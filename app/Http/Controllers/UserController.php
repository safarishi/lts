<?php

namespace App\Http\Controllers;

use Hash;
use Input;
use Validator;
use Illuminate\Http\Request;
use App\Exceptions\ValidationException;
use LucaDegasperi\OAuth2Server\Authorizer;

class UserController extends CommonController
{

    protected $email = '';

    public function __construct(Authorizer $authorizer)
    {
        $this->middleware('oauth', ['except' => 'store']);
        // before middleware
        $this->middleware('oauth.checkClient', ['only' => 'store']);
        // before filter
        $this->beforeFilter('@storeEmail', ['only' => 'store']);
    }

    /**
     * 用户注册校验邮箱唯一性
     *
     * @throws \App\Exceptions\ValidationException
     */
    public function storeEmail()
    {
        $this->email = Input::get('email');

        $this->models['user'] = $this->dbRepository('mongodb', 'user');

        $outcome = $this->models['user']->where('email', $this->email)->first();

        if ($outcome) {
            throw new ValidationException('邮箱已被注册');
        }
    }

    /**
     * 用户注册
     *
     */
    public function store()
    {
        // validator
        $validator = Validator::make(Input::all(), [
            'email' => 'required|email',
            'password' => 'required|min:6|confirmed',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->messages()->all());
        }

        $password = Input::get('password');

        $avatarUrl = '/uploads/images/avatar/default.png';

        $insertData = [
            'password'   => Hash::make($password),
            'avatar_url' => $avatarUrl,
            'email'      => $this->email,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        $insertId = $this->models['user']->insertGetId($insertData);

        return $this->models['user']->find($insertId);
    }

    public function show($id)
    {
        echo 'todo';
    }
}