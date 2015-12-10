<?php

namespace App\Http\Controllers;

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

        echo 'cURL Error #:'.$err;
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

}