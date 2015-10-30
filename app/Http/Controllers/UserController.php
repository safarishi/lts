<?php

namespace App\Http\Controllers;

use Input;
use LucaDegasperi\OAuth2Server\Authorizer;

class UserController extends CommonController
{

    public function __construct(Authorizer $authorizer)
    {
        $this->middleware('oauth', ['except' => 'store']);
        $this->middleware('oauth.checkClient', ['only' => 'store']);
    }

    public function store()
    {
        echo 'store todo';
    }

    public function show($id)
    {
        echo 'todo';
    }
}