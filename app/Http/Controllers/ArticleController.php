<?php

namespace App\Http\Controllers;

class ArticleController extends CommonController
{
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

    public function report()
    {
        // todo
    }
}