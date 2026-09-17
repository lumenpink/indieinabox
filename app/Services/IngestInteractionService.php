<?php

declare(strict_types=1);

namespace Indieinabox\Services;

use Indieinabox\Core\Container;
use Indieinabox\DTO\InteractionDto;
use Indieinabox\Events\Contracts\EventDispatcherInterface;
use Indieinabox\Events\WebmentionReceivedEvent;
use Indieinabox\Repositories\Contracts\InteractionRepositoryInterface;
use Indieinabox\Repositories\FileInteractionRepository;
use Indieinabox\Services\Contracts\IngestInteractionServiceInterface;

/**
 * Service orchestrating the ingestion, normalization, and persistence of social interactions.
 */
class IngestInteractionService implements IngestInteractionServiceInterface
{
    private InteractionRepositoryInterface $interactionRepo;
    private ?EventDispatcherInterface $eventDispatcher;

    public function __construct(
        ?InteractionRepositoryInterface $interactionRepo = null,
        ?EventDispatcherInterface $eventDispatcher = null
    ) {
        $container = class_exists(Container::class) ? Container::getInstance() : null;

        if ($interactionRepo !== null) {
            $this->interactionRepo = $interactionRepo;
        } elseif ($container && $container->has(InteractionRepositoryInterface::class)) {
            $this->interactionRepo = $container->get(InteractionRepositoryInterface::class);
        } else {
            $this->interactionRepo = new FileInteractionRepository();
        }

        if ($eventDispatcher !== null) {
            $this->eventDispatcher = $eventDispatcher;
        } elseif ($container && $container->has(EventDispatcherInterface::class)) {
            $this->eventDispatcher = $container->get(EventDispatcherInterface::class);
        } else {
            $this->eventDispatcher = null;
        }
    }

    /**
     * Ingests a normalized interaction into the repository and dispatches associated domain events.
     *
     * @param InteractionDto $interaction
     * @return bool
     */
    public function ingest(InteractionDto $interaction): bool
    {
        $targetSlug = trim(parse_url($interaction->getTarget(), PHP_URL_PATH) ?? $interaction->getTarget(), "/");
        if ($targetSlug === "") {
            $targetSlug = "home";
        }
        $targetHash = md5($targetSlug);

        $metadata = [
            "id" => $interaction->getId(),
            "target_hash" => $targetHash,
            "source" => $interaction->getSource(),
            "target" => $interaction->getTarget(),
            "author_name" => $interaction->getAuthorName(),
            "author_photo" => $interaction->getAuthorPhoto() ?? "",
            "author_url" => $interaction->getAuthorUrl() ?? "",
            "url" => $interaction->getAuthorUrl() ?: $interaction->getSource(),
            "published" => $interaction->getPublishedAt()->getTimestamp(),
            "is_read" => 0,
            "type" => $interaction->getProtocol(),
            "interaction_type" => $interaction->getType(),
            "status" => $interaction->getStatus(),
        ];

        foreach ($interaction->getMetadata() as $key => $val) {
            if (!isset($metadata[$key])) {
                $metadata[$key] = $val;
            }
        }

        $channel = $interaction->getStatus() === "spam" ? "spam" : "notifications";
        $saved = $this->interactionRepo->save(
            $interaction->getId(),
            $metadata,
            $interaction->getContent(),
            $channel
        );

        if ($saved && $this->eventDispatcher !== null && $interaction->getProtocol() === "webmention") {
            $author = [
                "name" => $interaction->getAuthorName(),
                "url" => $interaction->getAuthorUrl(),
                "photo" => $interaction->getAuthorPhoto(),
            ];
            $this->eventDispatcher->dispatch(new WebmentionReceivedEvent(
                $interaction->getSource(),
                $interaction->getTarget(),
                $interaction->getType(),
                $author,
                $interaction->getContent()
            ));
        }

        return $saved;
    }

    /**
     * Ingests a verified Webmention payload.
     *
     * @param string $source
     * @param string $target
     * @param array<string, mixed> $verifiedContent
     * @param string $status
     * @return InteractionDto
     */
    public function ingestWebmention(
        string $source,
        string $target,
        array $verifiedContent,
        string $status = "pending"
    ): InteractionDto {
        $dto = InteractionDto::fromWebmention($source, $target, $verifiedContent, $status);
        $this->ingest($dto);
        return $dto;
    }

    /**
     * Ingests a federated ActivityPub activity.
     *
     * @param array<string, mixed> $activity
     * @param array<string, mixed>|null $actorData
     * @param string $status
     * @return InteractionDto|null
     */
    public function ingestActivity(
        array $activity,
        ?array $actorData = null,
        string $status = "pending"
    ): ?InteractionDto {
        $dto = InteractionDto::fromActivityPub($activity, $actorData, $status);
        if ($dto !== null) {
            $this->ingest($dto);
        }
        return $dto;
    }
}
