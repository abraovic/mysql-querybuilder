<?php
namespace abraovic\mySqlQueryBuilder\Exceptions;

use \Exception;

class QueryFailedException extends \Exception
{
    public function __construct($message = "Internal Server Error", $code = 500, Exception $previous = null)
    {
        // make sure everything is assigned properly
        parent::__construct($message, $code, $previous);
    }
} 