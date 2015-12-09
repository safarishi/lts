<?php

namespace App\Http\Controllers;

use DB;
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

    public function show()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $fields = ['avatar_url', 'email', 'gender', 'display_name', 'company'];

        return DB::collection('user')->find($uid, $fields);
    }
}