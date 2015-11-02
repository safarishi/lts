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

    protected $reply = '';

    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('disconnect:sqlsrv', ['only' => ['report', 'index', 'show', 'search', 'moreArticle', 'myStar']]);
        $this->middleware('disconnect:mongodb', ['only' => ['favour', 'show', 'commentList', 'myComment', 'myStar']]);
        $this->middleware('oauth', ['except' => ['index', 'show', 'report', 'anonymousComment', 'anonymousReply', 'commentList', 'search', 'moreArticle']]);
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

        $tmp = clone $article;
        unset($tmp->content);
        $this->models['article'] = $tmp;

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
            'hot_comments' => $hotComments,
        ];
    }

    /**
     * [getHotComments description]
     * @param  string $id 文章id
     * @return [type]     [description]
     */
    protected function getHotComments($id)
    {
        $hotComments = $this->dbRepository('mongodb', 'article_comment')
            ->select('content', 'created_at', 'user', 'favoured_user')
            ->where('article.id', $id)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();

        return $this->processCommentResponse($hotComments);
    }

    /**
     * [processCommentResponse description]
     * @param  array $data [description]
     * @return array       [description]
     */
    protected function processCommentResponse($data)
    {
        $response = $this->handleCommentResponse($data);

        foreach ($response as &$value) {
            $value['article'] = $this->models['article'];
            unset($value['favoured_user']);
        }
        unset($value);

        return $response;
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
        $content = Input::get('content');

        $insertData = [
            'content'    => $content,
            'created_at' => date('Y-m-d H:i:s'),
            'comment_id' => $commentId,
            'user'       => $this->user,
        ];

        $reply = $this->dbRepository('mongodb', 'reply');

        $insertId = $reply->insertGetId($insertData);

        $this->reply = '回复：'.$content;
        $this->recordInformation($commentId);

        return $reply->find($insertId);
    }

    /**
     * 存储信息到用户信息集合
     * 1 文章评论回复
     * 2 文章评论匿名回复
     * 3 文章评论点赞
     * 4 文章评论取消点赞
     *
     * @param  string $commentId 文章评论id
     * @return void
     */
    protected function recordInformation($commentId)
    {
        $comment = $this->dbRepository('mongodb', 'article_comment')
            ->select('created_at', 'content', 'article', 'user')
            ->find($commentId);

        $insertData = array(
                'source'     => $this->user,
                'created_at' => date('Y-m-d H:i:s'),
                'content'    => array(
                        'reply'   => $this->reply,
                        'comment' => $comment,
                    ),
            );

        $this->dbRepository('mongodb', 'information')
            ->insert($insertData);
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
        $this->user = $this->dbRepository('mongodb', 'user')
            ->select('display_name', 'avatar_url')
            ->find($uid);

        if ($this->checkUserFavour($uid, $commentId)) {
            throw new DuplicateOperationException('您已点赞！');
        }

        $this->models['article_comment']->where('_id', $commentId)
            ->push('favoured_user', [$uid], true);

        $this->reply = '赞了这条评论:)';
        $this->recordInformation($commentId);

        return $this->favourResponse($commentId);
    }

    public function unfavour($id, $commentId)
    {
        $uid = $this->authorizer->getResourceOwnerId();
        $this->user = $this->dbRepository('mongodb', 'user')
            ->select('display_name', 'avatar_url')
            ->find($uid);

        if ($this->checkUserFavour($uid, $commentId)) {
            $this->reply = '取消了这条评论的赞:(';
            $this->recordInformation($commentId);
        }

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

    /**
     * 文章评论列表
     *
     * @param  string $id 文章id
     * @return array
     */
    public function commentList($id)
    {
        $this->models['article_comment'] = $this->dbRepository('mongodb', 'article_comment');

        $list = $this->models['article_comment']
            ->where('article.id', $id)
            ->orderBy('created_at', 'desc')
            ->take(4)
            ->get();

        $returnData = $this->handleCommentResponse($list);

        foreach ($returnData as &$value) {
            unset($value['favoured_user']);
        }
        unset($value);
        // 其他评论
        $extra = $this->getExtraComment($id);

        return ['list' => $returnData, 'extra' => $extra];
    }

    /**
     * 文章其他评论
     *
     * @param  string $id 文章id
     * @return array
     */
    protected function getExtraComment($id)
    {
        $extra = $this->dbRepository('mongodb', 'article_comment')
            ->where('article.id', '<>', $id)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();

        return $this->handleCommentResponse($extra);
    }

    public function search()
    {
        // 查询关键词
        $q = Input::get('q', '');

        $articleModel = $this->article()
            ->where('article_title', 'like', "%{$q}%")
            ->orWhere('article_writer', 'like', "%{$q}%")
            ->orWhere('article_whoadd', 'like', "%{$q}%");
        // 返回数据增加分页
        MultiplexController::addPagination($articleModel);

        return $articleModel->orderBy('article_addtime', 'desc')
            ->get();
    }

    /**
     * 更多文章
     *
     * @param  string $columnId 栏目id
     * @return todo
     */
    public function moreArticle($columnId)
    {
        $columns = $this->column()
            ->where('lanmu_father', $columnId)
            ->get();
        // 没有子栏目信息
        if (count($columns) === 0) {
            return $this->getMoreArticleByColumnId($columnId);
        }

        foreach ($columns as $column) {
            $column->articles = $this->getArticlesByColumnId($column->column_id);
        }

        return $this->filterArray($columns);
    }

    protected function getMoreArticleByColumnId($id)
    {
        $data = $this->column()
            ->where('lanmu_id', $id)
            ->get();

        foreach ($data as $value) {
            $value->articles = $this->article()
                ->where('article_lanmu', $id)
                ->orderBy('article_addtime', 'desc')
                ->take(20)
                ->get();
        }

        return $data;
    }

    /**
     * 过滤数组，并重新建立数字索引
     *
     * @param  array $array 待处理的数组
     * @return array        处理后的数组
     */
    protected function filterArray($array)
    {
        $result = array_filter($array, function($item) {
            return !empty($item->articles);
        });

        return array_values($result);
    }

}