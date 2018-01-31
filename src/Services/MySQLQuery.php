<?php
namespace abraovic\mySqlQueryBuilder\Services;

use abraovic\mySqlQueryBuilder\Handlers\StringHandler;
use abraovic\mySqlQueryBuilder\Exceptions\QueryFailedException;

class MySQLQuery extends QueryBuilder
{


    /**
     * Performs select query
     * @param $table -> name of table from which you want to select
     * @param $columns -> php array of columns names that you want to select
     * @param $where -> php array
     *                      There are few versions depend on where clause
     *                      1. null -> if you do not have where but you want to select all
     *                      2. array(
     *                          "column_name" =>
     *                              array(
     *                                  "value" => [actual_value],
     *                                  (optional)"logic" => [AND|OR],
     *                                  (optional)"in" => [0|1]
     *                              ),
     *                              ...
     *                          )
     *                         In case you have "in" than actual_value needs to be comma separated string
     *                         First column in where must never have logic key
     * @param $order -> php array
     *                      array("column" => [column_name], "type" => [DESC|ASC])
     * @param $limit -> php array
     *                      array("limit" => [0|...], "offset" => [0|...])
     * @param $special -> string -> this is a simple string that you can put at the end of the query
     *                      eg HAVING, ...
     * @return array $result
     * @return \stdClass $result
     *         It depends if there are multiple rows or a single
     * @throws QueryFailedException
     */
    public function select(
        $table,
        $columns,
        $where = [],
        $order = [],
        $limit = [],
        $special = "",
        $resultType = self::SINGLE_ROW
    )
    {
        $query = "SELECT :columns FROM :table";
        if (!empty($where)) {
            $query .= " WHERE :where";
        } else {
            $where = null;
        }
        if ($special) {
            $query .= " " . $special;
        }
        if (!empty($order)) {
            $query .= " ORDER BY ";
            if (isset($order['column'])) {
                $query .= $order['column'] . " " . $order['type'];
            } else {
                $ordCnt = 0;
                foreach ($order as $rule) {
                    $query .= $rule['column'] . " " . $rule['type'];
                    if ($ordCnt < count($order) - 1) {
                        $query .= ", ";
                    }
                    $ordCnt++;
                }
            }
        }
        if (!empty($limit)) {
            $query .= " LIMIT " . $limit['offset'] . ", " . $limit['limit'];
        }

        $query = $this->buildQuery($query, $table, StringHandler::array2CSString($columns), $where);
        $result = $this->execute($query, $where)->fetchAll(\PDO::FETCH_OBJ);
        $this->setOldDbh();

        switch ($resultType) {
            case self::SINGLE_ROW:
            default:
                if (!empty($result)) {
                    return $result[0];
                }
                return null;
                break;
            case self::MULTI_ROWS:
                return $result;
                break;
        }
    }

    /**
     * Performs delete query
     * @param $table -> name of table from which you want to delete
     * @param $where -> php array
     *                      There are few versions depend on where clause
     *                      1. null -> if you do not have where but you want to delete all
     *                      2. array(
     *                          "column_name" =>
     *                              array(
     *                                  "value" => [actual_value],
     *                                  (optional)"logic" => [AND|OR],
     *                                  (optional)"in" => [0|1]
     *                              ),
     *                              ...
     *                          )
     *                         In case you have "in" than actual_value needs to be comma separated string
     *                         First column in where must never have logic key
     * @return bool
     * @throws QueryFailedException
     */
    public function delete($table, $where)
    {
        $query = "DELETE FROM :table WHERE :where";
        $query = $this->buildQuery($query, $table, null, $where);
        $result = $this->execute($query, $where);
        $this->setOldDbh();
        return ($result) ? true : false;
    }

