<?php

namespace rock\mongodb\validate\locale\en;


use rock\validate\locale\Locale;

/**
 * Locale MongoId
 *
 * @codeCoverageIgnore
 * @package rock\validate\locale\en
 */
class MongoIdLocale extends Locale
{
    public function defaultTemplates()
    {
        return [
            self::MODE_DEFAULT => [
                self::STANDARD => '{{name}} must be an MongoId',
            ],
            self::MODE_NEGATIVE => [
                self::STANDARD => '{{name}} must not be an MongoId',
            ]
        ];
    }

    public function defaultPlaceholders($attribute = null)
    {
        return ['name' =>  $attribute];
    }
}