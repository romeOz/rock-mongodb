<?php

namespace rockunit;

use rock\helpers\Trace;
use rock\mongodb\Query;
use rockunit\common\CommonTestTrait;

/**
 * @group mongodb
 */
class QueryRunTest extends MongoDbTestCase
{
    use CommonTestTrait;

    public static function tearDownAfterClass()
    {
        parent::tearDownAfterClass();
        static::getCache()->flush();
        static::clearRuntime();
    }

    protected function setUp()
    {
        parent::setUp();
        $this->setUpTestRows();
    }

    protected function tearDown()
    {
        $this->dropCollection('customer');
        parent::tearDown();
    }

    /**
     * Sets up test rows.
     */
    protected function setUpTestRows()
    {
        $collection = $this->getConnection()->getCollection('customer');
        $rows = [];
        for ($i = 1; $i <= 10; $i++) {
            $rows[] = [
                'name' => 'name' . $i,
                'status' => $i,
                'address' => 'address' . $i,
                'avatar' => [
                    'width' => 50 + $i,
                    'height' => 100 + $i,
                    'url' => 'http://some.url/' . $i,
                ],
            ];
        }
        $collection->batchInsert($rows);
    }

    // Tests :

    public function testAll()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')->all($connection);
        $this->assertEquals(10, count($rows));
    }


    public function testAllCache()
    {
        Trace::removeAll();
        $cache = static::getCache();
        $cache->flush();
        $connection = $this->getConnection();
        $connection->enableQueryCache = true;
        $connection->queryCache = $cache;
        $query = (new Query)->from('customer');
        $query->all($connection);
        $rows = $query->all($connection);
        $this->assertEquals(10, count($rows));
        $this->assertTrue(Trace::getIterator('mongodb.query')->current()['cache']);

        // end cache
        $rows = $query->notCache()->all($connection);
        $this->assertEquals(10, count($rows));
        $this->assertFalse(Trace::getIterator('mongodb.query')->current()['cache']);
        $this->assertSame(3, Trace::getIterator('mongodb.query')->current()['count']);
    }

    public function testDirectMatch()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where(['name' => 'name1'])
            ->all($connection);
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testIndexBy()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->indexBy('name')
            ->all($connection);
        $this->assertEquals(10, count($rows));
        $this->assertNotEmpty($rows['name1']);
    }

    public function testIndexByCache()
    {
        Trace::removeAll();
        $cache = static::getCache();
        $cache->flush();
        $connection = $this->getConnection();
        $connection->enableQueryCache = true;
        $connection->queryCache = $cache;
        $query = (new Query)->from('customer')
        ->indexBy('name');
        $query->all($connection);
        $rows= $query->all($connection);
        $this->assertEquals(10, count($rows));
        $this->assertNotEmpty($rows['name1']);
        $this->assertTrue(Trace::getIterator('mongodb.query')->current()['cache']);
    }

    public function testInCondition()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5']
            ])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name5', $rows[1]['name']);
    }

    public function testNotInCondition()
    {
        $connection = $this->getConnection();

        $query = new Query;
        $rows = $query->from('customer')
            ->where(['not in', 'name', ['name1', 'name5']])
            ->all($connection);
        $this->assertEquals(8, count($rows));

        $query = new Query;
        $rows = $query->from('customer')
            ->where(['not in', 'name', ['name1']])
            ->all($connection);
        $this->assertEquals(9, count($rows));
    }

    public function testOrCondition()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where(['name' => 'name1'])
            ->orWhere(['address' => 'address5'])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('address5', $rows[1]['address']);
    }

    public function testCombinedInAndCondition()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5']
            ])
            ->andWhere(['name' => 'name1'])
            ->all($connection);
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
    }

    public function testCombinedInLikeAndCondition()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where([
                'name' => ['name1', 'name5', 'name10']
            ])
            ->andWhere(['LIKE', 'name', 'me1'])
            ->andWhere(['name' => 'name10'])
            ->all($connection);
        $this->assertEquals(1, count($rows));
        $this->assertEquals('name10', $rows[0]['name']);
    }

    public function testNestedCombinedInAndCondition()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where([
                'and',
                ['name' => ['name1', 'name2', 'name3']],
                ['name' => 'name1']
            ])
            ->orWhere([
                'and',
                ['name' => ['name4', 'name5', 'name6']],
                ['name' => 'name6']
            ])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name6', $rows[1]['name']);
    }

    public function testOrder()
    {
        $connection = $this->getConnection();

        $query = new Query;
        $rows = $query->from('customer')
            ->orderBy(['name' => SORT_DESC])
            ->all($connection);
        $this->assertEquals('name9', $rows[0]['name']);

        $query = new Query;
        $rows = $query->from('customer')
            ->orderBy(['avatar.height' => SORT_DESC])
            ->all($connection);
        $this->assertEquals('name10', $rows[0]['name']);
    }

    public function testMatchPlainId()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $row = $query->from('customer')->one($connection);
        $query = new Query;
        $rows = $query->from('customer')
            ->where(['_id' => $row['_id']->__toString()])
            ->all($connection);
        $this->assertEquals(1, count($rows));
    }

    public function testRegex()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            ->where(['REGEX', 'name', '/me1/'])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name10', $rows[1]['name']);
    }

    public function testLike()
    {
        $connection = $this->getConnection();

        $query = new Query;
        $rows = $query->from('customer')
            ->where(['LIKE', 'name', 'me1'])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name10', $rows[1]['name']);

        $query = new Query;
        $rowsUppercase = $query->from('customer')
            ->where(['LIKE', 'name', 'ME1'])
            ->all($connection);
        $this->assertEquals($rows, $rowsUppercase);
    }

    public function testCompare()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['$gt', 'status', 8])
            ->all($connection);
        $this->assertEquals(2, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['>', 'status', 8])
            ->all($connection);
        $this->assertEquals(2, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['<=', 'status', 3])
            ->all($connection);
        $this->assertEquals(3, count($rows));
    }

    public function testNot()
    {
        $connection = $this->getConnection();

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'status', ['$gte' => 10]])
            ->all($connection);
        $this->assertEquals(9, count($rows));

        $query = new Query();
        $rows = $query->from('customer')
            ->where(['not', 'name', 'name1'])
            ->all($connection);
        $this->assertEquals(9, count($rows));
    }

    public function testModify()
    {
        $connection = $this->getConnection();

        $query = new Query();

        $searchName = 'name5';
        $newName = 'new name';
        $row = $query->from('customer')
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => false], $connection);
        $this->assertEquals($searchName, $row['name']);

        $searchName = 'name7';
        $newName = 'new name';
        $row = $query->from('customer')
            ->where(['name' => $searchName])
            ->modify(['$set' => ['name' => $newName]], ['new' => true], $connection);
        $this->assertEquals($newName, $row['name']);

        $row = $query->from('customer')
            ->where(['name' => 'not existing name'])
            ->modify(['$set' => ['name' => 'new name']], ['new' => false], $connection);
        $this->assertNull($row);
    }

    public function testOptions()
    {
        $connection = $this->getConnection();
        $connection->getCollection('customer')->createIndex(['status' => 1]);

        $query = new Query();
        $rows = $query->from('customer')
            ->options([
                '$min' => [
                    'status' => 9
                ],
            ])
            ->all($connection);

        $this->assertCount(2, $rows);
    }

    /**
     * @see https://github.com/yiisoft/yii2/issues/4879
     *
     * @depends testInCondition
     */
    public function testInConditionIgnoreKeys()
    {
        $connection = $this->getConnection();
        $query = new Query;
        $rows = $query->from('customer')
            /*->where([
                'name' => [
                    0 => 'name1',
                    15 => 'name5'
                ]
            ])*/
            ->where(['in', 'name', [
                10 => 'name1',
                15 => 'name5'
            ]])
            ->all($connection);
        $this->assertEquals(2, count($rows));
        $this->assertEquals('name1', $rows[0]['name']);
        $this->assertEquals('name5', $rows[1]['name']);
    }
}