    /**
     * Performs insert query
     * @param $table -> name of table into which you want to insert
     * @param $columns -> php array of columns names that you want to insert
     * @param $values -> php array
     *                      There are few versions depend on where clause
     *                      1. array(
     *                          "column_name" =>
     *                              array(
     *                                  "value" => [actual_value]
     *                              ),
     *                              ...
     *                          )
     *                      2. array(
     *                              array('value_for_column_one', ...),
     *                              ...
     *                         )
     *                         Number of items in an inner array must be same as number of columns
     * @param $multi -> Determines if you are inserting multiple rows in a single query
     * @param $ignore -> executes insert ignore
     * @return bool
     * @throws QueryFailedException
     */
    public function insert(
        $table,
        $columns,
        $values,
        $multi = false,
        $updateOnDUplicate = "",
        $ignore = " "
    )
    {
        $query = "INSERT" . $ignore . "INTO :table (:columns) VALUES (:values)";
        if ($updateOnDUplicate) {
            $query .= " ON DUPLICATE KEY UPDATE " . $updateOnDUplicate;
        }

        $insertFields = "";
        $counter = 0;

        if ($multi) {
            $query = "INSERT INTO :table (:columns) VALUES :values";

            foreach ($values as $items) {
                $insertFields .= "(";
                $innerCounter = 0;
                foreach ($items as $item) {
                    $insertFields .= "'" . $item . "'";
                    if ($innerCounter < count($items) - 1) {
                        $insertFields .= ", ";
                    }
                    $innerCounter++;
                }
                $insertFields .= ")";

                if ($counter < count($values) - 1) {
                    $insertFields .= ", ";
                }
                $counter++;
            }

            $values = null;
        } else {
            foreach ($columns as $record) {
                $insertFields .= ":" . $record;
                if ($counter < count($columns) - 1) {
                    $insertFields .= ", ";
                }
                $counter++;
            }
        }

        $query = $this->buildQuery(
            $query,
            $table,
            StringHandler::array2CSString($columns),
            null,
            $insertFields
        );

        $result = $this->execute($query, $values);
        $this->lastInsertId = $this->dbh->lastInsertId();
        $this->setOldDbh();
        return ($result) ? true : false;
    }

    /**
     * Performs update query
     * @param $table -> name of table into which you want to update
     * @param $columns -> php array of columns names that you want to update
     *                     array("colum_name" => [value|INCR|DECR], ...)
     * @param $where -> php array
     *                      There are few versions depend on where clause
     *                      1. null -> if you do not have where but you want to select all
     *                      2. array(
     *                          "column_name" =>
     *                              array(
     *                                  "value" => [actual_value],
     *                                  (optional)"logic" => [AND|OR],
     *                                  (optional)"in" => [0|1]
     *                              ),
     *                              ...
     *                          )
     *                         In case you have "in" than actual_value needs to be comma separated string
     *                         First column in where must never have logic key
     * @param $custom -> custom string as part of where, do not forged add logic operator before string
     * @return bool
     * @throws QueryFailedException
     */
    public function update(
        $table,
        $columns,
        $where,
        $custom = ""
    )
    {
        $query = "UPDATE :table SET :columns WHERE :where";

        $updateFields = "";
        $counter = 0;
        foreach ($columns as $column => $record) {
            if (!is_string($record)) {
                $record = (string)$record;
            }
            if ($record != self::_INCR && $record != self::_DECR) {
                $updateFields .= $column . " = :" . $column;
            } else {
                $updateFields .= $column . " = " . $column . (($record == self::_INCR) ? "+" : "-") . "1";
            }
            if ($counter < count($columns) - 1) {
                $updateFields .= ", ";
            }
            $counter++;
        }

        $query = $this->buildQuery($query, $table, $updateFields, $where);
        if ($custom) {
            $query .= " " . $custom;
        }
        $result = $this->execute($query, $where, $columns);
        $this->setOldDbh();

        return ($result) ? true : false;
    }

    /**
     * Performs a raw query
     * @param $query
     * @return array $result
     * @return \stdClass $result
     *         It depends if there are multiple rows or a single
     * @return bool
     * @throws QueryFailedException
     */
    public function raw($query)
    {
        return $this->execute($query);
    }

    /**
     * Performs a START TRANSACTION query
     * @return bool
     * @throws QueryFailedException
     */
    public function startTransaction()
    {
        $query = "START TRANSACTION";
        return (bool)$this->execute($query);
    }

    /**
     * Performs a COMMIT query
     * @return bool
     * @throws QueryFailedException
     */
    public function commit()
    {
        $query = "COMMIT";
        return (bool)$this->execute($query);
    }
} 