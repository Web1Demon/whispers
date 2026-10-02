<?php

declare(strict_types=1);

namespace App\Infrastructure\Database;

use App\Core\Database;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Identity;
use App\Domain\Entity\Like;
use App\Domain\Entity\Post;
use App\Domain\Repository\CommentRepositoryInterface;
use App\Domain\Repository\LikeRepositoryInterface;
use App\Domain\Repository\PostRepositoryInterface;
use App\Domain\ValueObject\Pseudonym;
use App\Domain\ValueObject\TargetType;

class DatabaseSeeder
{
    private PostRepositoryInterface $postRepo;
    private CommentRepositoryInterface $commentRepo;
    private LikeRepositoryInterface $likeRepo;
    private Database $db;

    public function __construct(
        PostRepositoryInterface $postRepo,
        CommentRepositoryInterface $commentRepo,
        LikeRepositoryInterface $likeRepo,
        Database $db
    ) {
        $this->postRepo = $postRepo;
        $this->commentRepo = $commentRepo;
        $this->likeRepo = $likeRepo;
        $this->db = $db;
    }

    public function seed(): void
    {
        // Only seed if empty
        if ($this->postRepo->countFeed() > 0) {
            return;
        }

        $samplePosts = [
            [
                'title' => 'I secretly refactored our legacy monolith into event-driven microservices during the weekend',
                'content' => "Our team was struggling with 45-minute deployment pipelines and merge conflicts on a 10-year old monolith. Last weekend, I stayed up with 4 cups of pour-over coffee, isolated the payment & notification domains, and hooked them up to an asynchronous event broker.\n\nMonday morning tests passed with 99.4% coverage and deploy times dropped to 3 minutes. My tech lead thought AWS optimized our pipeline automatically.",
                'category' => 'tech',
                'likes' => 24,
                'views' => 142,
            ],
            [
                'title' => 'Sometimes I pretend my internet disconnected just to take a peaceful 15-minute walk',
                'content' => "Working remotely in a fast-paced environment can get overwhelming. Whenever back-to-back synchronous meetings drain my creative energy, I flip airplane mode on my laptop, step into the garden, take deep breaths, and let my brain reset.\n\nBest mental health hack of my career.",
                'category' => 'confessions',
                'likes' => 58,
                'views' => 310,
            ],
            [
                'title' => 'Why writing clean backend architecture is like composing classical music',
                'content' => "Every layer in a clean architecture has a distinct rhythm. The Domain entities define the melody; repositories act as the brass section handling the heavy data orchestration; the HTTP controllers simply conduct the orchestra without playing the instruments themselves.\n\nWhen SOLID principles and decoupling are respected, scaling the system feels effortless and harmonious.",
                'category' => 'deep-thoughts',
                'likes' => 37,
                'views' => 189,
            ],
            [
                'title' => 'The junior developer pushed directly to main, and it actually fixed a bug we investigated for 3 weeks',
                'content' => "We spent 3 sprints analyzing race conditions in our distributed locking mechanism. A new junior dev accidentally merged a PR removing an obsolete synchronized mutex block. The deadlock instantly vanished. We gave them the sprint MVP award!",
                'category' => 'humor',
                'likes' => 89,
                'views' => 520,
            ],
            [
                'title' => 'What is the best piece of career advice you ever received?',
                'content' => "For me, it was: 'Don't fall in love with your code; fall in love with the problem you are solving for real humans.' What advice changed your perspective?",
                'category' => 'life',
                'likes' => 45,
                'views' => 260,
            ],
        ];

        foreach ($samplePosts as $postData) {
            $pseudonymVO = Pseudonym::random();
            $fingerprint = hash('sha256', bin2hex(random_bytes(16)));
            $uid = bin2hex(random_bytes(6));
            $now = date('Y-m-d H:i:s', time() - random_int(3600, 86400 * 3));

            $post = new Post(
                null,
                $uid,
                $postData['title'],
                $postData['content'],
                $postData['category'],
                $pseudonymVO->getName(),
                $pseudonymVO->getGradientFrom(),
                $pseudonymVO->getGradientTo(),
                $fingerprint,
                hash('sha256', "post_{$uid}_{$fingerprint}"),
                $postData['likes'],
                0,
                $postData['views'],
                0.0,
                false,
                false,
                $now,
                $now
            );

            $createdPost = $this->postRepo->create($post);

            // Add sample likes
            for ($i = 0; $i < min(5, $postData['likes']); $i++) {
                $likerFp = hash('sha256', "liker_{$createdPost->getId()}_{$i}");
                $this->likeRepo->create(new Like(null, TargetType::POST, $createdPost->getId(), $likerFp));
            }

            // Seed nested comments for the first few posts
            if ($createdPost->getId() === 1) {
                // Root comment 1
                $c1Pseudonym = Pseudonym::random();
                $c1 = new Comment(
                    null,
                    $createdPost->getId(),
                    null,
                    0,
                    '',
                    "You're a legend! What message queue or event bus did you choose for the decoupling?",
                    $c1Pseudonym->getName(),
                    $c1Pseudonym->getGradientFrom(),
                    $c1Pseudonym->getGradientTo(),
                    hash('sha256', "c1_fp"),
                    hash('sha256', "c1_owner"),
                    8,
                    0,
                    false,
                    date('Y-m-d H:i:s', time() - 7200),
                    date('Y-m-d H:i:s', time() - 7200)
                );
                $savedC1 = $this->commentRepo->create($c1);
                $this->postRepo->updateCounters($createdPost->getId(), 0, 1);

                // Nested reply to comment 1 (Level 1)
                $c2Pseudonym = Pseudonym::random();
                $c2 = new Comment(
                    null,
                    $createdPost->getId(),
                    $savedC1->getId(),
                    1,
                    '',
                    "I used an asynchronous transactional outbox with RabbitMQ and Redis streams for instantaneous pub/sub.",
                    $c2Pseudonym->getName(),
                    $c2Pseudonym->getGradientFrom(),
                    $c2Pseudonym->getGradientTo(),
                    hash('sha256', "c2_fp"),
                    hash('sha256', "c2_owner"),
                    5,
                    0,
                    false,
                    date('Y-m-d H:i:s', time() - 3600),
                    date('Y-m-d H:i:s', time() - 3600)
                );
                $savedC2 = $this->commentRepo->create($c2);
                $this->postRepo->updateCounters($createdPost->getId(), 0, 1);

                // Sub-nested reply to reply (Level 2: comment on a comment)
                $c3Pseudonym = Pseudonym::random();
                $c3 = new Comment(
                    null,
                    $createdPost->getId(),
                    $savedC2->getId(),
                    2,
                    '',
                    "Transactional Outbox pattern is gold! Zero dual-write inconsistencies. Bravo!",
                    $c3Pseudonym->getName(),
                    $c3Pseudonym->getGradientFrom(),
                    $c3Pseudonym->getGradientTo(),
                    hash('sha256', "c3_fp"),
                    hash('sha256', "c3_owner"),
                    3,
                    0,
                    false,
                    date('Y-m-d H:i:s', time() - 1800),
                    date('Y-m-d H:i:s', time() - 1800)
                );
                $this->commentRepo->create($c3);
                $this->postRepo->updateCounters($createdPost->getId(), 0, 1);
            }
        }

        $this->postRepo->recalculateHotScores();
    }
}
