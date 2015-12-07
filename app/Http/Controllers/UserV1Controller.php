<?php

namespace App\Http\Controllers;

use LucaDegasperi\OAuth2Server\Authorizer;

class UserV1Controller extends UserController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('validation');
    }

    private static $_validate = [
        'store' => [
            'email'    => 'required|email|unique:user',
            'password' => 'required|min:6|confirmed',
        ],
    ];
}