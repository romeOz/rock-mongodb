<?php

namespace rock\mongodb\file;

use rock\db\common\ConnectionInterface;
use rock\helpers\Instance;
use rock\mongodb\Connection;

/**
 * Query represents Mongo "find" operation for GridFS collection.
 *
 * Query behaves exactly as regular {@see \rock\mongodb\Query}.
 * Found files will be represented as arrays of file document attributes with
 * additional 'file' key, which stores {@see \MongoGridFSFile} instance.
 *
 * @property Collection $collection Collection instance. This property is read-only.
 */
class Query extends \rock\mongodb\Query
{
    /**
     * @inheritdoc
     */
    public function one(ConnectionInterface $connection = null)
    {
        $row = parent::one($connection);
        if ($row !== null) {
            $models = $this->populate([$row]);
            return reset($models) ?: null;
        } else {
            return null;
        }
    }

    /**
     * Returns the Mongo collection for this query.
     *
     * @param ConnectionInterface $connection Mongo connection.
     * @return Collection collection instance.
     */
    public function getCollection(ConnectionInterface $connection = null)
    {
        $this->connection = isset($connection)
            ? $connection
            : Instance::ensure($this->connection, Connection::className());

        $this->calculateCacheParams($this->connection);
        return $this->connection->getFileCollection($this->from);
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * This method is internally used to convert the data fetched from database
     * into the format as required by this query.
     * @param array $rows the raw query result from database
     * @return array the converted query result
     */
    public function populate(array $rows)
    {
        $result = [];
        foreach ($rows as $file) {
            $row = $file->file;
            $row['file'] = $file;
            $result[] = $row;
        }
        return parent::populate($result);
    }
}