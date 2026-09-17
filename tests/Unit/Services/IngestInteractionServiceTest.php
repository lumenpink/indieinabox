<?php

declare(strict_types=1);

use DateTimeImmutable;
use Indieinabox\Core\Container;
use Indieinabox\DTO\InteractionDto;
use Indieinabox\Events\EventDispatcher;
use Indieinabox\Events\WebmentionReceivedEvent;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Services\Contracts\IngestInteractionServiceInterface;
use Indieinabox\Services\IngestInteractionService;

beforeEach(function () {
    /** @var \Tests\TestCase $this */
    $this->tempDir = sys_get_temp_dir() . "/iiab_ingest_" . uniqid();
    mkdir($this->tempDir);

    $this->repo = new FileInteractionRepository($this->tempDir);
    $this->dispatcher = new EventDispatcher();
    $this->service = new IngestInteractionService($this->repo, $this->dispatcher);
});

afterEach(function () {
    /** @var \Tests\TestCase $this */
    exec("rm -rf " . escapeshellarg($this->tempDir));
});

test("InteractionDto builds from Webmention payload correctly", function () {
    $verified = [
        "author_name" => "Alice Dev",
        "author_url" => "https://alice.example.com",
        "author_photo" => "https://alice.example.com/avatar.jpg",
        "text" => "Loved this article on DDD!",
        "interaction_type" => "reply",
        "whostyle" => ["theme" => "dark"],
    ];

    $dto = InteractionDto::fromWebmention(
        "https://alice.example.com/post/1",
        "https://mysite.example.com/article/2026/09/ddd.html",
        $verified
    );

    expect($dto->getAuthorName())->toBe("Alice Dev");
    expect($dto->getAuthorUrl())->toBe("https://alice.example.com");
    expect($dto->getAuthorPhoto())->toBe("https://alice.example.com/avatar.jpg");
    expect($dto->getContent())->toBe("Loved this article on DDD!");
    expect($dto->getType())->toBe("reply");
    expect($dto->getProtocol())->toBe("webmention");
    expect($dto->getStatus())->toBe("pending");
    expect($dto->getMetadata()["whostyle"])->toBe(["theme" => "dark"]);

    $array = $dto->toArray();
    expect($array["type parody"] ?? $array["type"])->toBe("reply");
    expect($array["author_name"])->toBe("Alice Dev");
    expect($array["published"])->toBeGreaterThan(0);
});

test("InteractionDto builds from ActivityPub activities correctly", function () {
    // Like activity
    $likeActivity = [
        "id" => "https://remote.social/activity/like/1",
        "type" => "Like",
        "actor" => "https://remote.social/users/bob",
        "object" => "https://mysite.example.com/note/2026/09/hello.html",
    ];
    $actorData = [
        "name" => "Bob Builder",
        "icon" => ["url" => "https://remote.social/bob.png"],
    ];

    $likeDto = InteractionDto::fromActivityPub($likeActivity, $actorData);
    expect($likeDto)->not->toBeNull();
    expect($likeDto->getType())->toBe("like");
    expect($likeDto->getProtocol())->toBe("activitypub");
    expect($likeDto->getAuthorName())->toBe("Bob Builder");
    expect($likeDto->getAuthorPhoto())->toBe("https://remote.social/bob.png");
    expect($likeDto->getTarget())->toBe("https://mysite.example.com/note/2026/09/hello.html");

    // Announce (repost) activity
    $announceActivity = [
        "id" => "https://remote.social/activity/boost/1",
        "type" => "Announce",
        "actor" => "https://remote.social/users/bob",
        "object" => "https://mysite.example.com/article/2026/09/ddd.html",
    ];
    $announceDto = InteractionDto::fromActivityPub($announceActivity);
    expect($announceDto->getType())->toBe("repost");

    // Create (reply) activity
    $createReply = [
        "id" => "https://remote.social/activity/reply/1",
        "type" => "Create",
        "actor" => "https://remote.social/users/bob",
        "object" => [
            "id" => "https://remote.social/notes/123",
            "type" => "Note",
            "inReplyTo" => "https://mysite.example.com/article/2026/09/ddd.html",
            "content" => "<p>Fascinating post!</p>",
            "published" => "2026-09-17T00:00:00Z",
        ],
    ];
    $replyDto = InteractionDto::fromActivityPub($createReply);
    expect($replyDto->getType())->toBe("reply");
    expect($replyDto->getContent())->toBe("<p>Fascinating post!</p>");
    expect($replyDto->getTarget())->toBe("https://mysite.example.com/article/2026/09/ddd.html");
});

