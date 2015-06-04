<?php
namespace rock\mongodb\file;

use rock\db\common\ActiveQueryTrait;
use rock\db\common\ActiveRelationTrait;
use rock\db\common\ActiveQueryInterface;
use rock\db\common\ConnectionInterface;

/**
 * ActiveQuery represents a Mongo query associated with an file Active Record class.
 *
 * ActiveQuery instances are usually created by {@see \rock\db\common\ActiveRecordInterface::find()}.
 *
 * Because ActiveQuery extends from {@see \rock\mongodb\Query}, one can use query methods, such as {@see \rock\db\common\QueryInterface::where()},
 * {@see \rock\db\common\QueryInterface::orderBy()} to customize the query options.
 *
 * ActiveQuery also provides the following additional query options:
 *
 * - {@see \rock\db\common\ActiveQueryInterface::with()}: list of relations that this query should be performed with.
 * - {@see \rock\db\common\ActiveQueryInterface::asArray()}: whether to return each record as an array.
 *
 * These options can be configured using methods of the same name. For example:
 *
 * ```php
 * $images = ImageFile::find()->with('tags')->asArray()->all();
 * ```
 *
 * @property Collection $collection Collection instance. This property is read-only.
 *
 */
class ActiveQuery extends Query implements ActiveQueryInterface
{
    use ActiveQueryTrait;
    use ActiveRelationTrait;

    /**
     * @event Event an event that is triggered when the query is initialized via {@see \rock\base\ObjectInterface::init()}.
     */
    const EVENT_INIT = 'init';


    /**
     * Constructor.
     * @param array $modelClass the model class associated with this query
     * @param array $config configurations to be applied to the newly created query object
     */
    public function __construct($modelClass, $config = [])
    {
        $this->modelClass = $modelClass;
        parent::__construct($config);
    }

    /**
     * Initializes the object.
     * This method is called at the end of the constructor. The default implementation will trigger
     * an {@see \rock\mongodb\file\ActiveQuery::EVENT_INIT} event. If you override this method, make sure you call the parent implementation at the end
     * to ensure triggering of the event.
     */
    public function init()
    {
        parent::init();
        $this->trigger(self::EVENT_INIT);
    }

    /**
     * @inheritdoc
     */
    protected function buildCursor(ConnectionInterface $connection = null)
    {
        if ($this->primaryModel !== null) {
            // lazy loading
            if ($this->via instanceof self) {
                // via pivot collection
                $viaModels = $this->via->findPivotRows([$this->primaryModel]);
                $this->filterByModels($viaModels);
            } elseif (is_array($this->via)) {
                // via relation
                /* @var $viaQuery ActiveQuery */
                list($viaName, $viaQuery) = $this->via;
                if ($viaQuery->multiple) {
                    $viaModels = $viaQuery->all();
                    $this->primaryModel->populateRelation($viaName, $viaModels);
                } else {
                    $model = $viaQuery->one();
                    $this->primaryModel->populateRelation($viaName, $model);
                    $viaModels = $model === null ? [] : [$model];
                }
                $this->filterByModels($viaModels);
            } else {
                $this->filterByModels([$this->primaryModel]);
            }
        }

        return parent::buildCursor($connection);
    }

    /**
     * Executes query and returns a single row of result.
     *
     * @param ConnectionInterface|\rock\mongodb\Connection $connection the Mongo connection used to execute the query.
     * If null, the Mongo connection returned by {@see \rock\db\ActiveQueryTrait::$modelClass} will be used.
     * @return ActiveRecord|array|null a single row of query result. Depending on the setting of {@see \rock\db\ActiveQueryTrait::$asArray},
     * the query result may be either an array or an ActiveRecord object. Null will be returned
     * if the query results in nothing.
     */
    public function one(ConnectionInterface $connection = null)
    {
        // before event
        /** @var ActiveRecord $model */
        $model  = new $this->modelClass;
        if (!$model->beforeFind()) {
            return null;
        }

        return parent::one($connection);
    }

    /**
     * Executes query and returns all results as an array.
     *
     * @param ConnectionInterface|\rock\mongodb\Connection $connection the Mongo connection used to execute the query.
     * If null, the Mongo connection returned by {@see \rock\db\ActiveQueryTrait::$modelClass} will be used.
     * @return array|ActiveRecord[] the query results. If the query results in nothing, an empty array will be returned.
     */
    public function all(ConnectionInterface $connection = null)
    {
        // before event
        /** @var ActiveRecord $activeRecord */
        $activeRecord = new $this->modelClass;
        if (!$activeRecord->beforeFind()) {
            return [];
        }

        return parent::all($connection);
    }

    /**
     * Returns the Mongo collection for this query.
     *
     * @param ConnectionInterface $connection Mongo connection.
     * @return Collection collection instance.
     */
    public function getCollection(ConnectionInterface $connection = null)
    {
        /* @var $modelClass ActiveRecord */
        $modelClass = $this->modelClass;
        if (!isset($connection )) {
            $connection = $modelClass::getConnection();
        }
        $this->connection = $connection;
        if ($this->from === null) {
            $this->from = $modelClass::collectionName();
        }
        $this->calculateCacheParams($this->connection);
        return $this->connection->getFileCollection($this->from);
    }

    /**
     * Converts the raw query results into the format as specified by this query.
     * This method is internally used to convert the data fetched from MongoDB
     * into the format as required by this query.
     * @param array $rows the raw query result from MongoDB
     * @return array the converted query result
     */
    public function populate(array $rows)
    {
        if (empty($rows)) {
            return [];
        }

        $indexBy = $this->indexBy;
        $this->indexBy = null;
        $rows = parent::populate($rows);
        $this->indexBy = $indexBy;
        $models = $this->createModels($rows);
        if (!empty($this->with)) {
            $this->findWith($this->with, $models);
        }
        if (!$this->asArray) {
            foreach ($models as $model) {
                $model->afterFind();
            }
        }
        return $models;
    }
}