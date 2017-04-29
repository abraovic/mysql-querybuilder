<?php
namespace abraovic\mySqlQueryBuilder\Handlers;


use abraovic\mySqlQueryBuilder\Exceptions\QueryFailedException;
use \PDO;

class PDOWrapper
{
    // System will resign if in one request it fails to
    // reconnect in a max number of tries.
    const MAX_RETRY_NO = 5;

    // Sncreases every time we try to reconnect to mysql
    // used to prevent infinite loop. Retry no will reset
    // upon a successful reconnect.
    private static $retryNo = 0;

    private $isConnected = false;
    private $isExecuted;
    private $dbHandler = null;
    private $maxRetryNo = self::MAX_RETRY_NO;

    // PDO data
    private $dsn;
    private $username;
    private $passwd;
    private $options;

    function __construct($dsn, $username = "", $passwd = "", $options = [])
    {
        // set PDO data so it can be used to reconnect if needed
        $this->dsn = $dsn;
        $this->username = $username;
        $this->passwd = $passwd;
        $this->options = $options;

        // connect for the first time
        $this->connect();
    }

    public function __call($method, $arguments)
    {
        $this->isExecuted = false;

        do {
            try {
                if (is_callable([$this->dbHandler, $method])) {
                    return call_user_func_array(array($this->dbHandler, $method), $arguments);
                } else {
                    throw new QueryFailedException('Undefined PDO method called');
                }
            } catch (\PDOException $e) {
                $eMsg = $e->getMessage();
                // Now, if server has gone away system will try to reconnect. In any other case it will simply
                // throw received error.

                if (strpos($eMsg, 'server has gone away') !== false) {
                    $this->reconnect();
                    $this->isExecuted = false;
                } else {
                    throw new QueryFailedException($eMsg);
                }
            }
        } while(!$this->isExecuted);
    }

    /**
     * @return int
     */
    public function getMaxRetryNo()
    {
        return $this->maxRetryNo;
    }

    /**
     * @param int $maxRetryNo
     */
    public function setMaxRetryNo($maxRetryNo)
    {
        $this->maxRetryNo = $maxRetryNo;
    }

    /**
     * Makes connection to database using PDO.
     */
    private function connect()
    {
        try {
            $this->dbHandler = new PDO($this->dsn, $this->username, $this->passwd, $this->options);
            // I need this to get all errors as exceptions to I can catch them
            $this->dbHandler->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
            $this->isConnected = true;
        } catch (\PDOException $e) {
            throw new QueryFailedException('Trying to connect to DB bas failed');
        }
    }

    /**
     * In case that system needs to be reconnected it fill perform that action. It will also
     * take care of number of retry to prevent infinite loop.
     */
    private function reconnect()
    {
        self::$retryNo++;

        if ($this->getMaxRetryNo() < self::$retryNo) {
            throw new QueryFailedException('Max no of reconnect limit has reached.');
        }

        $this->dbHandler = null;
        $this->connect();
    }
}