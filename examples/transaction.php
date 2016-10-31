<?php
require_once '../vendor/autoload.php';

use abraovic\mySqlQueryBuilder\MySQLQueryBuilder;
use abraovic\mySqlQueryBuilder\Services\MySQLQuery;

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

$name = 'test2';
$qb->transaction(function (MySQLQuery $qb) use ($name){
    $qb->insert('abc', ['name'], ['name' => array('value' => $name)]);
    $qb->insert('def', ['name'], ['name' => array('value' => $name)]);
});