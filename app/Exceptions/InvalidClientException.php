<?php

namespace App\Exceptions;

class InvalidClientException extends ApiException
{
    public function __construct($msg)
    {
        parent::__construct($msg, 14007);
        $this->httpStatusCode = 400;
        $this->errorType = 'invalid_client';
    }
}