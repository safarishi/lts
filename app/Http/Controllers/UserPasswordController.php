<?php

namespace App\Http\Controllers;

use LucaDegasperi\OAuth2Server\Authorizer;

class UserPasswordController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('oauth');
        $this->middleware('validation');
    }

    private static $_validate = [
        // 'modify'
    ];

    public function modify()
    {

    }

}