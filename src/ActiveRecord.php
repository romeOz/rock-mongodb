<?php
namespace rock\mongodb;

use rock\db\common\BaseActiveRecord;
use rock\helpers\Inflector;
use rock\helpers\Instance;
use rock\helpers\ObjectHelper;

/**
 * ActiveRecord is the base class for classes representing Mongo documents in terms of objects.
 */
abstract class ActiveRecord extends BaseActiveRecord
{
    /**
     * Returns the Mongo connection used by this AR class.
     * By default, the "mongodb" application component is used as the Mongo connection.
     * You may override this method if you want to use a different database connection.
     * @return Connection the database connection used by this AR class.
     */
    public static function getConnection()
    {
        return Instance::ensure('mongodb', Connection::className());
    }

    /**
     * Updates all documents in the collection using the provided attribute values and conditions.
     * For example, to change the status to be 1 for all customers whose status is 2:
     *
     * ```php
     * Customer::updateAll(['status' => 1], ['status' => 2]);
     * ```
     *
     * @param array $attributes attribute values (name-value pairs) to be saved into the collection
     * @param array $condition description of the objects to update.
     * Please refer to {@see \rock\mongodb\Query::where()} on how to specify this parameter.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer the number of documents updated.
     */
    public static function updateAll(array $attributes, $condition = [], array $options = [])
    {
        return static::getCollection()->update($condition, $attributes, $options);
    }

    /**
     * Updates all documents in the collection using the provided counter changes and conditions.
     * For example, to increment all customers' age by 1,
     *
     * ```php
     * Customer::updateAllCounters(['age' => 1]);
     * ```
     *
     * @param array $counters the counters to be updated (attribute name => increment value).
     * Use negative values if you want to decrement the counters.
     * @param array $condition description of the objects to update.
     * Please refer to {@see \rock\mongodb\Query::where()} on how to specify this parameter.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer the number of documents updated.
     */
    public static function updateAllCounters(array $counters, $condition = [], array $options = [])
    {
        return static::getCollection()->update($condition, ['$inc' => $counters], $options);
    }

    /**
     * Deletes documents in the collection using the provided conditions.
     * WARNING: If you do not specify any condition, this method will delete documents rows in the collection.
     *
     * For example, to delete all customers whose status is 3:
     *
     * ```php
     * Customer::deleteAll(['status' => 3]);
     * ```
     *
     * @param array $condition description of the objects to delete.
     * Please refer to {@see \rock\mongodb\Query::where()} on how to specify this parameter.
     * @param array $options list of options in format: optionName => optionValue.
     * @return integer the number of documents deleted.
     */
    public static function deleteAll($condition = [], array $options = [])
    {
        return static::getCollection()->remove($condition, $options);
    }

    /**
     * @inheritdoc
     * @return ActiveQuery the newly created {@see \rock\mongodb\ActiveQuery} instance.
     */
    public static function find()
    {
        return new ActiveQuery(get_called_class());
    }

    /**
     * Declares the name of the Mongo collection associated with this AR class.
     *
     * Collection name can be either a string or array:
     *  - if string considered as the name of the collection inside the default database.
     *  - if array - first element considered as the name of the database, second - as
     *    name of collection inside that database
     *
     * By default this method returns the class name as the collection name by calling {@see \rock\helpers\Inflector::camel2id()}.
     * For example, 'Customer' becomes 'customer', and 'OrderItem' becomes
     * 'order_item'. You may override this method if the collection is not named after this convention.
     * @return string|array the collection name
     */
    public static function collectionName()
    {
        return Inflector::camel2id(ObjectHelper::basename(get_called_class()), '_');
    }

    /**
     * Return the Mongo collection instance for this AR class.
     * @return Collection collection instance.
     */
    public static function getCollection()
    {
        return static::getConnection()->getCollection(static::collectionName());
    }

    /**
     * Returns the primary key name(s) for this AR class.
     * The default implementation will return ['_id'].
     *
     * Note that an array should be returned even for a collection with single primary key.
     *
     * @return string[] the primary keys of the associated Mongo collection.
     */
    public static function primaryKey()
    {
        return ['_id'];
    }

    /**
     * Returns the list of all attribute names of the model.
     * This method must be overridden by child classes to define available attributes.
     * Note: primary key attribute "_id" should be always present in returned array.
     * For example:
     *
     * ```php
     * public function attributes()
     * {
     *     return ['_id', 'name', 'address', 'status'];
     * }
     * ```
     *
     * @throws MongoException if not implemented
     * @return array list of attribute names.
     */
    public function attributes()
    {
        throw new MongoException('The attributes() method of mongodb ActiveRecord has to be implemented by child classes.');
    }

