<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Exception\NotFoundException;
use App\Core\Request;
use App\Core\Response;
use App\Domain\Repository\CommentRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Infrastructure\Security\IdentityManager;
use App\Service\CommentService;

class CommentController
{
    private CommentService $commentService;
    private PostRepositoryInterface $postRepo;
    private CommentRepositoryInterface $commentRepo;
    private IdentityManager $identityManager;

    public function __construct(
        CommentService $commentService,
        PostRepositoryInterface $postRepo,
        CommentRepositoryInterface $commentRepo,
        IdentityManager $identityManager
    ) {
        $this->commentService = $commentService;
        $this->postRepo = $postRepo;
        $this->commentRepo = $commentRepo;
        $this->identityManager = $identityManager;
    }

    public function create(Request $request): Response
    {
        $postId = (int)$request->getRouteParam('postId');
        $parentId = $request->get('parent_id') !== null ? (int)$request->get('parent_id') : null;
        $content = (string)$request->get('content', '');

        $identity = $this->identityManager->resolveIdentity($request);

        $result = $this->commentService->addComment($postId, $parentId, $content, $identity);

        return Response::success($result, 'Comment added successfully', 201);
    }

    public function reply(Request $request): Response
    {
        $commentId = (int)$request->getRouteParam('id');
        $content = (string)$request->get('content', '');

        $parentComment = $this->commentRepo->findById($commentId);
        if ($parentComment === null || $parentComment->isDeleted()) {
            throw new NotFoundException("Comment not found or has been removed.");
        }

        $identity = $this->identityManager->resolveIdentity($request);

        $result = $this->commentService->addComment($parentComment->getPostId(), $commentId, $content, $identity);

        return Response::success($result, 'Reply added successfully', 201);
    }

    public function delete(Request $request): Response
    {
        $id = (int)$request->getRouteParam('id');
        $identity = $this->identityManager->resolveIdentity($request);
        $ownershipToken = $request->getOwnershipToken();

        $this->commentService->deleteComment($id, $ownershipToken, $identity);

        return Response::success(null, 'Comment deleted successfully');
    }
}
