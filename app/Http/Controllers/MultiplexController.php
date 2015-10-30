<?php

namespace App\Http\Controllers;

class MultiplexController extends CommonController
{
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

}