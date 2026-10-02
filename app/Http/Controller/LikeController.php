<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Exception\ValidationException;
use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Security\IdentityManager;
use App\Service\LikeService;

class LikeController
{
    private LikeService $likeService;
    private IdentityManager $identityManager;

    public function __construct(LikeService $likeService, IdentityManager $identityManager)
    {
        $this->likeService = $likeService;
        $this->identityManager = $identityManager;
    }

    public function toggle(Request $request): Response
    {
        $targetType = (string)$request->get('target_type', '');
        $targetId = (int)$request->get('target_id', 0);

        if (empty($targetType) || $targetId <= 0) {
            throw new ValidationException("Target type ('post' or 'comment') and valid target_id are required.");
        }

        $identity = $this->identityManager->resolveIdentity($request);
        $result = $this->likeService->toggleLike($targetType, $targetId, $identity);

        return Response::success($result, $result['message']);
    }

    public function likePost(Request $request): Response
    {
        $id = (int)$request->getRouteParam('id');
        $identity = $this->identityManager->resolveIdentity($request);
        $result = $this->likeService->toggleLike('post', $id, $identity);

        return Response::success($result, $result['message']);
    }

    public function likeComment(Request $request): Response
    {
        $id = (int)$request->getRouteParam('id');
        $identity = $this->identityManager->resolveIdentity($request);
        $result = $this->likeService->toggleLike('comment', $id, $identity);

        return Response::success($result, $result['message']);
    }
}