    /**
     * Inserts a row into the associated Mongo collection using the attribute values of this record.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see \rock\components\Model::beforeValidate()} when `$runValidation` is true. If validation
     *    fails, it will skip the rest of the steps;
     * 2. call  {@see \rock\components\Model::afterValidate()} when `$runValidation` is true.
     * 3. call {@see \rock\db\common\BaseActiveRecord::beforeSave()}. If the method returns false, it will skip the
     *    rest of the steps;
     * 4. insert the record into collection. If this fails, it will skip the rest of the steps;
     * 5. call {@see \rock\db\common\BaseActiveRecord::afterSave()};
     *
     * In the above step 1, 2, 3 and 5, events {@see \rock\components\Model::EVENT_BEFORE_VALIDATE},
     * {@see \rock\db\common\BaseActiveRecord::EVENT_BEFORE_INSERT}, {@see \rock\db\common\BaseActiveRecord::EVENT_AFTER_INSERT} and {@see \rock\components\Model::EVENT_AFTER_VALIDATE}
     * will be raised by the corresponding methods.
     *
     * Only the {@see \rock\db\common\BaseActiveRecord::$dirtyAttributes}(changed attribute values) will be inserted into database.
     *
     * If the primary key  is null during insertion, it will be populated with the actual
     * value after insertion.
     *
     * For example, to insert a customer record:
     *
     * ```php
     * $customer = new Customer;
     * $customer->name = $name;
     * $customer->email = $email;
     * $customer->insert();
     * ```
     *
     * @param boolean $runValidation whether to perform validation before saving the record.
     * If the validation fails, the record will not be inserted into the collection.
     * @param array $attributeNames list of attribute names that need to be saved. Defaults to null,
     * meaning all attributes that are loaded will be saved.
     * @return boolean whether the attributes are valid and the record is inserted successfully.
     * @throws \Exception in case insert failed.
     */
    public function insert($runValidation = true, array $attributeNames = null)
    {
        if ($runValidation && !$this->validate($attributeNames)) {
            return false;
        }
        $result = $this->insertInternal($attributeNames);

        return $result;
    }

    /**
     * @see ActiveRecord::insert()
     * @param array|null $attributes
     * @return bool
     * @throws MongoException
     */
    protected function insertInternal(array $attributes = null)
    {
        if (!$this->beforeSave(true)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $currentAttributes = $this->getAttributes();
            foreach ($this->primaryKey() as $key) {
                if (isset($currentAttributes[$key])) {
                    $values[$key] = $currentAttributes[$key];
                }
            }
        }
        $newId = static::getCollection()->insert($values);
        $this->setAttribute('_id', $newId);
        $values['_id'] = $newId;

        $changedAttributes = array_fill_keys(array_keys($values), null);
        $this->setOldAttributes($values);
        $this->afterSave(true, $changedAttributes);

        return true;
    }

    /**
     * @inheritdoc
     * @see ActiveRecord::update()
     * @throws MongoException
     */
    protected function updateInternal(array $attributes = null)
    {
        if (!$this->beforeSave(false)) {
            return false;
        }
        $values = $this->getDirtyAttributes($attributes);
        if (empty($values)) {
            $this->afterSave(false, $values);
            return 0;
        }
        $condition = $this->getOldPrimaryKey(true);
        $lock = $this->optimisticLock();
        if ($lock !== null) {
            if (!isset($values[$lock])) {
                $values[$lock] = $this->$lock + 1;
            }
            $condition[$lock] = $this->$lock;
        }
        // We do not check the return value of update() because it's possible
        // that it doesn't change anything and thus returns 0.
        $rows = static::getCollection()->update($condition, $values);

        if ($lock !== null && !$rows) {
            throw new MongoException('The object being updated is outdated.');
        }

        if (isset($values[$lock])) {
            $this->$lock = $values[$lock];
        }

        $changedAttributes = [];
        foreach ($values as $name => $value) {
            $changedAttributes[$name] = $this->getOldAttribute($name);
            $this->setOldAttribute($name, $value);
        }
        $this->afterSave(false, $changedAttributes);

        return $rows;
    }

    /**
     * Deletes the document corresponding to this active record from the collection.
     *
     * This method performs the following steps in order:
     *
     * 1. call {@see \rock\db\common\BaseActiveRecord::beforeDelete()}. If the method returns false, it will skip the
     *    rest of the steps;
     * 2. delete the document from the collection;
     * 3. call {@see \rock\db\common\BaseActiveRecord::afterDelete()}.
     *
     * In the above step 1 and 3, events named {@see \rock\db\common\BaseActiveRecord::EVENT_BEFORE_DELETE} and {@see \rock\db\common\BaseActiveRecord::EVENT_AFTER_DELETE}
     * will be raised by the corresponding methods.
     *
     * @return integer|boolean the number of documents deleted, or false if the deletion is unsuccessful for some reason.
     * Note that it is possible the number of documents deleted is 0, even though the deletion execution is successful.
     * @throws MongoException if {@see \rock\db\common\BaseActiveRecord::optimisticLock()}(optimistic locking) is enabled and the data
     * being deleted is outdated.
     * @throws \Exception in case delete failed.
     */
    public function delete()
    {
        $result = false;
        if ($this->beforeDelete()) {
            $result = $this->deleteInternal();
            $this->afterDelete();
        }

        return $result;
    }

    /**
     * @see ActiveRecord::delete()
     * @throws MongoException
     */
    protected function deleteInternal()
    {
        // we do not check the return value of deleteAll() because it's possible
        // the record is already deleted in the database and thus the method will return 0
        $condition = $this->getOldPrimaryKey(true);
        $lock = $this->optimisticLock();
        if ($lock !== null) {
            $condition[$lock] = $this->$lock;
        }
        $result = static::getCollection()->remove($condition);
        if ($lock !== null && !$result) {
            throw new MongoException('The object being deleted is outdated.');
        }
        $this->setOldAttributes(null);

        return $result;
    }

    /**
     * Returns a value indicating whether the given active record is the same as the current one.
     * The comparison is made by comparing the collection names and the primary key values of the two active records.
     * If one of the records  {@see \rock\db\common\BaseActiveRecord::$isNewRecord}(is new) they are also considered not equal.
     * @param ActiveRecord $record record to compare to
     * @return boolean whether the two active records refer to the same row in the same Mongo collection.
     */
    public function equals($record)
    {
        if ($this->isNewRecord || $record->isNewRecord) {
            return false;
        }

        return $this->collectionName() === $record->collectionName() && (string)$this->getPrimaryKey() === (string)$record->getPrimaryKey();
    }
}