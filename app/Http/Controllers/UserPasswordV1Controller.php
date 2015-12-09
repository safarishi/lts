<?php

namespace App\Http\Controllers;

use DB;
use LucaDegasperi\OAuth2Server\Authorizer;

class UserPasswordV1Controller extends UserPasswordController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
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
            'password' => 'required|min:6|confirmed'
        ],
    ];

    public function modify()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $oldPassword = request('old_password');
        $newPassword = request('new_password');

        $email = DB::collection('user')
            ->where('_id', $uid)
            ->pluck('email');

        $this->validateCurrentPassword($email, $oldPassword);

        $updateData = [
            'password'   => bcrypt($newPassword),
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        $item = DB::collection('user')
            ->where('_id', $uid)
            ->update($updateData);
        if ($item === 1) {
            // 如果是第三方登录用户的话，需要级联修改第三方用户的登录的密码
            $this->updateThirdPartyUserPassword($email, $newPassword);
        }

        $fields = ['display_name', 'email', 'avatar_url', 'updated_at'];
        return DB::collection('user')->find($uid, $fields);
    }

}
