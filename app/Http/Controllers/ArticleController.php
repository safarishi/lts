<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;

class ArticleController extends CommonController
{

    public function __construct()
    {
        $this->middleware('disconnect:sqlsrv', ['only' => ['report', 'index']]);
    }

    public function index()
    {
        $articles = $this->article()
            ->where('article_havelogo', 1)
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

        $article->thumbnail_url = $this->addImagePrefixUrl($article->thumbnail_url);

        return (array) $article;
    }

    public function report()
    {
        return $this->dbRepository('sqlsrv', 'lanmu')
            ->select('lanmu_id as id', 'lanmu_name as name')
            ->where('lanmu_language', 'zh-cn')
            ->whereIn('lanmu_father', [113, 167, 168])
            ->get();
    }

}