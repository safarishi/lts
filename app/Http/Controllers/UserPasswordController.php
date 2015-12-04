<?php

namespace App\Http\Controllers;

use DB;
use Auth;
use Hash;
use Mail;
use Input;
use App\Exceptions\ValidationException;
use LucaDegasperi\OAuth2Server\Authorizer;
use League\OAuth2\Server\Exception\InvalidCredentialsException;

class UserPasswordController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('oauth', ['except' => ['sendEmail', 'reset']]);
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
        'reset' => [
            'password' => 'required|min:6|confirmed',
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

        $this->models['user'] = DB::collection('user');

        $user = $this->models['user']
            ->where('email', $email)
            ->first();

        $displayName   = $this->getDisplayName($user);
        $confirmedCode = MultiplexController::uuid();
        $updateData = [
            'password_email' => [
                'confirmed_code' => $confirmedCode,
                'expired_at'     => date('Y-m-d H:i:s', time() + 12*60*60),
            ],
        ];
        $this->models['user']->update($updateData);
        // 传递到邮件内容模板的视图变量
        $emailData = [
            'display_name' => $displayName,
            'confirmed'    => $confirmedCode,
        ];
        Mail::send('email.view', $emailData, function ($message) use ($email) {
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

    /**
     * 重置用户密码
     *
     * @return void
     *
     * @throws \App\Exceptions\ValidationException
     */
    public function reset()
    {
        if (!Input::has('confirmation')) {
            throw new ValidationException('参数错误:)');
        }
        // 移除过期失效数据
        $this->removeExpiredData();

        $confirmation = request('confirmation');

        $user = DB::collection('user')
            ->where('password_email.confirmed_code', $confirmation)
            ->first();

        if (!$user) {
            throw new ValidationException('链接或已失效，请重新找回密码！');
        }
        $expiredAt = $user['password_email']['expired_at'];
        // 判断链接时候失效
        if (time() > strtotime($expiredAt)) {
            throw new ValidationException('重置密码链接已失效，请重新找回密码！');
        }

        $this->uid = $user['_id'];
        // 密码重置处理
        $this->resetProcess($confirmation);
    }

    /**
     * 移除过期失效的数据字段
     * unset
     *
     * @return void
     */
    protected function removeExpiredData()
    {
        DB::collection('user')
            ->where('password_email.expired_at', '<', date('Y-m-d H:i:s'))
            ->unset('password_email');
    }

    protected function resetProcess($confirmation)
    {
        $this->password = request('password');
        // 检查是否是上次密码
        $this->checkIsLastPassword();
        // 重置密码
        $this->resetPassword();
        // 重置成功，移除密码重置链接对应的数据
        $this->removeData($confirmation);
    }

    /**
     * 检查密码是否为上次密码
     *
     * @return void
     *
     * @throws  \App\Exceptions\ValidationException
     */
    protected function checkIsLastPassword()
    {
        $hashedPassword = DB::collection('user')
            ->where('_id', $this->uid)
            ->pluck('password');

        if (!$hashedPassword) {
            return;
        }

        if (Hash::check($this->password, $hashedPassword)) {
            throw new ValidationException('不能和上次密码相同:)');
        }
    }

    /**
     * 重置密码
     *
     * @return void
     */
    protected function resetPassword()
    {
        $updateData = [
            'password' => bcrypt($this->password),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        DB::collection('user')
            ->where('_id', $this->uid)
            ->update($updateData);
    }

    /**
     * 移除密码重置连接对应的数据
     * unset
     *
     * @param  string $confirmation 确认码
     * @return void
     */
    protected function removeData($confirmation)
    {
        DB::collection('user')
            ->where('password_email.confirmed_code', $confirmation)
            ->unset('password_email');
    }

}