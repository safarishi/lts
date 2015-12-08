<?php

namespace App\Http\Controllers;

use Input;
use Request;
use LucaDegasperi\OAuth2Server\Authorizer;
use App\Exceptions\DuplicateOperateException;

class CommentController extends CommonController
{
    public function __construct(Authorizer $authorizer)
    {
        parent::__construct($authorizer);
        $this->middleware('oauth', ['except' => ['anonymousReply']]);
        $this->middleware('validation');
    }

    private static $_validate = [
        'reply' => [
            'content' => 'required',
        ],
        'anonymousReply' => [
            'content' => 'required',
        ],
    ];

    /**
     * 评论回复
     *
     * @param  string $id        文章id
     * @param  string $commentId 评论id
     * @return todo
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
     * 评论匿名回复
     *
     * @param  string $id        文章id
     * @param  string $commentId 评论id
     * @return todo
     */
    public function anonymousReply($id, $commentId)
    {
        // 校验验证码
        MultiplexController::verifyCaptcha();

        $this->user = MultiplexController::anonymousUser(Request::ip());

        return $this->replyResponse($commentId);
    }

    /**
     * 评论点赞
     *
     * @param  string $id        文章id
     * @param  string $commentId 评论id
     * @return todo
     */
    public function favour($id, $commentId)
    {
        $uid = $this->authorizer->getResourceOwnerId();
        $this->user = $this->dbRepository('mongodb', 'user')
            ->select('display_name', 'avatar_url')
            ->find($uid);

        if ($this->checkUserFavour($uid, $commentId)) {
            throw new DuplicateOperateException('您已点赞！');
        }

        $this->models['article_comment']->where('_id', $commentId)
            ->push('favoured_user', [$uid], true);

        $this->reply = '赞了这条评论:)';
        $this->recordInformation($commentId);

        return $this->favourResponse($commentId);
    }

    /**
     * 评论取消点赞
     *
     * @param  string $id        文章id
     * @param  string $commentId 评论id
     * @return todo
     */
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
     * 赞返回数据
     *
     * @param  [type] $commentId [description]
     * @return [type]            [description]
     */
    protected function favourResponse($commentId)
    {
        $comment = $this->models['article_comment']->find($commentId);

        $favours = 0;
        if (array_key_exists('favoured_user', $comment)) {
            $favours = count($comment['favoured_user']);
        }

        return [
            'article_comment_id' => $commentId,
            'favours' => $favours,
        ];
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

        $user = $comment['user'];
        $unread = '0';
        if (array_key_exists('_id', $user)) {
            $unread = $user['_id']->{'$id'};
        }

        $insertData = array(
                'source'     => $this->user,
                'created_at' => date('Y-m-d H:i:s'),
                'unread'     => $unread,
                'content'    => array(
                        'reply'   => $this->reply,
                        'comment' => $comment,
                    ),
            );

        $this->dbRepository('mongodb', 'information')
            ->insert($insertData);
    }

}