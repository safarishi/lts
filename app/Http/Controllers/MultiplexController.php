<?php

namespace App\Http\Controllers;

use DB;
use Hash;
use Input;
use Image;
use Config;
use Captcha;
use Session;
use Validator;
use App\Exceptions\ValidationException;

class MultiplexController extends CommonController
{

    protected $curlUrl = '';

    protected $curlMethod = 'GET';

    protected $serviceConfig = array();

    protected $accessToken = '';

    public static function anonymousUser($ip)
    {
        $area = self::getArea($ip);

        $avatarUrl = '/uploads/images/avatar/default.png';

        return [
            'avatar_url' => $avatarUrl,
            'display_name' => '来自'.$area.'的用户'
        ];
    }

   /**
    * 根据ip获取用户所在的城市信息
    *
    * @param  string $ip
    * @return string     城市名称
    */
    public static function getArea($ip)
    {
        // 根据ip地址查询地点信息的url
        $url = "http://int.dpool.sina.com.cn/iplookup/iplookup.php?format=json&ip=".$ip;

        $data = json_decode(file_get_contents($url));

        $result = '火星';
        if (is_object($data) && property_exists($data, 'city')) {
           $result = $data->city;
        }

        return $result;
    }

    // /**
    //  * 增加数据分页
    //  *
    //  * @param  object $model 需要分页的数据模型
    //  * @return void
    //  */
    // public static function addPagination($model)
    // {
    //     // 第几页数据，默认为第一页
    //     $page    = Input::get('page', 1);
    //     // 每页显示数据条目，默认为每页20条
    //     $perPage = Input::get('per_page', 20);
    //     $page    = intval($page);
    //     $perPage = intval($perPage);

    //     if ($page <= 0 || !is_int($page)) {
    //         $page = 1;
    //     }
    //     if (!is_int($perPage) || $perPage < 1 || $perPage > 100) {
    //         $perPage = 20;
    //     }
    //     // skip -- offset , take -- limit
    //     $model->skip(($page - 1) * $perPage)->take($perPage);
    // }

    /**
     * 上传用户头像
     *
     * @param string $uid 用户id
     */
    public static function uploadAvatar($uid)
    {
        $ext = 'png';
        $subDir = substr($uid, -1);
        $storageDir = Config::get('imagecache.paths.avatar_url').'/'.$subDir.'/';
        $storageName = $uid;
        $storagePath = $subDir.'/'.$storageName.'.'.$ext;

        if (!file_exists($storageDir)) {
            @mkdir($storageDir, 0777, true);
        }

        Image::make(Input::file('avatar_url'))->encode($ext)->save($storageDir.$storageName.'.'.$ext);

        return Config::get('imagecache.paths.avatar_url_prefix').'/'.$storagePath;
    }

    public function generateToken()
    {
        echo self::temporaryToken();
    }

    /**
     * 临时 token
     *
     * @return string
     */
    public static function temporaryToken()
    {
        $randomStr = self::generateRandomStr(7);

        return uniqid($randomStr, true);
    }

    /**
     * 生成临时 token
     * @return string
     */
    public static function uuid()
    {
        return self::temporaryToken();
    }

    /**
     * 随机生成默认长度6位由字母、数字组成的字符串
     *
     * @param  integer $length
     * @return string          随机生成的字符串
     */
    public static function generateRandomStr($length = 6)
    {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str   = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= $chars[mt_rand(0, strlen($chars) - 1)];
        }
        return $str;
    }

    public function generateCaptcha()
    {
        $token = Input::get('token');

        if (strlen($token) !== 30) {
            throw new ValidationException('临时令牌 参数错误');
        }

        $mayNeedReturn = Captcha::create();
        // todo

        $captcha = Session::get('captcha');

        $insertData = [
            'captcha'    => $captcha['key'],
            'token'      => $token,
            'created_at' => date('Y-m-d H:i:s'),
        ];

        DB::connection('mongodb')->collection('tmp')
            ->insert($insertData);
    }

    /**
     * 校验验证码
     *
     * @return void
     *
     * @throws \App\Exceptions\Validation
     */
    public static function verifyCaptcha()
    {
        $token   = Input::get('token');
        $captcha = Input::get('captcha');

        $validator = Validator::make(Input::only('captcha', 'token'), [
                'captcha' => 'required',
                'token'   => 'required',
            ]);

        if ($validator->fails()) {
            $messages = $validator->messages();
            throw new ValidationException($messages->all());
        }

        $tmp = DB::connection('mongodb')->collection('tmp');

        $data = $tmp->where('token', $token)->first();

        if ($data === null) {
            throw new ValidationException('无效的 token');
        }

        if (!Hash::check(mb_strtolower($captcha), $data['captcha'])) {
            throw new ValidationException('验证码填写不正确');
        }
        // 验证码验证通过后删除临时 token 数据
        $tmp->where('token', $token)->delete();
    }

    /**
     * 校验传递过来的 token 参数
     *
     * @return string
     *
     * @throws \App\Exceptions\ValidationException
     */
    public static function validateToken()
    {
        $token = Input::get('token');

        if (strlen($token) !== 30) {
            throw new ValidationException('token 参数传递错误');
        }

        return $token;
    }

    public function redirectUrl()
    {
        echo 'redirect url test with oauth2 grant type for authorization code';
    }

    public function authLogin()
    {
        echo 'auth login';
    }

}