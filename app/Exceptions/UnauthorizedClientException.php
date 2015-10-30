<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class UnauthorizedClientException extends HttpException
{
    public function __construct($msg)
    {
        parent::__construct(401, $msg);
    }
}