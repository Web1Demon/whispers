<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Autoloader.php';

$autoloader = new \App\Core\Autoloader('App', __DIR__ . '/../app');
$autoloader->register();

$appConfig = require __DIR__ . '/../config/app.php';
$dbConfig = require __DIR__ . '/../config/database.php';

echo "\n======================================================\n";
echo "    WHISPER BACKEND ARCHITECTURE & SUITE TEST RUNNER    \n";
echo "    PHP Version: " . PHP_VERSION . " | Driver: " . $dbConfig['default'] . "\n";
echo "======================================================\n\n";

$app = new \App\Core\Application($appConfig, $dbConfig);
$container = $app->getContainer();

$passed = 0;
$failed = 0;

function assertTest(string $description, bool $condition, ?string $details = null): void
{
    global $passed, $failed;
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS:\033[0m {$description}\n";
    } else {
        $failed++;
        echo "  \033[31m✖ FAIL:\033[0m {$description}\n";
        if ($details) {
            echo "         \033[33mDetail: {$details}\033[0m\n";
        }
    }
}

try {
    // 1. Test Database Migration & Reset
    echo "[TEST GROUP 1: Database Migration & Schema Setup]\n";
    /** @var \App\Infrastructure\Database\Migrator $migrator */
    $migrator = $container->get(\App\Infrastructure\Database\Migrator::class);
    $migrator->reset();
    assertTest("Database migration and table creation executed cleanly", true);

    /** @var \App\Core\Database $db */
    $db = $container->get(\App\Core\Database::class);
    $tables = $db->query("SHOW TABLES");
    assertTest("MySQL Database contains generated tables", count($tables) >= 5, "Tables found: " . count($tables));

    // 2. Test Anonymous Identity Management
    echo "\n[TEST GROUP 2: Anonymous Cryptographic Identity]\n";
    /** @var \App\Infrastructure\Security\IdentityManager $identityMgr */
    $identityMgr = $container->get(\App\Infrastructure\Security\IdentityManager::class);
    $id1 = $identityMgr->createNewIdentity();
    assertTest("Identity has valid pseudonym format", str_contains($id1->getPseudonym(), '#'), "Pseudonym: " . $id1->getPseudonym());
    assertTest("Identity generates avatar color gradients", str_starts_with($id1->getAvatarGradientFrom(), '#') && str_starts_with($id1->getAvatarGradientTo(), '#'));

    $ownershipToken = $identityMgr->generateOwnershipToken('post', 'sample_uid_1', $id1->getFingerprint(), date('Y-m-d H:i:s'));
    assertTest("Ownership token verified successfully", $identityMgr->verifyOwnership($ownershipToken, $ownershipToken));
    assertTest("Invalid ownership token rejected", !$identityMgr->verifyOwnership('wrong_token', $ownershipToken));

    // 3. Test Post Creation & Feed Retrieval
    echo "\n[TEST GROUP 3: Post Service & Feeds]\n";
    /** @var \App\Service\PostService $postService */
    $postService = $container->get(\App\Service\PostService::class);

    $post1Result = $postService->createPost(
        "First Architectural Whisper",
        "Exploring DDD and Clean Architecture in modern PHP 8.5 backend systems.",
        "tech",
        $id1
    );
    assertTest("Post created with status code and ownership token", isset($post1Result['post']['id'], $post1Result['ownership_token']));
    $postId1 = (int)$post1Result['post']['id'];

    $id2 = $identityMgr->createNewIdentity();
    $post2Result = $postService->createPost(
        "Life Reflections",
        "Taking time to reflect on system architecture design decisions.",
        "life",
        $id2
    );
    $postId2 = (int)$post2Result['post']['id'];

    $feed = $postService->getFeed(1, 10, 'recent', null, null, $id1);
    assertTest("Feed returns created posts with pagination", count($feed['items']) === 2 && $feed['total'] === 2);

    $post1Item = null;
    $post2Item = null;
    foreach ($feed['items'] as $item) {
        if ($item['id'] === $postId1) $post1Item = $item;
        if ($item['id'] === $postId2) $post2Item = $item;
    }

    assertTest("Author viewer state correctly flagged as owner for post 1", $post1Item !== null && $post1Item['viewer_state']['is_owner'] === true);
    assertTest("Non-author viewer state correctly flagged as non-owner for post 2", $post2Item !== null && $post2Item['viewer_state']['is_owner'] === false);

    $techFeed = $postService->getFeed(1, 10, 'recent', 'tech', null, $id1);
    assertTest("Category filter works accurately", count($techFeed['items']) === 1 && $techFeed['items'][0]['category'] === 'tech');

    // 4. Test Nested / Threaded Comments Tree (Comment on a Comment)
    echo "\n[TEST GROUP 4: Hierarchical Comments & Infinite Tree Structure]\n";
    /** @var \App\Service\CommentService $commentService */
    $commentService = $container->get(\App\Service\CommentService::class);

    // Root Comment (Level 0)
    $c1Result = $commentService->addComment($postId1, null, "Great insights on clean architecture!", $id2);
    $c1Id = (int)$c1Result['comment']['id'];
    assertTest("Root comment created on post (Depth 0)", $c1Result['comment']['depth'] === 0);

    // Reply to Comment 1 (Level 1)
    $c2Result = $commentService->addComment($postId1, $c1Id, "I especially like the repository pattern separation.", $id1);
    $c2Id = (int)$c2Result['comment']['id'];
    assertTest("Reply created to parent comment (Depth 1)", $c2Result['comment']['depth'] === 1 && $c2Result['comment']['parent_id'] === $c1Id);

    // Reply to Reply (Level 2: Comment on a Comment)
    $id3 = $identityMgr->createNewIdentity();
    $c3Result = $commentService->addComment($postId1, $c2Id, "Don't forget the outbox pattern for distributed messaging.", $id3);
    assertTest("Sub-reply created on nested comment (Depth 2)", $c3Result['comment']['depth'] === 2 && $c3Result['comment']['parent_id'] === $c2Id);

    // Fetch and verify full tree hierarchy
    $tree = $commentService->getCommentsTree($postId1, $id1);
    assertTest("Tree returns single root comment with nested children", count($tree) === 1);
    assertTest("Root comment contains nested child reply", count($tree[0]['children']) === 1 && $tree[0]['children'][0]['id'] === $c2Id);
    assertTest("Child reply contains sub-nested reply (Level 2)", count($tree[0]['children'][0]['children']) === 1 && $tree[0]['children'][0]['children'][0]['id'] === $c3Result['comment']['id']);

    // Check post comments counter
    $fetchedPost = $postService->getPost($postId1, $id1, false);
    assertTest("Post comments_count updated atomically", $fetchedPost->getCommentsCount() === 3, "Count: " . $fetchedPost->getCommentsCount());

    // 5. Test Polymorphic Likes & Idempotency
    echo "\n[TEST GROUP 5: Polymorphic Likes & Idempotency]\n";
    /** @var \App\Service\LikeService $likeService */
    $likeService = $container->get(\App\Service\LikeService::class);

    // Like Post
    $likePost1 = $likeService->toggleLike('post', $postId1, $id2);
    assertTest("Liking a post returns is_liked = true and count = 1", $likePost1['is_liked'] === true && $likePost1['likes_count'] === 1);

    // Unlike Post (Idempotent toggle)
    $unlikePost1 = $likeService->toggleLike('post', $postId1, $id2);
    assertTest("Unliking the post returns is_liked = false and count = 0", $unlikePost1['is_liked'] === false && $unlikePost1['likes_count'] === 0);

    // Re-like Post
    $likeService->toggleLike('post', $postId1, $id2);

    // Like Comment (Polymorphic)
    $likeComment1 = $likeService->toggleLike('comment', $c1Id, $id1);
    assertTest("Liking a comment works via polymorphic target", $likeComment1['is_liked'] === true && $likeComment1['likes_count'] === 1);

    // 6. Test Rate Limiting
    echo "\n[TEST GROUP 6: Sliding-Window Rate Limiter]\n";
    /** @var \App\Infrastructure\Security\RateLimiter $rateLimiter */
    $rateLimiter = $container->get(\App\Infrastructure\Security\RateLimiter::class);
    $testFp = "rate_limit_test_fingerprint";
    $exceptionCaught = false;

    try {
        for ($i = 0; $i < 15; $i++) {
            $rateLimiter->check('posts', $testFp);
        }
    } catch (\App\Core\Exception\RateLimitException $e) {
        $exceptionCaught = true;
    }
    assertTest("Rate limiter successfully blocked excessive requests", $exceptionCaught);

    // 7. Test Content Validation & Anti-Spam
    echo "\n[TEST GROUP 7: Content Moderation & Anti-Spam]\n";
    /** @var \App\Infrastructure\Security\ContentModerator $moderator */
    $moderator = $container->get(\App\Infrastructure\Security\ContentModerator::class);
    $validationCaught = false;

    try {
        $moderator->validatePost("ab", "short", "tech");
    } catch (\App\Core\Exception\ValidationException $e) {
        $validationCaught = true;
    }
    assertTest("Validation rejects post with title below minimum length", $validationCaught);

    // 8. Test HTTP Request & Controller Dispatching
    echo "\n[TEST GROUP 8: HTTP Pipeline & Full Controller Dispatch]\n";
    $req = new \App\Core\Request('GET', '/api/posts', ['limit' => 5], [], ['HTTP_ACCEPT' => 'application/json']);
    $res = $app->handle($req);
    assertTest("HTTP GET /api/posts returns 200 OK", $res->getStatusCode() === 200);

    $healthReq = new \App\Core\Request('GET', '/api/health');
    $healthRes = $app->handle($healthReq);
    assertTest("HTTP GET /api/health returns 200 OK", $healthRes->getStatusCode() === 200);

    $statsReq = new \App\Core\Request('GET', '/api/stats');
    $statsRes = $app->handle($statsReq);
    assertTest("HTTP GET /api/stats returns system telemetry", $statsRes->getStatusCode() === 200);

    // 9. Re-seed with rich sample data for demonstration
    /** @var \App\Infrastructure\Database\DatabaseSeeder $seeder */
    $seeder = $container->get(\App\Infrastructure\Database\DatabaseSeeder::class);
    $migrator->reset();
    $seeder->seed();
    assertTest("Database successfully re-seeded with realistic production data", true);

} catch (\Throwable $e) {
    $failed++;
    echo "\n\033[31mFATAL TEST ERROR: " . $e->getMessage() . "\033[0m\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n======================================================\n";
echo "    TEST SUMMARY: \033[32m{$passed} Passed\033[0m | \033[31m{$failed} Failed\033[0m\n";
echo "======================================================\n\n";

if ($failed > 0) {
    exit(1);
}
