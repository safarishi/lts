<?php

namespace App\Exceptions;

class DuplicateOperateException extends ApiException
{
    public function __construct($msg)
    {
        parent::__construct($msg, 14003);
        $this->httpStatusCode = 400;
        $this->errorType = 'invalid_operation';
    }
}