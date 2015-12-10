<?php

namespace App\Http\Controllers;

use DB;
use App\Exceptions\ValidationException;
use App\Exceptions\InvalidClientException;
use App\Exceptions\AuthorizationEntryException;

class ThirdPartyLoginV1Controller extends ThirdPartyLoginController
{
    /**
     * 公共的 curl 操作
     *
     * @return todo
     */
    protected function curlOperate()
    {
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

        if (!$err) {
            return $response;
        }

        throw new AuthorizationEntryException($err);
    }

    public function redirectUrl($type)
    {
        $this->type = $type;

        return $this->generateUrl();
    }

    protected function generateUrl()
    {
        $serviceConfig = config('services.'.$this->type);

        switch ($this->type) {
            case 'weibo':
                $url = 'https://api.weibo.com/oauth2/authorize?client_id='.
                    $serviceConfig['AppId'].'&redirect_uri='.
                    urlencode($serviceConfig['CallbackUrl']).'&response_type=code';
                break;
            case 'qq':
                $url = 'https://graph.qq.com/oauth2.0/authorize?response_type=code&client_id='.
                    $serviceConfig['AppId'].'&redirect_uri='.
                    urlencode($serviceConfig['CallbackUrl']).'&state=test';
                break;
            case 'weixin':
                $url = 'https://open.weixin.qq.com/connect/oauth2/authorize?appid='.
                    $serviceConfig['AppId'].'&redirect_uri='.
                    urlencode($serviceConfig['CallbackUrl']).'&response_type=code&scope=snsapi_userinfo&state=test#wechat_redirect';
                break;
            default:
                # code...
                break;
        }

        return $url;
    }

    protected function getOpenId()
    {
        $this->serviceConfig = config('services.'.$this->type);

        $code = request('code');

        switch ($this->type) {
            case 'weibo':
                $this->curlUrl = 'https://api.weibo.com/oauth2/access_token?client_id='.
                    $this->serviceConfig['AppId'].'&client_secret='.
                    $this->serviceConfig['AppSecret'].'&grant_type=authorization_code&redirect_uri='.
                    urlencode($this->serviceConfig['CallbackUrl']).'&code='.$code;

                $this->curlMethod = 'POST';
                break;
            case 'qq':
                return $this->getQqOpenId();
                break;
            case 'weixin':
                $this->curlUrl = 'https://api.weixin.qq.com/sns/oauth2/access_token?appid='
                    .$this->serviceConfig['AppId'].'&secret='
                    .$this->serviceConfig['AppSecret'].'&code='
                    .$code.'&grant_type=authorization_code';
                break;
            default:
                # code...
                break;
        }

        $outcome = json_decode($this->curlOperate());
        $this->accessToken = $outcome->access_token;

        return ($this->type === 'weibo') ? $outcome->uid : $outcome->openid;
    }

    /**
     * 检查第三方登录 Open ID 是否存在
     *
     * @param  string  $openId
     * @return string|false
     */
    protected function hasOpenId($openId)
    {
        $exist = DB::collection('user')
            ->where('addition.open_id', $openId)
            ->pluck('addition.token');

        return $exist ? $exist['token'] : false;
    }

    protected function fetchUser($openId)
    {
        switch ($this->type) {
            case 'weibo':
                $this->curlUrl = 'https://api.weibo.com/2/users/show.json?access_token='.$this->accessToken.'&uid='.$openId;
                $this->curlMethod = 'GET';
                break;
            case 'qq':
                $this->curlUrl = 'https://graph.qq.com/user/get_user_info?access_token='.
                    $this->accessToken.'&openid='.
                    $openId.'&appid='.$this->serviceConfig['AppId'];
                $this->curlMethod = 'GET';
                break;
            case 'weixin':
                $this->curlUrl = 'https://api.weixin.qq.com/sns/userinfo?access_token='.
                    $this->accessToken.'&openid='.
                    $openId.'&lang=zh_CN';
                $this->curlMethod = 'GET';
                break;
            default:
                # code...
                break;
        }

        return json_decode($this->curlOperate());
    }

    /**
     * 存储第三方 Open ID
     *
     * @param  string $openId   Open ID
     * @param  string $tmpToken temporary token
     * @return void
     */
    protected function storeOpenId($openId, $tmpToken)
    {
        $insertData = [
            'created_at' => date('Y-m-d H:i:s'),
            'addition'   => [
                'open_id' => $openId,
                'token'   => $tmpToken,
            ],
        ];

        DB::collection('user')->insert($insertData);
    }

    public function weiboCallback()
    {
        $this->type = 'weibo';
        // 获取第三方用户 Open ID
        $openId = $this->getOpenId();

        $result = $this->hasOpenId($openId);
        if ($result) {
            return 'Has Open Id //<br />'.$result;
        }

        $user = $this->fetchUser($openId);

        $avatarUrl = $user->avatar_hd;

        $tmpToken = MultiplexController::temporaryToken();
        // store Open ID
        $this->storeOpenId($openId, $tmpToken);

        return 'Query String //<br />'.'?avatar_url='.$avatarUrl.'&token='.$tmpToken;
    }

    public function qqCallback()
    {
        if (request('state') !== 'test1') {
            throw new InvalidClientException('客户端不允许:(');
        }

        $this->type = 'qq';

        $openId = $this->getOpenId();

        $result = $this->hasOpenId($openId);
        if ($result) {
            return 'Has Open Id //<br />'.$result;
        }

        $user = $this->fetchUser($openId);

        $avatarUrl = $user->figureurl_qq_2;

        $tmpToken = MultiplexController::temporaryToken();

        $this->storeOpenId($openId, $tmpToken);

        return 'Query String //<br />'.'?avatar_url='.$avatarUrl.'&token='.$tmpToken;
    }

    public function entry()
    {
        $token = request('token');

        if (strlen($token) != 30) {
            throw new ValidationException('令牌参数传递错误:(');
        }

        $entry = DB::collection('user')
            ->where('addition.token', $token)
            ->pluck('entry');

        if ($entry === null) {
            throw new ValidationException('令牌已失效:(');
        }

        return $entry;
    }

    public function weixinCallback()
    {
        $this->type = 'weixin';

        $openId = $this->getOpenId();

        $result = $this->hasOpenId($openId);
        if ($result) {
            return 'Has Open Id //<br />'.$result;
        }

        $user = $this->fetchUser($openId);

        $avatarUrl = $user->headimgurl;

        $tmpToken = MultiplexController::temporaryToken();

        $this->storeOpenId($openId, $tmpToken);

        return 'Query String //<br />'.'?avatar_url='.$avatarUrl.'&token='.$tmpToken;
    }

}