<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Hash;
use Input;
use LucaDegasperi\OAuth2Server\Authorizer;
use League\OAuth2\Server\Exception\InvalidCredentialsException;

class UserPasswordController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('oauth');
        $this->middleware('validation');
    }

    private static $_validate = [
        'modify' => [
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed'
        ],
    ];

    public function modify()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $oldPassword = Input::get('old_password');
        $newPassword = Input::get('new_password');

        $email = $this->dbRepository('mongodb', 'user')
            ->where('_id', $uid)
            ->pluck('email');

        $this->validateCurrentPassword($email, $oldPassword);

        $updateData = [
            'password' => Hash::make($newPassword),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $item = $this->dbRepository('mongodb', 'user')
            ->where('_id', $uid)
            ->update($updateData);
        if ($item === 1) {
            // 如果是第三方用户的话，修改第三方用户的登录密码
            $this->updateThirdPartyUserPassword($email, $newPassword);
        }

        return $this->dbRepository('mongodb', 'user')->find($uid);
    }

    /**
     * 更新第三方用户绑定的登录密码
     *
     * @param  string $username 第三方登录名
     * @param  string $password 第三方登录密码
     * @return void
     */
    protected function updateThirdPartyUserPassword($username, $password)
    {
        DB::collection('user')->where('entry.username', $username)
            ->update(['entry.password' => $password]);
    }

    /**
     * 校验用户当前的登录密码
     *
     * @param  string $username 登录名
     * @param  string $password 密码
     * @return void
     *
     * @throws League\OAuth2\Server\Exception\InvalidCredentialsException
     */
    protected function validateCurrentPassword($username, $password)
    {
        $credentials = [
            'email' => $username,
            'password' => $password,
        ];

        if (!Auth::once($credentials)) {
            throw new InvalidCredentialsException;
        }
    }

}