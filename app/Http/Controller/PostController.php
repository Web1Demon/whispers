<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Security\IdentityManager;
use App\Service\CommentService;
use App\Service\PostService;

class PostController
{
    private PostService $postService;
    private CommentService $commentService;
    private IdentityManager $identityManager;

    public function __construct(
        PostService $postService,
        CommentService $commentService,
        IdentityManager $identityManager
    ) {
        $this->postService = $postService;
        $this->commentService = $commentService;
        $this->identityManager = $identityManager;
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int)$request->getQuery('page', 1));
        $limit = min(50, max(1, (int)$request->getQuery('limit', 15)));
        $sort = (string)$request->getQuery('sort', 'recent');
        $category = (string)$request->getQuery('category', '');
        $search = (string)$request->getQuery('q', '');

        $identity = $this->identityManager->resolveIdentity($request);

        $result = $this->postService->getFeed(
            $page,
            $limit,
            $sort,
            $category !== '' ? $category : null,
            $search !== '' ? $search : null,
            $identity
        );

        return Response::paginate($result['items'], $page, $limit, $result['total'], [
            'sort' => $sort,
            'category' => $category ?: 'all',
            'search' => $search,
        ]);
    }

    public function show(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $identity = $this->identityManager->resolveIdentity($request);

        $post = $this->postService->getPost($id, $identity, true);
        $commentsTree = $this->commentService->getCommentsTree($post->getId(), $identity);

        $postData = $post->toArray();
        $postData['comments'] = $commentsTree;

        return Response::success($postData, 'Whisper retrieved successfully');
    }

    public function create(Request $request): Response
    {
        $title = (string)$request->get('title', '');
        $content = (string)$request->get('content', '');
        $category = (string)$request->get('category', 'general');

        $identity = $this->identityManager->resolveIdentity($request);

        $result = $this->postService->createPost($title, $content, $category, $identity);

        return Response::success($result, 'Whisper created successfully', 201);
    }

    public function delete(Request $request): Response
    {
        $id = (int)$request->getRouteParam('id');
        $identity = $this->identityManager->resolveIdentity($request);
        $ownershipToken = $request->getOwnershipToken();

        $this->postService->deletePost($id, $ownershipToken, $identity);

        return Response::success(null, 'Whisper deleted successfully');
    }
}
