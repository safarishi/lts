<?php

namespace App\Http\Controllers;

use App\Exceptions\ValidationException;

class ArticleV1Controller extends ArticleController
{
    public function show($id)
    {
        $article = $this->getArticleById($id);

        if ($article === null) {
            throw new ValidationException('文章参数传递错误:(');
        }

        $tmp = clone $article;
        unset($tmp->content);
        $this->tmpData = $tmp;

        // 处理文章内容里面的图片
        $tmpContent = str_replace('&#34;', '"', $article->content);
        $article->content = preg_replace('#(src=")/#', "\$1".'http://sisi-smu.org/', $tmpContent);
        $article->thumbnail_url = $this->addImagePrefixUrl($article->thumbnail_url);
        // 是否收藏文章
        $article->is_starred = $this->isStarred($id);
        // 相关文章
        $this->origin = $article->origin;
        $relatedArticles = $this->getRelatedArticles($id);
        // 热门评论
        $hotComments = $this->getHotComments($id);

        return [
            'article' => $article,
            'releated_articles' => $relatedArticles,
            'hot_comments' => $hotComments,
        ];
    }

    /**
     * 文章是否收藏
     *
     * @param  string  $id 文章id
     * @return boolean
     */
    protected function isStarred($id)
    {
        if (!$this->accessToken) {
            return false;
        }

        $uid = $this->getOwnerId();

        return $this->checkUserStar($uid, $id);
    }

    protected function getRelatedArticles($id)
    {
        $articleIdArr = $this->article()
            ->where('article_writer', $this->origin)
            ->where('article_id', '<>', $id)
            ->latest('article_addtime')
            ->take(2)
            ->lists('id');

        $articles = [];
        foreach ($articleIdArr as $articleId) {
            $articles[] = $this->getArticleBriefById($articleId);
        }

        return $articles;
    }

}