<?php

namespace rock\mongodb\sanitize\rules;
use rock\components\sanitize\ModelRule;
use rock\sanitize\SanitizeException;


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
 *             [Model::RULE_SANITIZE, '_id', 'mongoId']
 *         ];
 *     }
 * }
 * ```
 *
 */
class MongoIdRule extends ModelRule
{
    /**
     * @var string|null specifies the format, which validated attribute value should be converted to
     * in case validation was successful.
     * valid values are:
     * - 'string' - enforce value converted to plain string.
     * - 'object' - enforce value converted to {@see \MongoId} instance.
     * If not set - no conversion will be performed, leaving attribute value intact.
     */
    public $forceFormat;
    public $recursive = false;

    public function __construct($forceFormat = null, $config = [])
    {
        parent::__construct($config);
        $this->forceFormat = $forceFormat;
    }

    /**
     * @inheritdoc
     */
    public function sanitize($value)
    {
        $mongoId = $this->parseMongoId($value);
        if (is_object($mongoId)) {
            if ($this->forceFormat !== null) {
                switch ($this->forceFormat) {
                    case 'string' : {
                        return $mongoId->__toString();
                    }
                    case 'object' : {
                        return $mongoId;
                    }
                    default: {
                        throw new SanitizeException("Unrecognized format '{$this->forceFormat}'");
                    }
                }
            }
        }
        return $mongoId;
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