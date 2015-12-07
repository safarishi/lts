<?php

namespace App\Http\Controllers;

use DB;
use Input;
use Cache;
use Validator;
use App\Exceptions\ValidationException;

class CommonController extends ApiController
{

    protected $user;

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
            ->select('article_id as id', 'article_title as title',
                'article_logo as thumbnail_url', 'article_writer as origin',
                'article_whoadd as author', 'article_addtime as created_at', 'article_body as content')
            ->where('article_active', 1);

    }

    /**
     * [getArticleById description]
     * @param  string $id 文章id
     * @return todo
     */
    protected function getArticleById($id)
    {
        $key = 'articles/'.$id;
        if (Cache::has($key)) {
            return Cache::get($key);
        }

        $article = $this->article()
            ->where('article_id', $id)
            ->first();
        // 缓存文章详情
        // $minutes = 7*24*60;
        $minutes = 60*24*60;
        Cache::put($key, $article, $minutes);

        return $article;
    }

    protected function getArticleBriefById($id)
    {
        $article = $this->getArticleById($id);

        unset($article->content);

        return $article;
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
     * 获取用户 ID
     * 用户未登录返回空字符串 ''
     * 登录用户返回用户 ID
     *
     * @return string
     */
    protected function getUid()
    {
        return (!$this->accessToken) ? '' : ($this->getOwnerId());
    }

    /**
     * 根据 Access Token 获取用户 ID
     *
     * @return string
     */
    protected function getOwnerId()
    {
        $this->authorizer->validateAccessToken();

        return $this->authorizer->getResourceOwnerId();
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

    /**
     * 判断用户是否点赞评论
     *
     * @param  string $uid       用户id
     * @param  string $commentId 文章评论id
     * @return boolean
     */
    protected function checkUserFavour($uid, $commentId)
    {
        $this->models['article_comment'] = $this->dbRepository('mongodb', 'article_comment');

        $comment = $this->models['article_comment']
            ->select('favoured_user')
            ->find($commentId);

        if ($comment === null) {
            return false;
        }

        $favouredUser = array();
        if (array_key_exists('favoured_user', $comment)) {
            $favouredUser = $comment['favoured_user'];
        }

        return in_array($uid, $favouredUser, true);
    }

    /**
     * 处理评论返回数据
     *
     * @param  array $response [description]
     * @return array           [description]
     */
    protected function handleCommentResponse($response)
    {
        $uid = $this->getUid();

        foreach ($response as &$value) {
            $nums = 0;
            $isFavoured = false;
            if (array_key_exists('favoured_user', $value)) {
                $favouredUser = $value['favoured_user'];
                $nums = count($favouredUser);
                $isFavoured = in_array($uid, $favouredUser, true);
            }
            $value['favours'] = $nums;
            $value['is_favoured'] = $isFavoured;

            $replyId = $value['_id']->{'$id'};
            $replies = $this->getReply($replyId);
            if ($replies) {
                $value['replies'] = $replies;
            }
        }
        unset($value);

        return $response;
    }

    protected function getReply($id)
    {
        return $this->dbRepository('mongodb', 'reply')
            ->select('created_at', 'content', 'user')
            ->where('comment_id', $id)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();
    }

}