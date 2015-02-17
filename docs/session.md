Example:

```php
$config = [
    'connection' => new \rock\mongodb\Connection(),
    'sessionCollection' => 'sessions'
];
$session = new \rock\mongodb\Session($config);
$session ->add('name', 'Tom');

echo $session->get('name'); // result: Tom
```