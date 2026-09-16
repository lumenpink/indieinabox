<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Events\Contracts\EventDispatcherInterface;
use Indieinabox\Events\PostPublishedEvent;
use Indieinabox\Repositories\Contracts\ContentRepositoryInterface;
use Indieinabox\Repositories\FileSystemContentRepository;
use Indieinabox\Site\Site;
use Indieinabox\SiteBuilder\SiteBuilder;

/**
 * Protocol-agnostic service to compose, persist, build, and syndicate new posts and notes.
 */
class PublishPostService
{
    private Site $site;
    private ?OutboxService $outboxService;
    private ?EventDispatcherInterface $events;
    private ContentRepositoryInterface $contentRepo;

    public function __construct(
        Site $site,
        ?OutboxService $outboxService = null,
        ?EventDispatcherInterface $events = null,
        ?ContentRepositoryInterface $contentRepo = null
    ) {
        $this->site = $site;
        $this->outboxService = $outboxService;
        $this->events = $events;

        if ($this->events === null && class_exists(Container::class) && Container::getInstance()->has(EventDispatcherInterface::class)) {
            $this->events = Container::getInstance()->get(EventDispatcherInterface::class);
        }

        if ($contentRepo !== null) {
            $this->contentRepo = $contentRepo;
        } else {
            $container = class_exists(Container::class) ? Container::getInstance() : null;
            if ($container && $container->has(ContentRepositoryInterface::class)) {
                $this->contentRepo = $container->get(ContentRepositoryInterface::class);
            } else {
                $this->contentRepo = new FileSystemContentRepository(null, $this->site);
            }
        }
    }

    /**
     * Publishes a note or article and triggers static build and federation delivery.
     *
     * @param string $text Content body of the post
     * @param array<int, string> $mediaPaths List of local media paths
     * @param string $kind 'note' or 'article'
     * @param string|null $title Optional title (for articles)
     * @return array{slug: string, filepath: string}
     */
    public function publish(
        string $text,
        array $mediaPaths = [],
        string $kind = 'note',
        ?string $title = null
    ): array {
        $date = date('Y-m-d-H-i-s');
        $subfolder = $kind === 'article' ? 'articles' : 'notes';

        $frontmatter = [];
        if ($title !== null && $title !== '') {
            $frontmatter['title'] = $title;
            $frontmatter['date'] = date('Y-m-d H:i:s');
        }

        $body = $text;

        if (!empty($mediaPaths)) {
            $body .= "\n\n";
            foreach ($mediaPaths as $mp) {
                if (preg_match('/\.(mp4|webm|mov)$/i', $mp)) {
                    $body .= "<video src=\"{$mp}\" controls></video>\n";
                } elseif (preg_match('/\.(mp3|ogg|wav)$/i', $mp)) {
                    $body .= "<audio src=\"{$mp}\" controls></audio>\n";
                } else {
                    $body .= "![]({$mp})\n";
                }
            }
        }

        $postPath = $this->contentRepo->save($subfolder, $date, $body, $frontmatter);

        // Rebuild static site
        $builder = new SiteBuilder($this->site);
        $builder->build();

        $slug = '/' . $subfolder . '/' . $date;

        // Dispatch domain event
        if ($this->events !== null) {
            $this->events->dispatch(new PostPublishedEvent(
                $slug,
                $postPath,
                $kind,
                $title,
                $body,
                $mediaPaths
            ));
        }

        // Enqueue federation broadcast if outbox service available
        if ($this->outboxService !== null) {
            $fqdn = rtrim($this->site->metadata->fqdn ?? (string) Database::getSetting('fqdn'), '/');
            $handle = (string) (Database::getSetting('activitypub_handle') ?: 'author');
            $actorUri = $fqdn . '/@' . $handle;
            $objectId = $fqdn . '/' . $subfolder . '/' . $date;

            $object = ActivityBuilder::buildObjectForPageArray(
                $objectId,
                $actorUri,
                $fqdn,
                $text,
                $title
            );

            $createActivity = ActivityBuilder::buildCreateActivity($objectId . '#activity', $actorUri, $object);
            $this->outboxService->broadcastActivity($createActivity);
        }

        return [
            'slug' => $slug,
            'filepath' => $postPath,
        ];
    }
}
