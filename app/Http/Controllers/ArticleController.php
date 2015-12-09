<?php

namespace App\Http\Controllers;

use DB;
use Input;
use Request;
use Response;
use App\Exceptions\ValidationException;
use LucaDegasperi\OAuth2Server\Authorizer;
use App\Exceptions\DuplicateOperateException;

class ArticleController extends CommonController
{

    protected $origin;

    protected $reply = '';

    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('disconnect:sqlsrv', ['only' => ['report', 'index', 'show', 'search', 'moreArticle', 'myStar', 'team']]);
        $this->middleware('disconnect:sqlsrv2', ['only' => ['product']]);
        $this->middleware('disconnect:mongodb', ['only' => ['favour', 'show', 'commentList', 'myComment', 'myStar', 'myInformation']]);
        $this->middleware('oauth', ['except' => ['index', 'show', 'report', 'anonymousComment', 'anonymousReply', 'commentList', 'search', 'moreArticle', 'product', 'team']]);
        $this->middleware('validation');
    }

    private static $_validate = [
        'comment' => [
            'content' => 'required',
        ],
        'anonymousComment' => [
            'content' => 'required',
        ],
    ];

    public function index()
    {
        $pictureNews = $this->partArticle()
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
        $articles = $this->partArticle()
            ->where('article_lanmu', $id)
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

        $articles = $this->partArticle()
            ->whereIn('article_lanmu', $ids)
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
            throw new ValidationException('参数传递错误:(');
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
            ->select('content', 'created_at', 'user', 'favoured_user', 'article')
            ->where('article.id', $id)
            ->orderBy('created_at', 'desc')
            ->take(2)
            ->get();

        return $this->handleCommentResponse($hotComments);
    }

    protected function checkUserArticleStar($id)
    {
        if (!$this->accessToken) {
            return false;
        }

        $uid = $this->getOwnerId();

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
            throw new DuplicateOperateException('您已收藏:(');
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
     * @return array
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
     * @return array
     */
    protected function commentResponse($id)
    {
        $article = (array) $this->article()
            ->where('article_id', $id)
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
     * @return array
     */
    public function anonymousComment($id)
    {
        // 校验验证码
        MultiplexController::verifyCaptcha();

        $this->user = MultiplexController::anonymousUser(Request::ip());

        return $this->commentResponse($id);
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
            $value->articles = $this->partArticle()
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

    public function product()
    {
        echo $this->dbRepository('sqlsrv2', 'info')
            ->where('info_id', 5)
            ->first()
            ->info_desc_cn;
    }

    public function team()
    {
        $teamModel = $this->dbRepository('sqlsrv', 'expert')
            ->select('expert_id as id', 'expert_photo as avatar_url', 'expert_name as name', 'expert_title as position', 'expert_Description as description')
            ->where('expert_language', 'zh-cn')
            ->whereIn('expert_type', ['领导', '研究人员'])
            ->orderBy('expert_order', 'desc');

        MultiplexController::addPagination($teamModel);

        $members = $teamModel->get();

        foreach ($members as $member) {
            $member->avatar_url = $this->addImagePrefixUrl($member->avatar_url);
        }

        return $members;
    }

}