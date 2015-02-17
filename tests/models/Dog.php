<?php

namespace rockunit\models;

/**
 * Class Dog
 */
class Dog extends Animal
{
    /**
     *
     * @param self $record
     * @param array $row
     */
    public static function populateRecord($record, $row)
    {
        parent::populateRecord($record, $row);

        $record->does = 'bark';
    }
}