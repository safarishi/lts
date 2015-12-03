<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Hash;
use Mail;
use Input;
use LucaDegasperi\OAuth2Server\Authorizer;
use League\OAuth2\Server\Exception\InvalidCredentialsException;

class UserPasswordController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('oauth', ['except' => ['sendEmail']]);
        $this->middleware('validation');
    }

    private static $_validate = [
        'modify' => [
            'old_password' => 'required',
            'new_password' => 'required|min:6|confirmed'
        ],
        'sendEmail' => [
            'email' => 'required|email|exists:user',
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

    public function sendEmail()
    {
        MultiplexController::verifyCaptcha();
        $email = request('email');

        $user = $this->dbRepository('mongodb', 'user')
            ->where('email', $email)
            ->first();

        $displayName   = $this->getDisplayName($user);
        $confirmedCode = MultiplexController::uuid();
        $insertData    = [
            'user_id'        => $user['_id'],
            'confirmed_code' => $confirmedCode,
            'created_at'     => date('Y-m-d H:i:s'),
            'expired_at'     => date('Y-m-d H:i:s', time() + 12*60*60),
            'updated_at'     => date('Y-m-d H:i:s'),
        ];
        $this->dbRepository('mongodb', 'password_email')
            ->where('email', $email)
            ->insert($insertData);
        // 传递到邮件内容模板的视图变量
        $mailData = [
            'display_name' => $displayName,
            'confirmed' => $confirmedCode,
        ];

        Mail::send('email.view', $mailData, function ($message) use ($email) {
            $message->to($email)->subject('重设密码');
        });
    }

    /**
     * [getDisplayName description]
     * @param  array $user
     * @return string
     */
    protected function getDisplayName($user)
    {
        return isset($user['display_name']) ? $user['display_name'] : '用户';
    }

}