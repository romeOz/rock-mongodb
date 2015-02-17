Active data provider
----------------------

ActiveDataProvider provides data by performing MongoDB queries using `rock\mongodb\Query` and `rock\mongodb\ActiveQuery`.


The following is an example of using it to provide ActiveRecord instances:

```php
$provider = new \rock\db\ActiveDataProvider([
    'query' => Articles::find()->orderBy('id ASC'),
    'pagination' => [
        'limit' => 20,
        'sort' => SORT_DESC,
        'pageLimit' => 5,
        'pageCurrent' => (int)$_GET['page']
    ],
]);

$provider->get(); // returns list items in the current page
$provider->getPagination(); // returns data pagination
```

And the following example shows how to use ActiveDataProvider without ActiveRecord:

```php
$query = new \rock\mongodb\Query;
$provider = new ActiveDataProvider([
     'query' => $query->from('articles'),
     'pagination' => [
         'limit' => 20,
         'sort' => SORT_DESC,
         'pageLimit' => 5,
         'pageCurrent' => (int)$_GET['page'],
     ],
]);

$provider->get(); // returns list items in the current page
$provider->getPagination(); // returns data pagination
```

Array data provider
-------------------

ArrayDataProvider implements a data provider based on a data array.


```php
$query = (new \rock\mongodb\Query())->from('articles');
$provider = new ActiveDataProvider([
     'array' => $query->all(),
     'pagination' => [
         'limit' => 20,
         'sort' => SORT_DESC,
         'pageLimit' => 5,
         'pageCurrent' => (int)$_GET['page'],
     ],
]);

$provider->get(); // returns list items in the current page
$provider->getPagination(); // returns data pagination
```