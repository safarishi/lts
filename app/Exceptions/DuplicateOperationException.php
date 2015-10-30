<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class DuplicateOperationException extends HttpException
{
    public function __construct($msg)
    {
        if (is_array($msg)) {
            $msg = implode($msg, ' ');
        }
        parent::__construct(400, $msg);
    }
}