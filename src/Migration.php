<?php
namespace rock\mongodb;

use rock\components\ComponentsInterface;
use rock\components\ComponentsTrait;
use rock\db\common\MigrationInterface;
use rock\helpers\Instance;
use rock\helpers\Json;

/**
 * Migration is the base class for representing a MongoDB migration.
 *
 * Each child class of Migration represents an individual MongoDB migration which
 * is identified by the child class name.
 *
 * Within each migration, the {@see \rock\db\MigrationInterface::up()} method should be overridden to contain the logic
 * for "upgrading" the database; while the {@see \rock\db\MigrationInterface::down()} method for the "downgrading"
 * logic.
 *
 * Migration provides a set of convenient methods for manipulating MongoDB data and schema.
 * For example, the {@see \rock\mongodb\Migration::createIndex()} method can be used to create a collection index.
 * Compared with the same methods in {@see \rock\mongodb\Collection}, these methods will display extra
 * information showing the method parameters and execution time, which may be useful when
 * applying migrations.
 */
abstract class Migration implements MigrationInterface, ComponentsInterface
{
    use ComponentsTrait;

    /**
     * @var Connection|string the MongoDB connection object or the application component ID of the MongoDB connection
     * that this migration should work with.
     */
    public $connection = 'mongodb';


    /**
     * Initializes the migration.
     * This method will set {@see \rock\mongodb\Migration::$connection} to be the 'mongodb' application component, if it is null.
     */
    public function init()
    {
        $this->connection = Instance::ensure($this->connection, Connection::className());
    }

    /**
     * Creates new collection with the specified options.
     * @param string|array $collection name of the collection
     * @param array $options collection options in format: "name" => "value"
     */
    public function createCollection($collection, array $options = [])
    {
        if (is_array($collection)) {
            list($database, $collectionName) = $collection;
        } else {
            $database = null;
            $collectionName = $collection;
        }
        echo "    > create collection " . $this->composeCollectionLogName($collection) . " ...";
        $time = microtime(true);
        $this->connection->getDatabase($database)->createCollection($collectionName, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    /**
     * Drops existing collection.
     * @param string|array $collection name of the collection
     */
    public function dropCollection($collection)
    {
        echo "    > drop collection " . $this->composeCollectionLogName($collection) . " ...";
        $time = microtime(true);
        $this->connection->getCollection($collection)->drop();
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    /**
     * Creates an index on the collection and the specified fields.
     * @param string|array $collection name of the collection
     * @param array|string $columns column name or list of column names.
     * @param array $options list of options in format: optionName => optionValue.
     */
    public function createIndex($collection, $columns, array $options = [])
    {
        echo "    > create index on " . $this->composeCollectionLogName($collection) . " (" . Json::encode((array)$columns) . empty($options) ? "" : ", " . Json::encode($options) . ") ...";
        $time = microtime(true);
        $this->connection->getCollection($collection)->createIndex($columns, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    /**
     * Drop indexes for specified column(s).
     * @param string|array $collection name of the collection
     * @param string|array $columns column name or list of column names.
     */
    public function dropIndex($collection, $columns)
    {
        echo "    > drop index on " . $this->composeCollectionLogName($collection) . " (" . Json::encode((array)$columns) . ") ...";
        $time = microtime(true);
        $this->connection->getCollection($collection)->dropIndex($columns);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    /**
     * Drops all indexes for specified collection.
     * @param string|array $collection name of the collection.
     */
    public function dropAllIndexes($collection)
    {
        echo "    > drop all indexes on " . $this->composeCollectionLogName($collection) . ") ...";
        $time = microtime(true);
        $this->connection->getCollection($collection)->dropAllIndexes();
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
    }

    /**
     * Inserts new data into collection.
     * @param array|string $collection collection name.
     * @param array|object $data data to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId new record id instance.
     */
    public function insert($collection, $data, array $options = [])
    {
        echo "    > insert into " . $this->composeCollectionLogName($collection) . ") ...";
        $time = microtime(true);
        $id = $this->connection->getCollection($collection)->insert($data, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        return $id;
    }

    /**
     * Inserts several new rows into collection.
     * @param array|string $collection collection name.
     * @param array $rows array of arrays or objects to be inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return array inserted data, each row will have "_id" key assigned to it.
     */
    public function batchInsert($collection, array $rows, array $options = [])
    {
        echo "    > insert into " . $this->composeCollectionLogName($collection) . ") ...";
        $time = microtime(true);
        $rows = $this->connection->getCollection($collection)->batchInsert($rows, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        return $rows;
    }

    /**
     * Updates the rows, which matches given criteria by given data.
     * Note: for "multiple" mode Mongo requires explicit strategy "$set" or "$inc"
     * to be specified for the "newData". If no strategy is passed "$set" will be used.
     * @param array|string $collection collection name.
     * @param array $condition description of the objects to update.
     * @param array $newData the object with which to update the matching records.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     */
    public function update($collection, array $condition, array $newData, array $options = [])
    {
        echo "    > update " . $this->composeCollectionLogName($collection) . ") ...";
        $time = microtime(true);
        $result = $this->connection->getCollection($collection)->update($condition, $newData, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        return $result;
    }

    /**
     * Update the existing database data, otherwise insert this data
     * @param array|string $collection collection name.
     * @param array|object $data data to be updated/inserted.
     * @param array $options list of options in format: optionName => optionValue.
     * @return \MongoId updated/new record id instance.
     */
    public function save($collection, $data, array $options = [])
    {
        echo "    > save " . $this->composeCollectionLogName($collection) . ") ...";
        $time = microtime(true);
        $id = $this->connection->getCollection($collection)->save($data, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        return $id;
    }

    /**
     * Removes data from the collection.
     * @param array|string $collection collection name.
     * @param array $condition description of records to remove.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer|boolean number of updated documents or whether operation was successful.
     */
    public function remove($collection, array $condition = [], array $options = [])
    {
        echo "    > remove " . $this->composeCollectionLogName($collection) . ") ...";
        $time = microtime(true);
        $result = $this->connection->getCollection($collection)->remove($condition, $options);
        echo " done (time: " . sprintf('%.3f', microtime(true) - $time) . "s)\n";
        return $result;
    }

    /**
     * Composes string representing collection name.
     * @param array|string $collection collection name.
     * @return string collection name.
     */
    protected function composeCollectionLogName($collection)
    {
        if (is_array($collection)) {
            list($database, $collection) = $collection;
            return $database . '.' . $collection;
        } else {
            return $collection;
        }
    }
}