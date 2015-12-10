<?php

namespace App\Exceptions;

class AuthorizationEntryException extends ApiException
{
    public function __construct($msg)
    {
        parent::__construct($msg, 15003);
        $this->httpStatusCode = 500;
        $this->errorType = 'third_party_error';
    }
}