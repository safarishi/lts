<?php

namespace App\Exceptions;

class UnauthorizedClientException extends ApiException
{
    public function __construct($msg)
    {
        parent::__construct($msg, 14005);
        $this->httpStatusCode = 401;
        $this->errorType = 'invalid_client';
    }
}