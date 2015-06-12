Active data provider
----------------------

ActiveDataProvider provides data by performing MongoDB queries using `rock\mongodb\Query` and `rock\mongodb\ActiveQuery`.


The following is an example of using it to provide ActiveRecord instances:

```php
$provider = new \rock\db\common\ActiveDataProvider([
    'query' => Articles::find()->orderBy('id ASC'),
    'pagination' => [
        'limit' => 20,
        'sort' => SORT_DESC,
        'pageLimit' => 5,
        'page' => (int)$_GET['page']
    ],
]);

$provider->getModels(); // returns list items in the current page
$provider->getPagination(); // returns pagination provider
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
         'page' => (int)$_GET['page'],
     ],
]);

$provider->getModels(); // returns list items in the current page
$provider->getPagination(); // returns pagination provider
```