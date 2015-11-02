<?php

namespace App\Http\Controllers;

use DB;
use Hash;
use Input;
use Response;
use Validator;
use Illuminate\Http\Request;
use App\Exceptions\ValidationException;
use LucaDegasperi\OAuth2Server\Authorizer;

class UserController extends CommonController
{

    protected $email = '';

    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
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

    public function show()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        return $this->dbRepository('mongodb', 'user')
            ->select('avatar_url', 'email', 'gender', 'display_name', 'company')
            ->find($uid);
    }

    public function logout()
    {
        $oauthAccessToken = DB::table('oauth_access_tokens');

        $oauthAccessToken->where('id', $this->accessToken)->delete();

        return Response::make('', 204);
    }

    public function myComment()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $this->models['article_comment'] = $this->dbRepository('mongodb', 'article_comment');
        $commentModel = $this->models['article_comment']
            ->where('user._id', $uid)
            ->orderBy('created_at', 'desc');

        MultiplexController::addPagination($commentModel);

        $data = $commentModel->get();

        return $this->handleCommentResponse($data);
    }

}