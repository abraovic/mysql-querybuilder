<?php
namespace abraovic\mySqlQueryBuilder\Services;

use abraovic\mySqlQueryBuilder\Handlers\PDOWrapper;

class PDOSlave extends PDOWrapper
{
    public $validSlave = 0;

    function __construct($host, $database, $username = "", $passwd = "", $options = [])
    {
        /**
         * Select random slave host and use it to generate dsn string
         */
        if (!$host) {
            // if there is no added slaves in configuration then just return null
            return null;
        }

        $hosts = explode(",", $host);
        $host = $hosts[array_rand($hosts, 1)];
        $host = trim($host);
        $dsn = "mysql:dbname=" . $database . ";host=" . $host . ";charset=utf8mb4";

        parent::__construct($dsn, $username, $passwd, $options);

        $this->validSlave = 1;
    }
}