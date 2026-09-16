<?php

declare(strict_types=1);

namespace Indieinabox\Commands\Handlers;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Commands\CreatePostCommand;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Events\Contracts\EventDispatcherInterface;
use Indieinabox\Events\PostPublishedEvent;
use Indieinabox\Micropub\PostCreator;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Services\OutboxService;
use Indieinabox\Support\TextParser;

/**
 * Handler for CreatePostCommand.
 */
class CreatePostCommandHandler
{
    private ?ContentRepositoryInterface $contentRepo;
    private ?EventDispatcherInterface $eventDispatcher;
    private ?OutboxService $outboxService;

    public function __construct(
        ?ContentRepositoryInterface $contentRepo = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        ?OutboxService $outboxService = null
    ) {
        $this->contentRepo = $contentRepo;

        $container = class_exists(Container::class) ? Container::getInstance() : null;

        if ($eventDispatcher !== null) {
            $this->eventDispatcher = $eventDispatcher;
        } elseif ($container && $container->has(EventDispatcherInterface::class)) {
            $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        } else {
            $this->eventDispatcher = null;
        }

        if ($outboxService !== null) {
            $this->outboxService = $outboxService;
        } elseif ($container && $container->has(OutboxService::class)) {
            $this->outboxService = $container->get(OutboxService::class);
        } else {
            $this->outboxService = null;
        }
    }

    /**
     * @param CreatePostCommand $command
     * @return array{status: int, headers: array<string, string>, post_url: string, file_path: string, kind: string, slug: string}
     */
    public function handle(CreatePostCommand $command): array
    {
        $site = $command->getSite();
        $input = $command->getInput();
        $contentRepo = $this->contentRepo ?? new FileSystemContentRepository(null, $site);

        $name = isset($input['name']) && $input['name'] !== '' ? (string) $input['name'] : null;
        $content = $input['content'] ?? '';
        if (is_array($content)) {
            $content = (string) ($content['html'] ?? ($content['value'] ?? ''));
        } else {
            $content = (string) $content;
        }

        $slug = (string) ($input['mp-slug'] ?? ($name !== null ? PostCreator::slugify($name) : date('dHis')));
        $lang = (string) ($input['mp-language'] ?? '');
        $category = $input['category'] ?? [];
        if (!is_array($category) && !empty($category)) {
            $category = [$category];
        }

        // Auto-extract hashtags
        $extractedTags = TextParser::extractHashtags($content);
        if (!empty($extractedTags)) {
            $category = array_unique(array_merge($category, $extractedTags));
        }

        // Photo uploads
        $photos = [];
        if (isset($input['photo'])) {
            $photos = is_array($input['photo']) ? $input['photo'] : [$input['photo']];
        }

        // Discover post kind
        $kind = PostCreator::discoverPostType($input, $photos);

        // Build frontmatter
        $frontmatter = [];
        if ($name !== null) {
            $frontmatter['title'] = $name;
        }
        $frontmatter['date'] = date('Y-m-d H:i:s');
        if (!empty($category)) {
            $frontmatter['tags'] = array_values($category);
        }

        foreach (array_keys(PostCreator::INDIEWEB_PROPERTIES) as $prop) {
            if (isset($input[$prop])) {
                $frontmatter[str_replace('-', '_', $prop)] = $input[$prop];
            }
        }

        $otherProps = ['read-status', 'rating', 'p-rating', 'syndicate-to', 'mp-syndicate-to'];
        foreach ($otherProps as $op) {
            if (isset($input[$op])) {
                $frontmatter[str_replace('-', '_', $op)] = $input[$op];
            }
        }

        $body = $content;
        foreach ($photos as $photo) {
            if (is_string($photo) && strpos($body, $photo) === false) {
                $body .= "\n\n![]($photo)\n\n";
            }
        }

        // Persist using ContentRepository
        $year = date('Y');
        $month = date('m');
        $uniqueSlug = $contentRepo->generateUniqueSlug($kind, $slug, $lang, $year, $month);
        $filePath = $contentRepo->save($kind, $uniqueSlug, $body, $frontmatter, $lang, $year, $month);

        // Queue build
        try {
            $db = Database::getDb();
            if ($db) {
                $stmt = $db->query("SELECT 1 FROM inbox_queue WHERE type = 'build_site'");
                if ($stmt && !$stmt->fetch()) {
                    $insert = $db->prepare('INSERT INTO inbox_queue (type, payload_json, created_at) VALUES (?, ?, ?)');
                    $insert->execute(['build_site', json_encode([]), time()]);
                }
            }
        } catch (\Throwable) {
        }

        // Construct Canonical URL
        $baseUrl = rtrim($site->fqdn ?? '', '/');
        $defaultLang = $site->localization->defaultLang ?? 'en';
        $postUrl = $baseUrl . '/' . $kind . '/' . $year . '/' . $month . '/' . $uniqueSlug . '.html';
        if ($lang !== '' && $lang !== $defaultLang) {
            $postUrl = $baseUrl . '/' . $lang . '/' . $kind . '/' . $year . '/' . $month . '/' . $uniqueSlug . '.html';
        }

        // ActivityPub federation
        if ($this->outboxService !== null || class_exists(OutboxService::class)) {
            $outbox = $this->outboxService ?? new OutboxService();
            $actorId = $baseUrl . '/actor';
            $object = ActivityBuilder::buildObjectForPageArray(
                $postUrl,
                $actorId,
                $baseUrl,
                $content,
                $name,
                $frontmatter
            );
            $createActivity = ActivityBuilder::buildCreateActivity(
                $postUrl . '#activity',
                $actorId,
                $object
            );
            $outbox->broadcastActivity($createActivity);
        }

        // Queue outgoing webmentions
        if (class_exists(\Indieinabox\Webmention\WebmentionSender::class)) {
            \Indieinabox\Webmention\WebmentionSender::queueOutgoingWebmentions($postUrl, $frontmatter, $content);
        }

        // Dispatch domain event
        if ($this->eventDispatcher !== null) {
            $this->eventDispatcher->dispatch(new PostPublishedEvent(
                '/' . $kind . '/' . $year . '/' . $month . '/' . $uniqueSlug,
                $filePath,
                $kind,
                $name,
                $body,
                $photos
            ));
        }

        return [
            'status' => 202,
            'headers' => ['Location' => $postUrl],
            'post_url' => $postUrl,
            'file_path' => $filePath,
            'kind' => $kind,
            'slug' => $uniqueSlug,
        ];
    }
}
