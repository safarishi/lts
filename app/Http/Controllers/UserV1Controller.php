<?php

namespace App\Http\Controllers;

use DB;
use LucaDegasperi\OAuth2Server\Authorizer;

class UserV1Controller extends UserController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('validation');
    }

    private static $_validate = [
        'store' => [
            'email'    => 'required|email|unique:user',
            'password' => 'required|min:6|confirmed',
        ],
    ];

    public function show()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $fields = ['avatar_url', 'email', 'gender', 'display_name', 'company'];

        return DB::collection('user')->find($uid, $fields);
    }

    public function myStar()
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $articleIdArr = DB::collection('user')
            ->where('_id', $uid)
            ->pluck('starred_articles');
        if (!$articleIdArr) {
            return [];
        }
        // 反转数组
        $idArr = array_reverse($articleIdArr);
        $articles = [];
        foreach ($idArr as $id) {
            $articles[] = $this->getArticleBriefById($id);
        }

        return $articles;
    }
}