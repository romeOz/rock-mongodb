<?php

namespace rockunit\models;

class Item extends ActiveRecord
{
    public static function collectionName()
    {
        return 'item';
    }

    public function attributes()
    {
        return [
            '_id',
            'name',
            'price',
        ];
    }
}