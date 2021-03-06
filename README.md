ODM for MongoDB
=======================

This extension requires MongoDB PHP Extension version 1.5.0 or higher.

Standalone fork by [Yii2 MongoDB 2.0.4](https://github.com/yiisoft/yii2-mongodb).

[![Latest Stable Version](https://poser.pugx.org/romeOz/rock-mongodb/v/stable.svg)](https://packagist.org/packages/romeOz/rock-mongodb)
[![Total Downloads](https://poser.pugx.org/romeOz/rock-mongodb/downloads.svg)](https://packagist.org/packages/romeOz/rock-mongodb)
[![Build Status](https://travis-ci.org/romeOz/rock-mongodb.svg?branch=master)](https://travis-ci.org/romeOz/rock-mongodb)
[![Coverage Status](https://coveralls.io/repos/romeOz/rock-mongodb/badge.svg?branch=master)](https://coveralls.io/r/romeOz/rock-mongodb?branch=master)
[![License](https://poser.pugx.org/romeOz/rock-mongodb/license.svg)](https://packagist.org/packages/romeOz/rock-mongodb)

Features
-------------------
 
 * Query Builder/DBAL/DAO: Querying the database using a simple abstraction layer
 * Active Record: The Active Record ODM, retrieving and manipulating records, and defining relations
 * Support [MongoGridFS](http://docs.mongodb.org/manual/core/gridfs/)
 * Behaviors (SluggableBehavior, TimestampBehavior,...)
 * Data Provider
 * **Validation and Sanitization rules for AR (Model)**
 * **Caching queries** 
 * **Standalone module/component for [Rock Framework](https://github.com/romeOz/rock)**
 
> Bolded features are different from [Yii2 MongoDB](https://github.com/yiisoft/yii2-mongodb).

Installation
-------------------

From the Command Line:

```
composer require romeoz/rock-mongodb
```

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
$users->name = 'Tom';
$users->save();    
```

Documentation
-------------------

* [Basic](https://github.com/yiisoft/yii2/blob/master/extensions/mongodb/README.md): Connecting to a database, basic queries, query builder, and Active Record
* [Data Providers](https://github.com/romeOz/rock-mongodb/blob/master/docs/data-provider.md)

Requirements
-------------------

 * **PHP 5.4+** 
 * For validation rules a model required [Rock Validate](https://github.com/romeOz/rock-validate): `composer require romeoz/rock-validate`
 * For sanitization rules a model required [Rock Sanitize](https://github.com/romeOz/rock-sanitize): `composer require romeoz/rock-sanitize`
 * For using behaviors a model required [Rock Behaviors](https://github.com/romeOz/rock-behaviors): `composer require romeoz/rock-behaviors`
 * For using Data Provider required [Rock Data Provider](https://github.com/romeOz/rock-dataprovider/): `composer require romeoz/rock-dataprovider`
 * For caching queries required [Rock Cache](https://github.com/romeOz/rock-behaviors): `composer require romeoz/rock-cache`

>All unbolded dependencies is optional

License
-------------------

The Object Document Mapping (ODM) for MongoDB is open-sourced software licensed under the [MIT license](http://opensource.org/licenses/MIT).