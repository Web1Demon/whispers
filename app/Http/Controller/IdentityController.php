<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Core\Request;
use App\Core\Response;
use App\Infrastructure\Security\IdentityManager;

class IdentityController
{
    private IdentityManager $identityManager;

    public function __construct(IdentityManager $identityManager)
    {
        $this->identityManager = $identityManager;
    }

    public function me(Request $request): Response
    {
        $identity = $this->identityManager->resolveIdentity($request);
        return Response::success($identity->toArray(true), 'Identity resolved');
    }

    public function refresh(Request $request): Response
    {
        $newIdentity = $this->identityManager->createNewIdentity();
        return Response::success($newIdentity->toArray(true), 'New anonymous identity generated');
    }
}