test("InteractionDto supports fromArray and toArray roundtrip", function () {
    $now = new DateTimeImmutable();
    $dto = new InteractionDto(
        "custom_id_123",
        "https://example.com/post",
        "https://remote.com/mention",
        "like",
        "webmention",
        "Carol",
        "https://remote.com/carol",
        null,
        "Great job!",
        $now,
        "approved",
        ["key" => "val"]
    );

    $array = $dto->toArray();
    $restored = InteractionDto::fromArray($array);

    expect($restored->getId())->toBe("custom_id_123");
    expect($restored->getTarget())->toBe("https://example.com/post");
    expect($restored->getType())->toBe("like");
    expect($restored->getStatus())->toBe("approved");
    expect($restored->getAuthorName())->toBe("Carol");
});

test("IngestInteractionService ingests Webmention and dispatches WebmentionReceivedEvent", function () {
    /** @var \Tests\TestCase $this */
    $eventDispatched = false;
    $dispatchedSource = "";

    $this->dispatcher->listen(WebmentionReceivedEvent::class, function (WebmentionReceivedEvent $event) use (&$eventDispatched, &$dispatchedSource) {
        $eventDispatched = true;
        $dispatchedSource = $event->getSource();
    });

    $dto = $this->service->ingestWebmention(
        "https://source.example.com/post/99",
        "https://mysite.example.com/article/2026/09/post.html",
        [
            "author_name" => "Dave Tester",
            "text" => "Mentioning your post here.",
            "interaction_type" => "reply",
        ],
        "approved"
    );

    expect($eventDispatched)->toBeTrue();
    expect($dispatchedSource)->toBe("https://source.example.com/post/99");

    // Verify stored in repository
    $targetSlug = "article/2026/09/post.html";
    $interactions = $this->repo->findByPageSlug($targetSlug);
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]["author_name"])->toBe("Dave Tester");
    expect($interactions[0]["interaction_type"])->toBe("reply");
});

test("IngestInteractionService ingests ActivityPub activity and handles spam channel", function () {
    /** @var \Tests\TestCase $this */
    // Normal activity
    $activity = [
        "id" => "https://mastodon.social/users/eve/statuses/1",
        "type" => "Like",
        "actor" => "https://mastodon.social/users/eve",
        "object" => "https://mysite.example.com/note/2026/09/test.html",
    ];

    $dto = $this->service->ingestActivity($activity, null, "approved");
    expect($dto)->not->toBeNull();

    $interactions = $this->repo->findByPageSlug("note/2026/09/test.html");
    expect($interactions)->toHaveCount(1);
    expect($interactions[0]["type"])->toBe("activitypub");

    // Spam activity
    $spamActivity = [
        "id" => "https://spammer.social/activity/1",
        "type" => "Like",
        "actor" => "https://spammer.social/users/bot",
        "object" => "https://mysite.example.com/note/2026/09/test.html",
    ];
    $spamDto = $this->service->ingestActivity($spamActivity, null, "spam");
    expect($spamDto->getStatus())->toBe("spam");

    $spamInteractions = $this->repo->listByStatus("spam");
    expect($spamInteractions)->toHaveCount(1);
    expect($spamInteractions[0]["source"])->toBe("https://spammer.social/users/bot");
});

test("IngestInteractionServiceInterface is registered in Container", function () {
    $container = Container::getInstance();
    expect($container->has(IngestInteractionServiceInterface::class))->toBeTrue();
    $service = $container->get(IngestInteractionServiceInterface::class);
    expect($service)->toBeInstanceOf(IngestInteractionService::class);
});
