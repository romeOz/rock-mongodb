<?php

namespace rockunit\models\file;

/**
 * Test Mongo ActiveRecord
 */
class ActiveRecord extends \rock\mongodb\file\ActiveRecord
{
    public static $connection;

    public static function getConnection()
    {
        return self::$connection;
    }
}
