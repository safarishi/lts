<?php

namespace App\Http\Controllers;

use DB;
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
        $this->middleware('oauth', ['except' => ['index', 'show', 'report']]);
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
        // todo

        // 相关文章
        $this->origin = $article->origin;
        $relatedArticles = $this->getRelatedArticles($id);
        // 热门评论
        // $hotComments = $this->getHotComments($id);
        // todo

        return [
            'article' => $article,
            'related_articles' => $relatedArticles,
        ];
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

}