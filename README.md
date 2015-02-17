ORM for MongoDB.
=======================

This extension requires MongoDB PHP Extension version 1.5.0 or higher.

Independent fork by [Yii2 MongoDB](https://github.com/yiisoft/yii2/tree/master/extensions/mongodb).

[Rock MongoDB on Packagist](https://packagist.org/packages/romeOz/rock-mongodb)

Features
-------------------
 
 * Query Builder/DBAL/DAO: Querying the database using a simple abstraction layer
 * Active Record: The Active Record ORM, retrieving and manipulating records, and defining relations
 * Support [MongoGridFS](http://docs.mongodb.org/manual/core/gridfs/)
 * Behaviors (SluggableBehavior, TimestampBehavior,...)
 * Stores session data in a collection
 * **Validation and Sanitization rules for AR (Model)**
 * **Query Caching**
 * **Data Providers**
 * **MongoDB as key-value storage (Cache handler)**
 * **Module for [Rock Framework](https://github.com/romeOz/rock)**
 
> Bolded features are different from [Yii2 MongoDB](https://github.com/yiisoft/yii2/tree/master/extensions/mongodb).

Installation
-------------------

From the Command Line:

`composer require romeoz/rock-mongodb:*`

In your composer.json:

```json
{
    "require": {
        "romeoz/rock-mongodb": "*"
    }
}
```

Quick Start
-------------------

####Query Builder

```php
$rows = (new \rock\mongodb\Query)
    ->from('users')
    ->all();
```

####Active Record

```php
// find
$users = Users::find()
    ->where(['status' => Users::STATUS_ACTIVE])
    ->orderBy('id')
    ->all();
    
// insert
$users = new Users();
$users ->name = 'Tom';
$users ->save();    
```

Documentation
-------------------

* [Basic](https://github.com/yiisoft/yii2/blob/master/extensions/mongodb/README.md): Connecting to a database, basic queries, query builder, and Active Record
* [Data Providers](https://github.com/romeOz/rock-mongodb/blob/master/docs/data-provider.md)
* [Session](https://github.com/romeOz/rock-mongodb/blob/master/docs/session.md)
* [Cache](https://github.com/romeOz/rock-mongodb/blob/master/docs/cache.md)

Requirements
-------------------

 * **PHP 5.4+**
 * [Rock Cache](https://github.com/romeOz/rock-cache) **(optional)**. Should be installed: `composer require romeoz/rock-cache:*`
 * [Rock Validate](https://github.com/romeOz/rock-validate) **(optional)**. Should be installed: `composer require romeoz/rock-validate:*`
 * [Rock Sanitize](https://github.com/romeOz/rock-sanitize) **(optional)**. Should be installed: `composer require romeoz/rock-sanitize:*`
 * [Rock Behaviors](https://github.com/romeOz/rock-behaviors) **(optional)**. Should be installed: `composer require romeoz/rock-behaviors:*`
 * [Rock Session](https://github.com/romeOz/rock-behaviors) **(optional)**. Should be installed: `composer require romeoz/rock-session:*`

License
-------------------

The Object Relational Mapping (ORM) for MongoDB is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).