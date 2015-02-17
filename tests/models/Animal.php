<?php

namespace rockunit\models;

/**
 * Class Animal
 */
class Animal extends ActiveRecord
{

    public $does;

    public static function collectionName()
    {
        return 'test_animals';
    }

    public function attributes()
    {
        return ['_id', 'type'];
    }

    public function init()
    {
        parent::init();
        $this->type = get_called_class();
    }

    public function getDoes()
    {
        return $this->does;
    }

    /**
     *
     * @param type $row
     * @return static
     */
    public static function instantiate($row)
    {
        $class = $row['type'];
        return new $class;
    }

}