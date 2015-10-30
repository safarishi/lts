<?php

namespace App\Http\Controllers;

use DB;
use Input;
use Request;
use Response;
use App\Exceptions\ValidationException;
use LucaDegasperi\OAuth2Server\Authorizer;
use App\Exceptions\DuplicateOperationException;

class ArticleController extends CommonController
{

    protected $origin;

    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('disconnect:sqlsrv', ['only' => ['report', 'index']]);
        $this->middleware('disconnect:mongodb', ['only' => ['favour']]);
        $this->middleware('oauth', ['except' => ['index', 'show', 'report', 'anonymousComment', 'anonymousReply']]);
        $this->middleware('validation.required:content', ['only' => ['anonymousComment', 'anonymousReply', 'comment', 'reply']]);
    }

    public function index()
    {
        $pictureNews = $this->article()
            ->where('article_havelogo', 1)
            ->orderBy('article_addtime', 'desc')
            ->take(3)
            ->get();

        foreach ($pictureNews as $value) {
            $value->thumbnail_url = $this->addImagePrefixUrl($value->thumbnail_url);
        }

        $columns = $this->getColumns();

        foreach ($columns as $column) {
            // 获取栏目下的文章列表
            $column->articles = $this->getColumnArticle($column->column_id);
        }

        return ['picture_news' => $pictureNews, 'article_list' => $columns];
    }

    protected function getColumns()
    {
        $columns = $this->column()
            ->whereIn('lanmu_father', [1, 2, 52])
            ->whereNotIn('lanmu_id', [2, 32, 52, 66])
            ->get();

        return $this->processColumnsData($columns);
    }

    protected function processColumnsData($columns)
    {
        $columns = $this->addSortField($columns);

        $sort = array();
        foreach ($columns as $column) {
            $sort[] = $column->sort_value;
        }

        array_multisort($sort, SORT_ASC, $columns);

        foreach ($columns as $column) {
            unset($column->sort_value);
        }

        return $columns;
    }

    protected function addSortField($data)
    {
        foreach ($data as $value) {
            if ($value->column_id === '6') {
                $value->sort_value = 1;
            } elseif ($value->column_id === '3') {
                $value->sort_value = 3;
            } elseif ($value->column_id === '7') {
                $value->sort_value = 19;
            } elseif ($value->column_id === '38') {
                $value->sort_value = 15;
            } elseif (in_array($value->column_id, [13, 71, 90, 168, 40, 167, 113, 171])) {
                $value->sort_value = 5;
            } else {
                $value->sort_value = 17;
            }
        }

        return $data;
    }

    /**
     * [getColumnArticle description]
     * @param  string $columnId [description]
     * @return [type]           [description]
     */
    protected function getColumnArticle($columnId)
    {
        $articles = $this->getArticlesByColumnId($columnId);

        if (count($articles) === 0) {
            $articles = $this->getArticlesBySubColumnId($columnId);
        }

        return $articles;
    }

    protected function getArticlesByColumnId($id)
    {
        $articles = $this->article()->where('article_lanmu', $id)
            ->orderBy('article_addtime', 'desc')
            ->take(3)
            ->get();

        foreach ($articles as $value) {
            $value->thumbnail_url = $this->addImagePrefixUrl($value->thumbnail_url);
        }

        return $articles;
    }

    /**
     * [getArticlesBySubColumnId description]
     * @param  string $id [description]
     * @return [type]     [description]
     */
    protected function getArticlesBySubColumnId($id)
    {
        $columns = $this->column()
            ->where('lanmu_father', $id)
            ->get();

        $ids = array();
        foreach ($columns as $column) {
            $ids[] = $column->column_id;
        }

        $articles = $this->article()->whereIn('article_lanmu', $ids)
            ->orderBy('article_addtime', 'desc')
            ->take(3)
            ->get();

        foreach ($articles as $value) {
            $value->thumbnail_url = $this->addImagePrefixUrl($value->thumbnail_url);
        }

        return $articles;
    }

    public function show($id)
    {
        $article = $this->article()
            ->addSelect('article_body as content')
            ->where('article_id', $id)
            ->first();

        if ($article === null) {
            throw new ValidationException('Article id parameter is wrong.');
        }
        // 处理文章内容里面的图片显示
        $tmpContent = str_replace('&#34;', '"', $article->content);
        $article->content = preg_replace('#(src=")/#', "\$1".'http://sisi-smu.org/', $tmpContent);

        $article->thumbnail_url = $this->addImagePrefixUrl($article->thumbnail_url);
        // 返回是否收藏文章
        $article->is_starred = $this->checkUserArticleStar($id);

        // 相关文章
        $this->origin = $article->origin;
        $relatedArticles = $this->getRelatedArticles($id);
        // 热门评论
        $hotComments = $this->getHotComments($id);

        return [
            'article' => $article,
            'related_articles' => $relatedArticles,
        ];
    }

    /**
     * [getHotComments description]
     * @param  string $id 文章id
     * @return [type]     [description]
     */
    protected function getHotComments($id)
    {
        // todo
    }

    protected function checkUserArticleStar($id)
    {
        $uid = $this->getUid();

        return $this->checkUserStar($uid, $id);
    }

    /**
     * [getRelatedArticles description]
     * @param  string $id [article id]
     * @return [type]     [description]
     */
    protected function getRelatedArticles($id)
    {
        return $this->article()
            ->where('article_writer', $this->origin)
            ->where('article_id', '<>', $id)
            ->take(2)
            ->get();
    }

    public function report()
    {
        return $this->dbRepository('sqlsrv', 'lanmu')
            ->select('lanmu_id as id', 'lanmu_name as name')
            ->where('lanmu_language', 'zh-cn')
            ->whereIn('lanmu_father', [113, 167, 168])
            ->get();
    }

    public function star($id)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        if ($this->checkUserStar($uid, $id)) {
            throw new DuplicateOperationException('您已收藏！');
        }

        $this->models['user']
            ->where('_id', $uid)
            ->push('starred_articles', [$id], true);

        return $this->models['user']->find($uid);
    }

    public function unstar($id)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $this->dbRepository('mongodb', 'user')
            ->where('_id', $uid)
            ->pull('starred_articles', [$id]);

        return Response::make('', 204);
    }

    /**
     * 文章评论
     *
     * @param  string $id 文章id
     * @return [type]     [description]
     */
    public function comment($id)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $this->user = $this->dbRepository('mongodb', 'user')
            ->select('avatar_url', 'display_name')
            ->find($uid);

        return $this->commentResponse($id);
    }

    /**
     * 文章评论返回数据
     *
     * @param  string $id 文章id
     * @return todo
     */
    protected function commentResponse($id)
    {
        $article = (array) $this->article()->where('article_id', $id)
            ->select('article_id as id', 'article_writer as origin')
            ->first();

        $insertData = [
            'content'    => Input::get('content'),
            'created_at' => date('Y-m-d H:i:s'),
            'article'    => $article,
            'user'       => $this->user,
        ];

        $comment = $this->dbRepository('mongodb', 'article_comment');

        $insertId = $comment->insertGetId($insertData);

        return $comment->find($insertId);
    }

    /**
     * 匿名评论文章
     *
     * @param  string $id 文章id
     * @return [type]     [description]
     */
    public function anonymousComment($id)
    {
        throw new ValidationException('验证码填写错误');
        // captcha todo

        $this->user = MultiplexController::anonymousUser(Request::ip());

        return $this->commentResponse($id);
    }

    /**
     * 文章评论回复
     *
     * @param  string  $id        文章id
     * @param  string  $commentId 文章评论id
     * @return array
     */
    public function reply($id, $commentId)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $this->user = $this->dbRepository('mongodb', 'user')
            ->select('avatar_url', 'display_name')
            ->find($uid);

        return $this->replyResponse($commentId);
    }

    /**
     * [replyResponse description]
     * @param  string $commentId 文章评论id
     * @return array
     */
    protected function replyResponse($commentId)
    {
        $insertData = [
            'content'    => Input::get('content'),
            'created_at' => date('Y-m-d H:i:s'),
            'comment_id' => $commentId,
            'user'       => $this->user,
        ];

        $reply = $this->dbRepository('mongodb', 'reply');

        $insertId = $reply->insertGetId($insertData);

        return $reply->find($insertId);
    }

    /**
     * 文章评论匿名回复
     * @param  string  $id        文章id
     * @param  string  $commentId 文章评论id
     * @return todo
     */
    public function anonymousReply($id, $commentId)
    {
        throw new ValidationException('验证码填写错误');
        // captcha todo

        $this->user = MultiplexController::anonymousUser(Request::ip());

        return $this->replyResponse($commentId);
    }

    public function favour($id, $commentId)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        if ($this->checkUserFavour($uid, $commentId)) {
            throw new DuplicateOperationException('您已点赞！');
        }

        $this->models['article_comment']->where('_id', $commentId)
            ->push('favoured_user', [$uid], true);

        return $this->favourResponse($commentId);
    }

    public function unfavour($id, $commentId)
    {
        $uid = $this->authorizer->getResourceOwnerId();

        $this->models['article_comment'] = $this->dbRepository('mongodb', 'article_comment');

        $this->models['article_comment']
            ->where('_id', $commentId)
            ->pull('favoured_user', [$uid]);

        return $this->favourResponse($commentId);
    }

    /**
     * 赞返回数据
     *
     * @param  [type] $commentId [description]
     * @return [type]            [description]
     */
    protected function favourResponse($commentId)
    {
        $comment = $this->models['article_comment']->find($commentId);

        return [
            'article_comment_id' => $commentId,
            'favours' => count($comment['favoured_user']),
        ];
    }

}