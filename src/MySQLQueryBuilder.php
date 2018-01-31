<?php
namespace abraovic\mySqlQueryBuilder;

use abraovic\mySqlQueryBuilder\Services\MySQLQuery;
use abraovic\mySqlQueryBuilder\Services\MySQLTransaction;
use abraovic\mySqlQueryBuilder\Services\PDOSlave;
use abraovic\mySqlQueryBuilder\Handlers\PDOWrapper;

/**
 * @method setDbh($db)
 *
 * @method select($table, $columns, $where = [], $order = [], $limit = [], $special = "", $resultType = MySQLQuery::SINGLE_ROW)
 * @method update($table, $columns, $where, $custom = "")
 * @method insert($table, $columns, $values, $multi = false, $updateOnDUplicate = "", $ignore = " ")
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

    /** @var MySQLQuery */
    private $query;
    /** @var MySQLTransaction */
    private $transaction;

    function __construct(PDOWrapper &$masterDbh, PDOSlave &$slaveDbh)
    {
        $this->master = $masterDbh;
        $this->slave = $slaveDbh;

        $this->query = new MySQLQuery($this->master, $this->slave);
        $this->transaction = new MySQLTransaction($this->master, $this->slave);
    }

    public function __call($name, $arguments)
    {
        // these are queries so MySQLQuery is init
        return call_user_func_array(array($this->query, $name), $arguments);
    }

    public function transaction($callable)
    {
        $this->transaction->transaction($callable);
    }

    /**
     * @return MySQLQueryBuilder
     */
    public function slave()
    {
        // slave is implemented like this to return proper class
        // which then supports auto complete - little hack hihi
        $this->query->slave();
        return $this;
    }
}