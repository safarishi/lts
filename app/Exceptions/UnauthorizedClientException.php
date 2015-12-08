<?php

namespace App\Exceptions;

use Lang;

class UnauthorizedClientException extends ApiException
{
    public function __construct()
    {
        parent::__construct(Lang::get('oauth.unauthorized_client'), 14005);
        $this->httpStatusCode = 401;
        $this->errorType = 'unauthorized_client';
    }
}