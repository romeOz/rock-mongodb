<?php

namespace rock\mongodb;

use rock\base\ObjectInterface;
use rock\base\ObjectTrait;
use rock\cache\CacheInterface;
use rock\db\common\CommonCacheTrait;
use rock\helpers\Instance;
use rock\helpers\Trace;

/**
 * Collection represents the Mongo collection information.
 *
 * A collection object is usually created by calling {@see \rock\mongodb\Database::getCollection()} or {@see \rock\mongodb\Connection::getCollection()}.
 *
 * Collection provides the basic interface for the Mongo queries, mostly: insert, update, delete operations.
 * For example:
 *
 * ```php
 * $collection = Rock::$app->mongodb->getCollection('customer');
 * $collection->insert(['name' => 'John Smith', 'status' => 1]);
 * ```
 *
 * To perform "find" queries, please use {@see \rock\mongodb\Query} instead.
 *
 * Mongo uses JSON format to specify query conditions with quite specific syntax.
 * However Collection class provides the ability of "translating" common condition format used "rock\db\*"
 * into Mongo condition.
 * For example:
 *
 * ```php
 * $condition = [
 *     [
 *         'OR',
 *         ['AND', ['first_name' => 'John'], ['last_name' => 'Smith']],
 *         ['status' => [1, 2, 3]]
 *     ],
 * ];
 *
 * print_r($collection->buildCondition($condition));
 *
 * // outputs :
 * [
 *     '$or' => [
 *         [
 *             'first_name' => 'John',
 *             'last_name' => 'John',
 *         ],
 *         [
 *             'status' => ['$in' => [1, 2, 3]],
 *         ]
 *     ]
 * ]
 * ```
 *
 * Note: condition values for the key '_id' will be automatically cast to {@see \MongoId} instance,
 * even if they are plain strings. However, if you have other columns, containing {@see \MongoId}, you
 * should take care of possible typecast on your own.
 *
 * @property string $fullName Full name of this collection, including database name. This property is
 * read-only.
 * @property array $lastError Last error information. This property is read-only.
 * @property string $name Name of this collection. This property is read-only.
 */
class Collection implements ObjectInterface
{
    use ObjectTrait;
    use CommonCacheTrait;

    /**
     * @var \MongoCollection Mongo collection instance.
     */
    public $mongoCollection;
    /**
     * @var Connection
     */
    public $connection;


    /**
     * @return string name of this collection.
     */
    public function getName()
    {
        return $this->mongoCollection->getName();
    }

    /**
     * @return string full name of this collection, including database name.
     */
    public function getFullName()
    {
        return $this->mongoCollection->__toString();
    }

    /**
     * @return array last error information.
     */
    public function getLastError()
    {
        return $this->mongoCollection->db->lastError();
    }

    /**
     * Composes log/profile token.
     * @param string $command command name
     * @param array $arguments command arguments.
     * @return string token.
     */
    protected function composeLogToken($command, array $arguments = [])
    {
        $parts = [];
        foreach ($arguments as $argument) {
            $parts[] = is_scalar($argument) ? $argument : $this->encodeLogData($argument);
        }

        return $this->getFullName() . '.' . $command . '(' . implode(', ', $parts) . ')';
    }

    /**
     * Encodes complex log data into JSON format string.
     * @param mixed $data raw data.
     * @return string encoded data string.
     */
    protected function encodeLogData($data)
    {
        return json_encode($this->processLogData($data));
    }

