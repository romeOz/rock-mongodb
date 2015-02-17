```php
$config = [
    'storage' => \rock\mongodb\Connection
    'cacheCollection' => 'cache'
];
$mongoCache = new \rock\mongodb\Cache($config);

$tags = ['tag_1', 'tag_2'];
$value = ['foo', 'bar'];
$expire = 0; // If use expire "0", then time to live infinitely
$mongoCache->set('key_1', $value, $expire, $tags);

$mongoCache->get('key_1'); // result: ['foo', 'bar'];

$mongoCache->flush(); // Invalidate all items in the cache
```