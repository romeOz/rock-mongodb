<?php

namespace rockunit\validate;


use rock\components\Model;
use rock\mongodb\ActiveRecord;
use rock\mongodb\validate\rules\MongoIdRule;


class MongoIdRuleTest extends \PHPUnit_Framework_TestCase
{
    public function testValidateValue()
    {
        $validator = new MongoIdRule();

        // false
        $this->assertFalse($validator->validate('id'));

        // true
        $this->assertTrue($validator->validate(new \MongoId('4d3ed089fb60ab534684b7e9')));
        $this->assertTrue($validator->validate('4d3ed089fb60ab534684b7e9'));
    }

    public function testValidateAttribute()
    {
        $m = new ModelIdTestRuleModel();

        // true
        $m->id = new \MongoId('4d3ed089fb60ab534684b7e9');
        $this->assertTrue($m->insert());
        $this->assertEmpty($m->getErrors());

        $m->setAttribute('id', '4d3ed089fb60ab534684b7e9');
        $this->assertTrue($m->insert());
        $this->assertEmpty($m->getErrors());

        // false
        $m->setAttribute('id', 'unknown');
        $this->assertFalse($m->insert());
        $this->assertNotEmpty($m->getErrors());
    }

    /**
     * @depends testValidateAttribute
     */
    public function testSanitizeValue()
    {
        $sanitizator = new \rock\mongodb\sanitize\rules\MongoIdRule();

        $this->assertNull($sanitizator->sanitize('id'));
        $this->assertInstanceOf('\MongoId', $sanitizator->sanitize(new \MongoId('4d3ed089fb60ab534684b7e9')));
        $this->assertInstanceOf('\MongoId', $sanitizator->sanitize('4d3ed089fb60ab534684b7e9'));

        $sanitizator->forceFormat = 'string';
        $this->assertSame('4d3ed089fb60ab534684b7e9', $sanitizator->sanitize(new \MongoId('4d3ed089fb60ab534684b7e9')));

        $m = new ModelIdTestSanitizeModel;
        $m->setAttribute('id', '4d3ed089fb60ab534684b7e9');
        $this->assertTrue($m->insert());
        $this->assertInstanceOf('\MongoId', $m->id);
        $this->assertInstanceOf('\MongoId', $m->getAttribute('id'));

        $m->setAttribute('id', new \MongoId('4d3ed089fb60ab534684b7e9'));
        $this->assertTrue($m->insert());
        $this->assertInstanceOf('\MongoId', $m->getAttribute('id'));

        $m = new ModelIdTestSanitizeModel;
        $m->setAttribute('id', 'unknown');
        $this->assertTrue($m->insert());
        $this->assertNull($m->id);
        $this->assertNull($m->getAttribute('id'));
    }
}

class MongoIdTestModel extends Model
{
    public $id;
}

class ModelIdTestRuleModel extends ActiveRecord
{
    public function attributes()
    {
        return ['id'];
    }

    public function rules()
    {
        return [
            [Model::RULE_VALIDATE, 'id', 'mongoId']
        ];
    }

    public function insert($runValidation = true, array $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }
        return true;
    }
}


class ModelIdTestSanitizeModel extends ActiveRecord
{
    public function attributes()
    {
        return ['id'];
    }

    public function rules()
    {
        return [
            [Model::RULE_SANITIZE, 'id', 'mongoId']
        ];
    }

    public function insert($runValidation = true, array $attributes = null)
    {
        if ($runValidation && !$this->validate($attributes)) {
            return false;
        }
        return true;
    }
}