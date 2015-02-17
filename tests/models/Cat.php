<?php

namespace rockunit\models;


/**
 * Class Cat
 */
class Cat extends Animal
{
    /**
     *
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row)
    {
        parent::populateRecord($record, $row);

        $record->does = 'meow';
    }

}
