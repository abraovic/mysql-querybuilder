<?php
namespace abraovic\mySqlQueryBuilder;

use abraovic\mySqlQueryBuilder\Services\MySQLQuery;
use abraovic\mySqlQueryBuilder\Services\MySQLTransaction;
use abraovic\mySqlQueryBuilder\Services\PDOSlave;

/**
 * @method setDbh($db)
 * @method slave()
 *
 * @method select($table, $columns, $where = [], $order = [], $limit = [], $special = "", $resultType = MySQLQuery::SINGLE_ROW)
 * @method update($table, $columns, $where, $custom = "")
 * @method insert($table, $columns, $values, $multi = false, $ignore = " ")
 * @method delete($table, $where)
 * @method raw($query)
 *
 * @method startTransaction()
 * @method commit()
 *
 * @method affectedRowCount()
 * @method getLastInsertId()
 */

class MySQLQueryBuilder
{
    private $master;
    private $slave;

    function __construct(\PDO $masterDbh, PDOSlave $slaveDbh)
    {
        $this->master = $masterDbh;
        $this->slave = $slaveDbh;
    }

    public function __call($name, $arguments)
    {
        // these are queries so MySQLQuery is init
        $queryBuilder = new MySQLQuery($this->master, $this->slave);
        return call_user_func_array(array($queryBuilder, $name), $arguments);
    }

    public function transaction($callable)
    {
        $queryBuilder = new MySQLTransaction($this->master, $this->slave);
        $queryBuilder->transaction($callable);
    }
}