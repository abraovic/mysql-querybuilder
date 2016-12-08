<?php
namespace abraovic\mySqlQueryBuilder\Services;

use abraovic\mySqlQueryBuilder\Exceptions\QueryFailedException;

class QueryBuilder
{
    const SINGLE_ROW = 1;
    const MULTI_ROWS = 2;
    const CONN_MASTER = 1000;
    const CONN_SLAVE = 1001;

    ## LOGIC
    const _OR = "OR";
    const _AND = "AND";
    const _INCR = "INCR";
    const _DECR = "DECR";
    const _DESC = "DESC";
    const _ASC = "ASC";
    const _IGNORE = " IGNORE ";
    const _IN = 1;
    const _NOT_IN = 2;

    /** @var \PDO $masterDbh */
    private $masterDbh;
    /** @var \PDO $slaveDbh */
    private $slaveDbh;
    private $slaveOn = 0;

    protected $rowCount;
    protected $lastInsertId;
    protected $dbh;
    protected $oldDbh;

    public $query = "";

    function __construct(\PDO &$masterDbh, PDOSlave &$slaveDbh)
    {
        $this->masterDbh = $masterDbh;
        // if there is no slave, use master connection
        $this->slaveDbh = ($slaveDbh->validSlave) ? $slaveDbh : $masterDbh;
        $this->dbh = $this->masterDbh;
        $this->oldDbh = $this->masterDbh;
    }

    /**
     * Manually select database on which you would like to perform operations for
     * current jos
     *
     * @deprecated
     * @param $db -> CONN_MASTER|CONN_SLAVE
     * @throws QueryFailedException
     */
    public function setDbh($db)
    {
        switch ($db) {
            case self::CONN_MASTER:
                $this->dbh = $this->masterDbh;
                break;
            case self::CONN_SLAVE:
                $this->dbh = $this->slaveDbh;
                break;
            default:
                throw new QueryFailedException("Invalid db selector use CONN_MASTER|CONN_SLAVE");
        }
    }

    /**
     * Executes query on slave connection
     */
    public function slave()
    {
        $this->oldDbh = $this->dbh;
        $this->dbh = $this->slaveDbh;
        $this->slaveOn = 1;
        return $this;
    }

    /**
     * Gets a number of affected rows by last executed query
     * @return int
     */
    public function affectedRowCount()
    {
        return $this->rowCount;
    }

    /**
     * Gets a last insert id
     * @return int
     */
    public function getLastInsertId()
    {
        return $this->lastInsertId;
    }

    protected function execute($query, $where = null, $columns = null)
    {
        $qh = $this->dbh->prepare($query);
        if ($where) {
            foreach ($where as $key => $item) {
                if (isset($item['in'])) {
                    if ($item['in'] == self::_IN || $item['in'] == self::_NOT_IN) {
                        continue;
                    }
                }

                $binder = $key;
                if (isset($item['binder'])) {
                    $binder = $item['binder'];
                }

                if (is_int($item['value'])) {
                    $qh->bindValue(":".$binder, (int)$item['value'], \PDO::PARAM_INT);
                } else {
                    $qh->bindValue(":".$binder, $item['value']);
                }
            }
        }

        if ($columns) {
            foreach ($columns as $column => $record) {
                if (!is_string($record)) {
                    $record = (string)$record;
                }
                if ($record != self::_INCR && $record != self::_DECR) {
                    $qh->bindValue(":" . $column, $record);
                }
            }
        }

        $this->query = $query;

        try {
            $qh->execute();
            $this->rowCount = $qh->rowCount();
        } catch (\PDOException $e) {
            throw new QueryFailedException($query . " - " . $e->getMessage());
        }

        return $qh;
    }

    protected function buildQuery(
        $query,
        $table,
        $columns = null,
        $where = null,
        $values = null
    ) {
        $query = str_replace(":table", $table, $query);
        if ($columns) {
            $query = str_replace(":columns", $columns, $query);
        }
        if ($where) {
            $whereString = "";
            $counter = 0;
            foreach ($where as $key => $item) {
                $binder = $key;
                if (isset($item['binder'])) {
                    $binder = $item['binder'];
                }

                if (isset($item['r_key'])) {
                    $key = $item['r_key'];
                }

                if (isset($item['group_open'])) {
                    $whereString .= "(";
                }
                if ($counter < count($where) - 1) {
                    if (isset($item['logic'])) {
                        switch ($item['logic']) {
                            case self::_AND:
                                $whereString .= " AND ";
                                break;
                            case self::_OR:
                                $whereString .= " OR ";
                                break;
                        }
                    }
                }
                if (isset($item['in'])) {
                    if ($item['in'] == self::_IN) {
                        $whereString .= $key . " IN (" . $item['value'] . ")";
                    } else if ($item['in'] == self::_NOT_IN){
                        $whereString .= $key . " NOT IN (" . $item['value'] . ")";
                    } else {
                        $whereString .= $key . " " . (isset($item['operator']) ? $item['operator'] : "=") . " :" . $binder;
                    }
                } else if (isset($item['like'])) {
                    $whereString .= $key . " LIKE :" . $binder;
                } else {
                    $whereString .= $key . " " . (isset($item['operator']) ? $item['operator'] : "=") . " :" . $binder;
                }

                if (isset($item['group_close'])) {
                    $whereString .= ")";
                }
            }
            $query = str_replace(":where", $whereString, $query);
        }
        if ($values) {
            $query = str_replace(":values", $values, $query);
        }
        return $query;
    }

    protected function setOldDbh()
    {
        if ($this->slaveOn) {
            $this->slaveOn = 0;
            $this->dbh = $this->masterDbh;
        }
    }
}