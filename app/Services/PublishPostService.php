<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\ActivityPub\ActivityBuilder;
use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Events\Contracts\EventDispatcherInterface;
use Indieinabox\Events\PostPublishedEvent;
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

    public function __construct(
        Site $site,
        ?OutboxService $outboxService = null,
        ?EventDispatcherInterface $events = null
    ) {
        $this->site = $site;
        $this->outboxService = $outboxService;
        $this->events = $events;

        if ($this->events === null && class_exists(Container::class) && Container::getInstance()->has(EventDispatcherInterface::class)) {
            $this->events = Container::getInstance()->get(EventDispatcherInterface::class);
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
        $contentDir = Database::$dataDir . '/../content';

        $subfolder = $kind === 'article' ? 'articles' : 'notes';
        $postDir = $contentDir . '/' . $subfolder;
        if (!is_dir($postDir)) {
            @mkdir($postDir, 0755, true);
        }

        $postPath = $postDir . '/' . $date . '.md';

        $content = '';
        if ($title !== null && $title !== '') {
            $content .= "---\ntitle: " . addslashes($title) . "\ndate: " . date('Y-m-d H:i:s') . "\n---\n\n";
        }

        $content .= $text;

        if (!empty($mediaPaths)) {
            $content .= "\n\n";
            foreach ($mediaPaths as $mp) {
                if (preg_match('/\.(mp4|webm|mov)$/i', $mp)) {
                    $content .= "<video src=\"{$mp}\" controls></video>\n";
                } elseif (preg_match('/\.(mp3|ogg|wav)$/i', $mp)) {
                    $content .= "<audio src=\"{$mp}\" controls></audio>\n";
                } else {
                    $content .= "![]({$mp})\n";
                }
            }
        }

        file_put_contents($postPath, $content);

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
                $content,
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
