<?php

namespace App\Http\Controllers;

use Input;

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

}