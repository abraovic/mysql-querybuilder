<?php
namespace abraovic\mySqlQueryBuilder\Services;

use abraovic\mySqlQueryBuilder\Exceptions\QueryFailedException;

class MySQLTransaction extends MySQLQuery
{
    /**
     * Execute queries in a single transaction
     * @param $callable -> callable method with MySQLQuery argument
     * @throws QueryFailedException
     */
    public function transaction($callable)
    {
        if (is_callable($callable)) {
            $this->startTransaction();
            $callable($this);
            $this->commit();
        } else {
            throw new QueryFailedException('Method not callable');
        }
    }
}