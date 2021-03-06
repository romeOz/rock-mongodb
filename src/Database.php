<?php
namespace rock\mongodb;

use rock\base\ObjectInterface;
use rock\base\ObjectTrait;
use rock\helpers\Instance;
use rock\helpers\Json;
use rock\helpers\Trace;

/**
 * Database represents the Mongo database information.
 *
 * @property file\Collection $fileCollection Mongo GridFS collection. This property is read-only.
 * @property string $name Name of this database. This property is read-only.
 */
class Database implements ObjectInterface
{
    use ObjectTrait;

    /**
     * @var \MongoDB Mongo database instance.
     */
    public $mongoDb;
    /**
     * @var Connection
     */
    public $connection;

    /**
     * @var Collection[] list of collections.
     */
    private $_collections = [];
    /**
     * @var file\Collection[] list of GridFS collections.
     */
    private $_fileCollections = [];


    /**
     * @return string name of this database.
     */
    public function getName()
    {
        return $this->mongoDb->__toString();
    }

    /**
     * Returns the Mongo collection with the given name.
     * @param string $name collection name
     * @param boolean $refresh whether to reload the collection instance even if it is found in the cache.
     * @return Collection Mongo collection instance.
     */
    public function getCollection($name, $refresh = false)
    {
        if ($refresh || !array_key_exists($name, $this->_collections)) {
            $this->_collections[$name] = $this->selectCollection($name);
        }

        return $this->_collections[$name];
    }

    /**
     * Returns Mongo GridFS collection with given prefix.
     * @param string $prefix collection prefix.
     * @param boolean $refresh whether to reload the collection instance even if it is found in the cache.
     * @return file\Collection Mongo GridFS collection.
     */
    public function getFileCollection($prefix = 'fs', $refresh = false)
    {
        if ($refresh || !array_key_exists($prefix, $this->_fileCollections)) {
            $this->_fileCollections[$prefix] = $this->selectFileCollection($prefix);
        }

        return $this->_fileCollections[$prefix];
    }

    /**
     * Selects collection with given name.
     * @param string $name collection name.
     * @return Collection collection instance.
     */
    protected function selectCollection($name)
    {
        return Instance::ensure([
            'class' => Collection::className(),
            'mongoCollection' => $this->mongoDb->selectCollection($name),
            'connection' => $this->connection
        ]);
    }

    /**
     * Selects GridFS collection with given prefix.
     * @param string $prefix file collection prefix.
     * @return file\Collection file collection instance.
     */
    protected function selectFileCollection($prefix)
    {
        return Instance::ensure([
            'class' => \rock\mongodb\file\Collection::className(),
            'mongoCollection' => $this->mongoDb->getGridFS($prefix),
            'connection' => $this->connection
        ]);
    }

    /**
     * Creates new collection.
     * Note: Mongo creates new collections automatically on the first demand,
     * this method makes sense only for the migration script or for the case
     * you need to create collection with the specific options.
     *
     * @param string $name name of the collection
     * @param array $options collection options in format: "name" => "value"
     * @return \MongoCollection new Mongo collection instance.
     * @throws MongoException on failure.
     */
    public function createCollection($name, array $options = [])
    {
        $rawQuery = $this->getName() . '.create(' . $name . ', ' . Json::encode($options) . ')';
        $token = [
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($token);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoDb->createCollection($name, $options);
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
     * Executes Mongo command.
     *
     * @param array $command command specification.
     * @param array $options options in format: "name" => "value"
     * @return array database response.
     * @throws MongoException on failure.
     */
    public function executeCommand($command, array $options = [])
    {
        $rawQuery = $this->getName() . '.$cmd(' . Json::encode($command) . ', ' . Json::encode($options) . ')';
        $token = [
            //'dsn' => $connection->dsn,
            'query' => $rawQuery,
            'valid' => true,
            'cache' => false,
        ];
        //Log::info($token, __METHOD__);
        Trace::beginProfile('mongodb.query', $token);
        try {
            $result = $this->mongoDb->command($command, $options);
            $this->tryResultError($result);
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
                if (array_key_exists('ok', $result)) {
                    $errorCode = (int)$result['ok'];
                } else {
                    $errorCode = 0;
                }
                throw new MongoException("$errorMessage. Code: $errorCode.");
            }
        } elseif (!$result) {
            throw new MongoException('Unknown error, use "w=1" option to enable error tracking');
        }
    }
}
