<?php
require_once '../vendor/autoload.php';

use abraovic\mySqlQueryBuilder\MySQLQueryBuilder;

$database = "test";
$host = "localhost";
$username = "root";
$passwd = "abc";

$dsn = "mysql:dbname=" . $database . ";host=" . $host . ";charset=utf8mb4";
$pdo = new \PDO($dsn, $username, $passwd);

// create new qb with no slaves
$qb = new MySQLQueryBuilder(
    $pdo,
    new \abraovic\mySqlQueryBuilder\Services\PDOSlave(null, $database, $username, $passwd, [])
);


var_dump($qb->select(
    'abc',
    ['id', 'name'],
    [],
    [],
    [],
    '',
    \abraovic\mySqlQueryBuilder\Services\QueryBuilder::MULTI_ROWS
));