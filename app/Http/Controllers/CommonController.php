<?php

namespace App\Http\Controllers;

use DB;

class CommonController extends ApiController
{

    protected $models = array();

    /**
     * [dbRepository description]
     *
     * @param  string $connection 数据库连接名
     * @param  string $name       数据库表名（或集合名）
     * @return object
     */
    protected function dbRepository($connection, $name)
    {
        return DB::connection($connection)->table($name);
    }

    protected function article()
    {
        return $this->dbRepository('sqlsrv', 'articles')
            ->select('article_id as id', 'article_title as title', 'article_logo as thumbnail_url', 'article_writer as origin', 'article_whoadd as author', 'article_addtime as created_at')
            ->where('article_active', 1);

    }

    /**
     * 增加图片前缀 url
     *
     * @param [type] $thumbnailUrl [description]
     */
    protected function addImagePrefixUrl($thumbnailUrl)
    {
        if (!empty($thumbnailUrl)) {
            return 'http://sisi-smu.org'.str_replace('\\', '/', $thumbnailUrl);
        }

        return '';
    }

    /**
     * 栏目信息固定返回
     *
     * @return object
     */
    protected function column()
    {
        return $this->dbRepository('sqlsrv', 'lanmu')
            ->select('lanmu_id as column_id', 'lanmu_name as column_name')
            ->where('lanmu_language', 'zh-cn')
            ->where('lanmu_active', 1);
    }

    /**
     * 获取用户id
     * 用户未登录返回空字符串 ''
     * 登录用户返回用户id
     *
     * @return string
     */
    protected function getUid()
    {
        $uid = '';

        if ($this->accessToken) {
            // 获取用户id
            $this->authorizer->validateAccessToken();
            $uid = $this->authorizer->getResourceOwnerId();
        }

        return $uid;
    }

    /**
     * 检查用户是否收藏文章
     *
     * @param  string $uid       用户id
     * @param  string $articleId 文章id
     * @return todo
     */
    protected function checkUserStar($uid, $articleId)
    {
        $this->models['user'] = $this->dbRepository('mongodb', 'user');

        $user = $this->models['user']->find($uid);

        if ($user === null) {
            return false;
        }

        $starred = array();
        if (array_key_exists('starred_articles', $user)) {
            $starred = $user['starred_articles'];
        }

        return in_array($articleId, $starred);
    }

}