    /**
     * Pre-processes the log data before sending it to `json_encode()`.
     * @param mixed $data raw data.
     * @return mixed the processed data.
     */
    protected function processLogData($data)
    {
        if (is_object($data)) {
            if ($data instanceof \MongoId ||
                $data instanceof \MongoRegex ||
                $data instanceof \MongoDate ||
                $data instanceof \MongoInt32 ||
                $data instanceof \MongoInt64 ||
                $data instanceof \MongoTimestamp
            ) {
                $data = get_class($data) . '(' . $data->__toString() . ')';
            } elseif ($data instanceof \MongoCode) {
                $data = 'MongoCode( ' . $data->__toString() . ' )';
            } elseif ($data instanceof \MongoBinData) {
                $data = 'MongoBinData(...)';
            } elseif ($data instanceof \MongoDBRef) {
                $data = 'MongoDBRef(...)';
            } elseif ($data instanceof \MongoMinKey || $data instanceof \MongoMaxKey) {
                $data = get_class($data);
            } else {
                $result = [];
                foreach ($data as $name => $value) {
                    $result[$name] = $value;
                }
                $data = $result;
            }

            if ($data === []) {
                return new \stdClass();
            }
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = $this->processLogData($value);
                }
            }
        }

        return $data;
    }

    /**
     * Drops this collection.
     *
     * @throws MongoException on failure.
     * @return boolean whether the operation successful.
     */
    public function drop()
    {
        $rawQuery = $this->composeLogToken('drop');
        $token = [
            //'dsn' => $connection->dsn,
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        Trace::beginProfile('mongodb.query', $token);
        //Log::info($rawQuery);
        try {
            $result = $this->mongoCollection->drop();
            $this->tryResultError($result);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return true;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);

            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Creates an index on the collection and the specified fields.
     *
     * @param array|string $columns column name or list of column names.
     * If array is given, each element in the array has as key the field name, and as
     * value either 1 for ascending sort, or -1 for descending sort.
     * You can specify field using native numeric key with the field name as a value,
     * in this case ascending sort will be used.
     * For example:
     *
     * ```php
     * [
     *     'name',
     *     'status' => -1,
     * ]
     * ```
     *
     * @param array $options list of options in format: optionName => optionValue.
     * @throws MongoException on failure.
     * @return boolean whether the operation successful.
     */
    public function createIndex($columns, array $options = [])
    {
        $columns = (array)$columns;
        $keys = $this->normalizeIndexKeys($columns);
        $rawQuery = $this->composeLogToken('createIndex', [$keys, $options]);
        $token = [
            //'dsn' => $connection->dsn,
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];

        $options = array_merge(['w' => 1], $options);
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            if (method_exists($this->mongoCollection, 'createIndex')) {
                $result = $this->mongoCollection->createIndex($keys, $options);
            } else {
                $result = $this->mongoCollection->ensureIndex($keys, $options);
            }
            $this->tryResultError($result);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return true;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);

            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Drop indexes for specified column(s).
     *
     * @param string|array $columns column name or list of column names.
     * If array is given, each element in the array has as key the field name, and as
     * value either 1 for ascending sort, or -1 for descending sort.
     * Use value 'text' to specify text index.
     * You can specify field using native numeric key with the field name as a value,
     * in this case ascending sort will be used.
     * For example:
     *
     * ```php
     * [
     *     'name',
     *     'status' => -1,
     *     'description' => 'text',
     * ]
     * ```
     *
     * @throws MongoException on failure.
     * @return boolean whether the operation successful.
     */
    public function dropIndex($columns)
    {
        $columns = (array)$columns;
        $keys = $this->normalizeIndexKeys($columns);
        $rawQuery = $this->composeLogToken('dropIndex', [$keys]);
        $token = [
            //'dsn' => $connection->dsn,
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoCollection->deleteIndex($keys);
            $this->tryResultError($result);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return true;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);

            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Compose index keys from given columns/keys list.
     * @param array $columns raw columns/keys list.
     * @return array normalizes index keys array.
     */
    protected function normalizeIndexKeys(array $columns)
    {
        $keys = [];
        foreach ($columns as $key => $value) {
            if (is_numeric($key)) {
                $keys[$value] = \MongoCollection::ASCENDING;
            } else {
                $keys[$key] = $value;
            }
        }

        return $keys;
    }

    /**
     * Drops all indexes for this collection.
     *
     * @throws MongoException on failure.
     * @return integer count of dropped indexes.
     */
    public function dropAllIndexes()
    {
        $rawQuery = $this->composeLogToken('dropIndexes');
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoCollection->deleteIndexes();
            $this->tryResultError($result);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return $result['nIndexesWas'];
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);

            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Returns a cursor for the search results.
     * In order to perform "find" queries use {@see \rock\mongodb\Query} class.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @return \MongoCursor cursor for the search results
     * @see Query
     */
    public function find(array $condition = [], array $fields = [])
    {
        return $this->mongoCollection->find($this->buildCondition($condition), $fields);
    }

    /**
     * Returns a single document.
     * @param array $condition query condition
     * @param array $fields fields to be selected
     * @return array|null the single document. Null is returned if the query results in nothing.
     * @see http://www.php.net/manual/en/mongocollection.findone.php
     */
    public function findOne(array $condition = [], array $fields = [])
    {
        return $this->mongoCollection->findOne($this->buildCondition($condition), $fields);
    }

    /**
     * Updates a document and returns it.
     *
     * @param array $condition query condition
     * @param array $update update criteria
     * @param array $fields fields to be returned
     * @param array $options list of options in format: optionName => optionValue.
     * @return array|null the original document, or the modified document when $options['new'] is set.
     * @throws MongoException on failure.
     * @see http://www.php.net/manual/en/mongocollection.findandmodify.php
     */
    public function findAndModify(array $condition, array $update, array $fields = [], array $options = [])
    {
        $condition = $this->buildCondition($condition);
        $rawQuery = $this->composeLogToken('findAndModify', [$condition, $update, $fields, $options]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoCollection->findAndModify($condition, $update, $fields, $options);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return $result;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);

            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Inserts new data into collection.
     *
     * @param array|object $data data to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId new record id instance.
     * @throws MongoException on failure.
     */
    public function insert($data, array $options = [])
    {
        $rawQuery = $this->composeLogToken('insert', [$data]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $options = array_merge(['w' => 1], $options);
            $this->tryResultError($this->mongoCollection->insert($data, $options));
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return is_array($data) ? $data['_id'] : $data->_id;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);

            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Inserts several new rows into collection.
     *
     * @param array $rows array of arrays or objects to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array inserted data, each row will have "_id" key assigned to it.
     * @throws MongoException on failure.
     */
    public function batchInsert(array $rows, array $options = [])
    {
        $rawQuery = $this->composeLogToken('batchInsert', [$rows]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $options = array_merge(['w' => 1], $options);
            $this->tryResultError($this->mongoCollection->batchInsert($rows, $options));
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return $rows;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Updates the rows, which matches given criteria by given data.
     * Note: for "multiple" mode Mongo requires explicit strategy "$set" or "$inc"
     * to be specified for the "newData". If no strategy is passed "$set" will be used.
     *
     * @param array $condition description of the objects to update.
     * @param array $newData the object with which to update the matching records.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     * @throws MongoException on failure.
     */
    public function update(array $condition, array $newData, array $options = [])
    {
        $condition = $this->buildCondition($condition);
        $options = array_merge(['w' => 1, 'multiple' => true], $options);
        if ($options['multiple']) {
            $keys = array_keys($newData);
            if (!empty($keys) && strncmp('$', $keys[0], 1) !== 0) {
                $newData = ['$set' => $newData];
            }
        }
        $rawQuery = $this->composeLogToken('update', [$condition, $newData, $options]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoCollection->update($condition, $newData, $options);
            $this->tryResultError($result);
            $this->clearCache($this->getName(), $rawQuery);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);
            if (is_array($result) && array_key_exists('n', $result)) {
                return $result['n'];
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    protected function clearCache($table, $rawSql)
    {
        if (!$this->connection->autoClearCache || (!$tables = $this->connection->queryCacheTags ?: [$table])) {
            return;
        }

        /** @var $cache CacheInterface */
        $cache = Instance::ensure($this->connection->queryCache, null, [], false);
        if (!$cache instanceof CacheInterface) {
            return;
        }

        $cache->removeMultiTags($tables);

        $token = [
            'dsn' => $this->connection->dsn,
            'sql' => $rawSql,
            'message' => 'Clear cache by tags: ' . implode(',', $tables),
        ];
        Trace::trace('cache.mongodb', $token);
    }

    /**
     * Update the existing database data, otherwise insert this data
     *
     * @param array|object $data data to be updated/inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId updated/new record id instance.
     * @throws MongoException on failure.
     */
    public function save($data, array $options = [])
    {
        $rawQuery = $this->composeLogToken('save', [$data]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $options = array_merge(['w' => 1], $options);
            $this->tryResultError($this->mongoCollection->save($data, $options));
            $this->clearCache($this->getName(), $rawQuery);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return is_array($data) ? $data['_id'] : $data->_id;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Removes data from the collection.
     *
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     * @throws MongoException on failure.
     * @see http://www.php.net/manual/en/mongocollection.remove.php
     */
    public function remove(array $condition = [], array $options = [])
    {
        $condition = $this->buildCondition($condition);
        $options = array_merge(['w' => 1, 'justOne' => false], $options);
        $rawQuery = $this->composeLogToken('remove', [$condition, $options]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoCollection->remove($condition, $options);
            $this->tryResultError($result);
            $this->clearCache($this->getName(), $rawQuery);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);
            if (is_array($result) && array_key_exists('n', $result)) {
                return $result['n'];
            } else {
                return true;
            }
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Returns a list of distinct values for the given column across a collection.
     *
     * @param string $column column to use.
     * @param array $condition query parameters.
     * @return array|boolean array of distinct values, or "false" on failure.
     * @throws MongoException on failure.
     */
    public function distinct($column, $condition = [])
    {
        $condition = $this->buildCondition($condition);
        $rawQuery = $this->composeLogToken('distinct', [$column, $condition]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        $cache = $cacheKey = null;
        if (($result = $this->getCache($rawQuery, $token, $cache, $cacheKey)) !== false) {
            return $result;
        }
        try {
            $result = $this->mongoCollection->distinct($column, $condition);
            $this->setCache($result, $cache, $cacheKey);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return $result;
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Performs aggregation using Mongo Aggregation Framework.
     *
     * @param array $pipeline list of pipeline operators, or just the first operator
     * @param array $pipelineOperator additional pipeline operator. You can specify additional
     * pipelines via third argument, fourth argument etc.
     * @return array the result of the aggregation.
     * @throws MongoException on failure.
     * @see http://docs.mongodb.org/manual/applications/aggregation/
     */
    public function aggregate($pipeline, $pipelineOperator = [])
    {
        $args = func_get_args();
        $rawQuery = $this->composeLogToken('aggregate', $args);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);

        $cache = $cacheKey = null;
        if (($result = $this->getCache($rawQuery, $token, $cache, $cacheKey)) !== false) {
            return $result;
        }
        try {
            $result = call_user_func_array([$this->mongoCollection, 'aggregate'], $args);
            $this->tryResultError($result);
            $this->setCache($result['result'], $cache, $cacheKey);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return $result['result'];
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Performs aggregation using Mongo "group" command.
     *
     * @param mixed $keys fields to group by. If an array or non-code object is passed,
     * it will be the key used to group results. If instance of {@see \MongoCode} passed,
     * it will be treated as a function that returns the key to group by.
     * @param array $initial Initial value of the aggregation counter object.
     * @param \MongoCode|string $reduce function that takes two arguments (the current
     * document and the aggregation to this point) and does the aggregation.
     * Argument will be automatically cast to {@see \MongoCode}.
     * @param array $options optional parameters to the group command. Valid options include:
     *  - condition - criteria for including a document in the aggregation.
     *  - finalize - function called once per unique key that takes the final output of the reduce function.
     * @return array the result of the aggregation.
     * @throws MongoException on failure.
     * @see http://docs.mongodb.org/manual/reference/command/group/
     */
    public function group($keys, $initial, $reduce, array $options = [])
    {
        if (!($reduce instanceof \MongoCode)) {
            $reduce = new \MongoCode((string)$reduce);
        }
        if (array_key_exists('condition', $options)) {
            $options['condition'] = $this->buildCondition($options['condition']);
        }
        if (array_key_exists('finalize', $options)) {
            if (!($options['finalize'] instanceof \MongoCode)) {
                $options['finalize'] = new \MongoCode((string)$options['finalize']);
            }
        }
        $rawQuery = $this->composeLogToken('group', [$keys, $initial, $reduce, $options]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);

        $cache = $cacheKey = null;
        if (($result = $this->getCache($rawQuery, $token, $cache, $cacheKey)) !== false) {
            return $result;
        }
        try {
            // Avoid possible E_DEPRECATED for $options:
            if (empty($options)) {
                $result = $this->mongoCollection->group($keys, $initial, $reduce);
            } else {
                $result = $this->mongoCollection->group($keys, $initial, $reduce, $options);
            }
            $this->tryResultError($result);

            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);
            if (array_key_exists('retval', $result)) {
                $this->setCache($result['retval'], $cache, $cacheKey);
                return $result['retval'];
            } else {
                $this->setCache([], $cache, $cacheKey);
                return [];
            }
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Performs aggregation using Mongo "map reduce" mechanism.
     * Note: this function will not return the aggregation result, instead it will
     * write it inside the another Mongo collection specified by "out" parameter.
     * For example:
     *
     * ```php
     * $customerCollection = Rock::$app->mongo->getCollection('customer');
     * $resultCollectionName = $customerCollection->mapReduce(
     *     'function () {emit(this.status, this.amount)}',
     *     'function (key, values) {return Array.sum(values)}',
     *     'mapReduceOut',
     *     ['status' => 3]
     * );
     * $query = new Query();
     * $results = $query->from($resultCollectionName)->all();
     * ```
     *
     * @param \MongoCode|string $map function, which emits map data from collection.
     * Argument will be automatically cast to {@see \MongoCode}.
     * @param \MongoCode|string $reduce function that takes two arguments (the map key
     * and the map values) and does the aggregation.
     * Argument will be automatically cast to {@see \MongoCode}.
     * @param string|array $out output collection name. It could be a string for simple output
     * ('outputCollection'), or an array for parametrized output (['merge' => 'outputCollection']).
     * You can pass ['inline' => true] to fetch the result at once without temporary collection usage.
     * @param array $condition criteria for including a document in the aggregation.
     * @param array $options additional optional parameters to the mapReduce command. Valid options include:
     *  - sort - array - key to sort the input documents. The sort key must be in an existing index for this collection.
     *  - limit - the maximum number of documents to return in the collection.
     *  - finalize - function, which follows the reduce method and modifies the output.
     *  - scope - array - specifies global variables that are accessible in the map, reduce and finalize functions.
     *  - jsMode - boolean -Specifies whether to convert intermediate data into BSON format between the execution of the map and reduce functions.
     *  - verbose - boolean - specifies whether to include the timing information in the result information.
     * @return string|array the map reduce output collection name or output results.
     * @throws MongoException on failure.
     */
    public function mapReduce($map, $reduce, $out, array $condition = [], array $options = [])
    {
        if (!($map instanceof \MongoCode)) {
            $map = new \MongoCode((string)$map);
        }
        if (!($reduce instanceof \MongoCode)) {
            $reduce = new \MongoCode((string)$reduce);
        }
        $command = [
            'mapReduce' => $this->getName(),
            'map' => $map,
            'reduce' => $reduce,
            'out' => $out
        ];
        if (!empty($condition)) {
            $command['query'] = $this->buildCondition($condition);
        }
        if (array_key_exists('finalize', $options)) {
            if (!($options['finalize'] instanceof \MongoCode)) {
                $options['finalize'] = new \MongoCode((string)$options['finalize']);
            }
        }
        if (!empty($options)) {
            $command = array_merge($command, $options);
        }
        $rawQuery = $this->composeLogToken('mapReduce', [$map, $reduce, $out]);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        $cache = $cacheKey = null;
        if (($result = $this->getCache($rawQuery, $token, $cache, $cacheKey)) !== false) {
            return $result;
        }
        try {
            $command = array_merge(['mapReduce' => $this->getName()], $command);
            $result = $this->mongoCollection->db->command($command);
            $this->tryResultError($result);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            if (array_key_exists('results', $result)) {
                $this->setCache($result['results'], $cache, $cacheKey);
                return $result['results'];
            }
            $this->setCache($result['result'], $cache, $cacheKey);
            return $result['result'];
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Performs full text search.
     *
     * @param string $search string of terms that MongoDB parses and uses to query the text index.
     * @param array $condition criteria for filtering a results list.
     * @param array $fields list of fields to be returned in result.
     * @param array $options additional optional parameters to the mapReduce command. Valid options include:
     *  - limit - the maximum number of documents to include in the response (by default 100).
     *  - language - the language that determines the list of stop words for the search
     *    and the rules for the stemmer and tokenizer. If not specified, the search uses the default
     *    language of the index.
     * @return array the highest scoring documents, in descending order by score.
     * @throws MongoException on failure.
     */
    public function fullTextSearch($search, array $condition = [], array $fields = [], array $options = [])
    {
        $command = [
            'search' => $search
        ];
        if (!empty($condition)) {
            $command['filter'] = $this->buildCondition($condition);
        }
        if (!empty($fields)) {
            $command['project'] = $fields;
        }
        if (!empty($options)) {
            $command = array_merge($command, $options);
        }
        $rawQuery = $this->composeLogToken('text', $command);
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($rawQuery);
        Trace::beginProfile('mongodb.query', $token);
        $cache = $cacheKey = null;
        if (($result = $this->getCache($rawQuery, $token, $cache, $cacheKey)) !== false) {
            return $result;
        }
        try {
            $command = array_merge(['text' => $this->getName()], $command);
            $result = $this->mongoCollection->db->command($command);
            $this->tryResultError($result);
            $this->setCache($result['results'], $cache, $cacheKey);
            Trace::endProfile('mongodb.query', $token);
            Trace::trace('mongodb.query', $token);

            return $result['results'];
        } catch (\Exception $e) {
            $message = $e->getMessage() . "\nThe query being executed was: $rawQuery";
            Trace::endProfile('mongodb.query', $token);
            $token['valid'] = false;
            $token['exception'] = defined('ROCK_DEBUG') && ROCK_DEBUG === true ? $e : $message;
            Trace::trace('mongodb.query', $token);
            throw new MongoException($message, [], 0, $e);
        }
    }

    /**
     * Checks if command execution result ended with an error.
     *
     * @param mixed $result raw command execution result.
     * @throws MongoException if an error occurred.
     */
    protected function tryResultError($result)
    {
        if (is_array($result)) {
            if (!empty($result['errmsg'])) {
                $errorMessage = $result['errmsg'];
            } elseif (!empty($result['err'])) {
                $errorMessage = $result['err'];
            }
            if (isset($errorMessage)) {
//                if (array_key_exists('code', $result)) {
//                    $errorCode = (int) $result['code'];
//                } elseif (array_key_exists('ok', $result)) {
//                    $errorCode = (int) $result['ok'];
//                } else {
//                    $errorCode = 0;
//                }
                throw new MongoException($errorMessage);
            }
        } elseif (!$result) {
            throw new MongoException('Unknown error, use "w=1" option to enable error tracking');
        }
    }

    /**
     * Throws an exception if there was an error on the last operation.
     *
     * @throws MongoException if an error occurred.
     */
    protected function tryLastError()
    {
        $this->tryResultError($this->getLastError());
    }

    /**
     * Converts "\rock\db\*" quick condition keyword into actual Mongo condition keyword.
     * @param string $key raw condition key.
     * @return string actual key.
     */
    protected function normalizeConditionKeyword($key)
    {
        static $map = [
            'AND' => '$and',
            'OR' => '$or',
            'IN' => '$in',
            'NOT IN' => '$nin',
        ];
        $matchKey = strtoupper($key);
        if (array_key_exists($matchKey, $map)) {
            return $map[$matchKey];
        } else {
            return $key;
        }
    }

    /**
     * Converts given value into {@see \MongoId} instance.
     * If array given, each element of it will be processed.
     * @param mixed $rawId raw id(s).
     * @return array|\MongoId normalized id(s).
     */
    protected function ensureMongoId($rawId)
    {
        if (is_array($rawId)) {
            $result = [];
            foreach ($rawId as $key => $value) {
                $result[$key] = $this->ensureMongoId($value);
            }

            return $result;
        } elseif (is_object($rawId)) {
            if ($rawId instanceof \MongoId) {
                return $rawId;
            } else {
                $rawId = (string)$rawId;
            }
        }
        try {
            $mongoId = new \MongoId($rawId);
        } catch (\MongoException $e) {
            // invalid id format
            $mongoId = $rawId;
        }

        return $mongoId;
    }

    /**
     * Parses the condition specification and generates the corresponding Mongo condition.
     *
     * @param array $condition the condition specification. Please refer to {@see \rock\db\common\QueryInterface::where()}
     * on how to specify a condition.
     * @return array the generated Mongo condition
     * @throws MongoException if the condition is in bad format
     */
    public function buildCondition($condition)
    {
        static $builders = [
            'NOT' => 'buildNotCondition',
            'AND' => 'buildAndCondition',
            'OR' => 'buildOrCondition',
            'BETWEEN' => 'buildBetweenCondition',
            'NOT BETWEEN' => 'buildBetweenCondition',
            'IN' => 'buildInCondition',
            'NOT IN' => 'buildInCondition',
            'REGEX' => 'buildRegexCondition',
            'LIKE' => 'buildLikeCondition',
        ];
        if (!is_array($condition)) {
            throw new MongoException('Condition should be an array.');
        } elseif (empty($condition)) {
            return [];
        }
        if (isset($condition[0])) { // operator format: operator, operand 1, operand 2, ...
            $operator = strtoupper($condition[0]);
            if (isset($builders[$operator])) {
                $method = $builders[$operator];
            } else {
                $operator = $condition[0];
                $method = 'buildSimpleCondition';
            }
            array_shift($condition);
            return $this->$method($operator, $condition);
        } else {
            // hash format: 'column1' => 'value1', 'column2' => 'value2', ...
            return $this->buildHashCondition($condition);
        }
    }

    /**
     * Creates a condition based on column-value pairs.
     * @param array $condition the condition specification.
     * @return array the generated Mongo condition.
     */
    public function buildHashCondition($condition)
    {
        $result = [];
        foreach ($condition as $name => $value) {
            if (strncmp('$', $name, 1) === 0) {
                // Native Mongo condition:
                $result[$name] = $value;
            } else {
                if (is_array($value)) {
                    if (array_key_exists(0, $value)) {
                        // Quick IN condition:
                        $result = array_merge($result, $this->buildInCondition('IN', [$name, $value]));
                    } else {
                        // Mongo complex condition:
                        $result[$name] = $value;
                    }
                } else {
                    // Direct match:
                    if ($name == '_id') {
                        $value = $this->ensureMongoId($value);
                    }
                    $result[$name] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Composes `NOT` condition.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     * @throws MongoException if wrong number of operands have been given.
     */
    public function buildNotCondition($operator, array $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new MongoException("Operator '$operator' requires two operands.");
        }
        list($name, $value) = $operands;
        $result = [];
        if (is_array($value)) {
            $result[$name] = ['$not' => $this->buildCondition($value)];
        } else {
            if ($name == '_id') {
                $value = $this->ensureMongoId($value);
            }
            $result[$name] = ['$ne' => $value];
        }
        return $result;
    }

    /**
     * Connects two or more conditions with the `AND` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     */
    public function buildAndCondition($operator, array $operands)
    {
        $operator = $this->normalizeConditionKeyword($operator);
        $parts = [];
        foreach ($operands as $operand) {
            $parts[] = $this->buildCondition($operand);
        }

        return [$operator => $parts];
    }

    /**
     * Connects two or more conditions with the `OR` operator.
     * @param string $operator the operator to use for connecting the given operands
     * @param array $operands the Mongo conditions to connect.
     * @return array the generated Mongo condition.
     */
    public function buildOrCondition($operator, array $operands)
    {
        $operator = $this->normalizeConditionKeyword($operator);
        $parts = [];
        foreach ($operands as $operand) {
            $parts[] = $this->buildCondition($operand);
        }

        return [$operator => $parts];
    }

    /**
     * Creates an Mongo condition, which emulates the `BETWEEN` operator.
     *
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name. The second and third operands
     * describe the interval that column value should be in.
     * @return array the generated Mongo condition.
     * @throws MongoException if wrong number of operands have been given.
     */
    public function buildBetweenCondition($operator, array $operands)
    {
        if (!isset($operands[0], $operands[1], $operands[2])) {
            throw new MongoException("Operator '$operator' requires three operands.");
        }
        list($column, $value1, $value2) = $operands;
        if (strncmp('NOT', $operator, 3) === 0) {
            return [
                $column => [
                    '$lt' => $value1,
                    '$gt' => $value2,
                ]
            ];
        } else {
            return [
                $column => [
                    '$gte' => $value1,
                    '$lte' => $value2,
                ]
            ];
        }
    }

    /**
     * Creates an Mongo condition with the `IN` operator.
     *
     * @param string $operator the operator to use (e.g. `IN` or `NOT IN`)
     * @param array $operands the first operand is the column name. If it is an array
     * a composite IN condition will be generated.
     * The second operand is an array of values that column value should be among.
     * @return array the generated Mongo condition.
     * @throws MongoException if wrong number of operands have been given.
     */
    public function buildInCondition($operator, array $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new MongoException("Operator '$operator' requires two operands.");
        }

        list($column, $values) = $operands;

        $values = (array)$values;

        if (!is_array($column)) {
            $columns = [$column];
            $values = [$column => $values];
        } elseif (count($column) < 2) {
            $columns = $column;
            $values = [$column[0] => $values];
        } else {
            $columns = $column;
        }

        $operator = $this->normalizeConditionKeyword($operator);
        $result = [];
        foreach ($columns as $column) {
            if ($column == '_id') {
                $inValues = $this->ensureMongoId($values[$column]);
            } else {
                $inValues = $values[$column];
            }
            $inValues = array_values($inValues);
            if (count($inValues) === 1 && $operator === '$in') {
                $result[$column] = $inValues[0];
            } else {
                $result[$column][$operator] = $inValues;
            }
        }

        return $result;
    }

    /**
     * Creates a Mongo regular expression condition.
     *
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return array the generated Mongo condition.
     * @throws MongoException if wrong number of operands have been given.
     */
    public function buildRegexCondition($operator, array $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new MongoException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (!($value instanceof \MongoRegex)) {
            $value = new \MongoRegex($value);
        }

        return [$column => $value];
    }

    /**
     * Creates a Mongo condition, which emulates the `LIKE` operator.
     *
     * @param string $operator the operator to use
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return array the generated Mongo condition.
     * @throws MongoException if wrong number of operands have been given.
     */
    public function buildLikeCondition($operator, array $operands)
    {
        if (!isset($operands[0], $operands[1])) {
            throw new MongoException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (!($value instanceof \MongoRegex)) {
            $value = new \MongoRegex('/' . preg_quote($value) . '/i');
        }

        return [$column => $value];
    }

    /**
     * Creates an Mongo condition like `{$operator:{field:value}}`.
     * @param string $operator the operator to use. Besides regular MongoDB operators, aliases like `>`, `<=`,
     * and so on, can be used here.
     * @param array $operands the first operand is the column name.
     * The second operand is a single value that column value should be compared with.
     * @return string the generated Mongo condition.
     * @throws MongoException if wrong number of operands have been given.
     */
    public function buildSimpleCondition($operator, $operands)
    {
        if (count($operands) !== 2) {
            throw new MongoException("Operator '$operator' requires two operands.");
        }
        list($column, $value) = $operands;
        if (strncmp('$', $operator, 1) !== 0) {
            static $operatorMap = [
                '>' => '$gt',
                '<' => '$lt',
                '>=' => '$gte',
                '<=' => '$lte',
                '!=' => '$ne',
                '<>' => '$ne',
                '=' => '$eq',
                '==' => '$eq',
            ];
            if (isset($operatorMap[$operator])) {
                $operator = $operatorMap[$operator];
            } else {
                throw new MongoException("Unsupported operator '{$operator}'.");
            }
        }
        return [$column => [$operator => $value]];
    }

    protected function getCache($rawQuery, $token, &$cache, &$cacheKey)
    {
        $this->calculateCacheParams($this->connection);
        /** @var $cache CacheInterface */
        if ($this->connection->enableQueryCache) {
            $cache = Instance::ensure($this->connection->queryCache, null, [], false);
        }

        if ($cache instanceof CacheInterface) {
            $cacheKey = json_encode([__METHOD__, $this->connection->dsn, $rawQuery]);
            if (($result = $cache->get($cacheKey)) !== false) {
                Trace::increment('cache.mongodb', 'Cache query MongoDB connection: ' . $this->connection->dsn);
                Trace::endProfile('mongodb.query', $token);
                $token['cache'] = true;
                Trace::trace('mongodb.query', $token);

                return $result;
            }
        }

        return false;
    }

    protected function setCache($result, $cache, $cacheKey)
    {
        if (isset($cache, $cacheKey) && $result !== false && $cache instanceof CacheInterface) {
            $cache->set($cacheKey,
                $result,
                $this->connection->queryCacheExpire,
                $this->connection->queryCacheTags ?: [$this->getName()]
            );
        }
    }
}