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

    /**
     * 增加数据分页
     *
     * @param  object $model 需要分页的数据模型
     * @return void
     */
    public static function addPagination($model)
    {
        // 第几页数据，默认为第一页
        $page    = Input::get('page', 1);
        // 每页显示数据条目，默认为每页20条
        $perPage = Input::get('per_page', 20);
        $page    = intval($page);
        $perPage = intval($perPage);

        if ($page <= 0 || !is_int($page)) {
            $page = 1;
        }
        if (!is_int($perPage) || $perPage < 1 || $perPage > 100) {
            $perPage = 20;
        }
        // skip -- offset , take -- limit
        $model->skip(($page - 1) * $perPage)->take($perPage);
    }

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
            throw new ValidationException('token 参数传递错误');
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

    public function generateWeiboUrl()
    {
        $config = Config::get('services.weibo');

        $url = 'https://api.weibo.com/oauth2/authorize?client_id='.
            $config['client_id'].'&redirect_uri='.
            urlencode($config['redirect']).'&response_type=code';

        return $url;
    }

    public function weiboCallback()
    {
        $config = Config::get('services.weibo');

        $this->curlUrl = 'https://api.weibo.com/oauth2/access_token?client_id='.
            $config['client_id'].'&client_secret='.
            $config['client_secret'].'&grant_type=authorization_code&redirect_uri='.
            urlencode($config['redirect']).'&code='.
            Input::get('code');

        $this->curlMethod = 'POST';

        $outcome = json_decode($this->curlOperate());
        // 获取 open id
        $openId = $outcome->uid;

        $result = $this->hasOpenId($openId);
        if ($result) {
            return $result;
        }

        $userInfo = $this->fetchUserInfo($outcome->access_token, $openId);
        $avatar_url = $userInfo->avatar_hd ? $userInfo->avatar_hd : $userInfo->avatar_large;

        $tmpToken = self::temporaryToken();

        $this->storeOpenId($openId, $tmpToken);

        return 'store success';
    }

    /**
     * 检查第三方登录的 open id 是否存在
     *
     * @param  string  $openId
     * @return string|false
     */
    protected function hasOpenId($openId)
    {
        $exist = DB::connection('mongodb')->collection('user')
            ->where('addition.open_id', $openId)
            ->first();

        if ($exist === null) {
            return false;
        }

        return $exist['addition']['token'];
    }

    protected function storeOpenId($openId, $tmpToken)
    {
        $user = DB::connection('mongodb')->collection('user');

        $insertData = [
            'created_at' => date('Y-m-d H:i:s'),
            'addition' => array(
                    'open_id' => $openId,
                    'token'   => $tmpToken,
                ),
        ];

        $user->insert($insertData);
    }

    protected function fetchUserInfo($accessToken, $uid)
    {
        $this->curlUrl    = 'https://api.weibo.com/2/users/show.json?access_token='.$accessToken.'&uid='.$uid;
        $this->curlMethod = 'GET';

        return $this->curlOperate();
    }

    /**
     *
     * @return object
     *
     * @throws Rootant\Api\Exception\AuthorizationEntryException
     */
    protected function curlOperate()
    {
        // curl
        $curl = curl_init();

        curl_setopt_array($curl, array(
          CURLOPT_URL => $this->curlUrl,
          CURLOPT_RETURNTRANSFER => true,
          CURLOPT_ENCODING => '',
          CURLOPT_MAXREDIRS => 10,
          CURLOPT_TIMEOUT => 30,
          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
          CURLOPT_CUSTOMREQUEST => $this->curlMethod,
          CURLOPT_SSL_VERIFYPEER => false,
        ));

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
          echo "cURL Error #:" . $err;
        } else {
          return $response;
        }
    }

    /**
     * 根据 token 获取用户的登录口令
     *
     * @return array
     */
    public function entry()
    {
        $token = self::validateToken();

        $exist = DB::connection('mongodb')->collection('user')
            ->where('addition.token', $token)
            ->first();

        if ($exist === null) {
            throw new ValidationException('无效的 token');
        }

        return $exist['entry'];
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

    public function generateQqUrl()
    {
        $config = Config::get('services.qq');

        $url = 'https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id='.
            $config['app_id'].'&redirect_uri='.
            urlencode($config['redirect']).'&state=test';

        return $url;
    }

    public function qqCallback()
    {
        $config = Config::get('services.qq');

        $this->curlUrl = 'https://graph.qq.com/oauth2.0/token?grant_type=authorization_code&client_id='.
            $config['app_id'].'&client_secret='.
            $config['app_key'].'&code='.
            Input::get('code').'&redirect_uri='.
            urlencode($config['redirect']);

        $outcome = $this->curlOperate();

        $accessToken = explode('=', explode('&', $outcome)[0])[1];

        $this->curlUrl = 'https://graph.qq.com/oauth2.0/me?access_token='.$accessToken;
        $result = $this->curlOperate();
        var_dump($result);
        // todo
        /*
            string 'callback( {"client_id":"101257109","openid":"292FC6D986316F300806E42DA87EDC75"} );
' (length=83)
         */
    }

}