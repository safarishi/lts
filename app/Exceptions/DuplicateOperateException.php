<?php

namespace App\Exceptions;

class DuplicateOperateException extends ApiException
{
    public function __construct($msg)
    {
        parent::__construct($msg, 14003);
        $this->httpStatusCode = 409;
        $this->errorType = 'conflict_operation';
    }
}