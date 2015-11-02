<?php

namespace App\Http\Controllers;

use DB;
use Hash;
use Input;
use Response;
use App\User;
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

    public function myStar()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $user = $this->dbRepository('mongodb', 'user')->find($uid);

        $articleIds = array();
        if (array_key_exists('starred_articles', $user)) {
            $articleIds = $user['starred_articles'];
        } else {
            return [];
        }

        $articleModel = $this->article()
            ->whereIn('article_id', $articleIds);

        MultiplexController::addPagination($articleModel);

        return $articleModel->get();
    }

    public function myInformation()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $model = $this->dbRepository('mongodb', 'information')
            ->where('content.comment.user._id', $uid)
            ->orderBy('created_at', 'desc');

        // 增加数据分页
        MultiplexController::addPagination($model);

        return $model->get();
    }

    /**
     * 修改用户信息的时候校验邮箱唯一性
     *
     * @param  string $uid 用户id
     * @return void
     *
     * @throws \App\Exceptions\ValidationException
     */
    protected function validateEmail($uid)
    {
        $outcome = $this->dbRepository('mongodb', 'user')
            ->where('_id', '<>', $uid)
            ->where('email', Input::get('email'))
            ->first();

        if ($outcome) {
            throw new ValidationException('邮箱已被占用');
        }
    }

    public function modify()
    {
        $uid = $this->authorizer->getResourceOwnerId();
        // validator
        $validator = Validator::make(Input::all(), [
            'email'  => 'email',
            'gender' => 'in:男,女',
        ]);
        if ($validator->fails()) {
            throw new ValidationException($validator->messages()->all());
        }

        if (Input::has('email')) {
            $this->validateEmail($uid);
        }

        $user = User::find($uid);

        $allowedFields = ['avatar_url', 'display_name', 'gender', 'email', 'company'];

        array_walk($allowedFields, function($item) use ($user) {
            $v = Input::get($item);
            if ($v && $item !== 'avatar_url') {
                $user->$item = $v;
            }
            // if (Input::hasFile('avatar_url')) {
            //     $user->avatar_url = UserAvatarApiController::uploadAvatar($this->uid);
            // }
        });

        $user->save();

        return $this->dbRepository('mongodb', 'user')->find($uid);
    }

}