<?php

namespace rock\mongodb\validate\rules;

use rock\components\validate\ModelRule;


/**
 * MongoId verifies if the attribute is a valid Mongo ID.
 * Attribute will be considered as valid, if it is an instance of {@see \MongoId} or a its string value.
 *
 * Usage example:
 *
 * ```php
 * class Customer extends rock\mongodb\ActiveRecord
 * {
 *     ...
 *     public function rules()
 *     {
 *         return [
 *             [Model::RULE_VALIDATE, '_id', 'mongoId']
 *         ];
 *     }
 * }
 * ```
 *
 */
class MongoIdRule extends ModelRule
{
    public function __construct($forceFormat = null, $config = [])
    {
        parent::__construct($config);
    }

    /**
     * @inheritdoc
     */
    public function validate($value)
    {
        $this->params['attribute'] = $this->attribute;
        return is_object($this->parseMongoId($value));
    }

    /**
     * @param mixed $value
     * @return \MongoId|null
     */
    private function parseMongoId($value)
    {
        if ($value instanceof \MongoId) {
            return $value;
        }
        try {
            return new \MongoId($value);
        } catch (\Exception $e) {
            return null;
        }
    }
}