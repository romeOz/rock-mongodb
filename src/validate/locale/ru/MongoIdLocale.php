<?php

namespace rock\mongodb\validate\locale\ru;

/**
 * Locale MongoId
 *
 * @codeCoverageIgnore
 * @package rock\validate\locale\ru
 */
class MongoIdLocale extends \rock\mongodb\validate\locale\en\MongoIdLocale
{
    public function defaultTemplates()
    {
        return [
            self::MODE_DEFAULT => [
                self::STANDARD => '{{name}} должен быть в формате MongoId',
            ],
            self::MODE_NEGATIVE => [
                self::STANDARD => '{{name}} не должен быть в формате MongoId',
            ]
        ];
    }
}