<?php

declare(strict_types=1);

namespace Indieinabox\Moderation;

use Indieinabox\Core\Container;
use Indieinabox\Core\Database;
use Indieinabox\Moderation\Contracts\ModerationServiceInterface;
use PDO;

/**
 * Service managing domain/actor defederation rules, silence lists, and moderation audit trails.
 */
class ModerationService implements ModerationServiceInterface
{
    private ?PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db;
    }

    private function getDb(): PDO
    {
        if ($this->db !== null) {
            return $this->db;
        }

        $container = class_exists(Container::class) ? Container::getInstance() : null;
        if ($container && $container->has(PDO::class)) {
            return $container->get(PDO::class);
        }

        return Database::getDb();
    }

    #[\Override]
    public function addBlock(?string $domain, ?string $actorUrl = null, string $action = 'block', string $reason = ''): int
    {
        $allowedActions = ['block', 'silence', 'reject_media'];
        if (!in_array($action, $allowedActions, true)) {
            $action = 'block';
        }

        $cleanDomain = null;
        if (!empty($domain)) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            $cleanDomain = strtolower(trim($parsed ?: $domain));
        }

        $cleanActor = !empty($actorUrl) ? trim($actorUrl) : null;

        $stmt = $this->getDb()->prepare(
            'INSERT INTO federation_blocks (domain, actor_url, action, reason, created_at) ' .
            'VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$cleanDomain, $cleanActor, $action, trim($reason), time()]);

        return (int) $this->getDb()->lastInsertId();
    }

    #[\Override]
    public function removeBlock(int $id): bool
    {
        $stmt = $this->getDb()->prepare('DELETE FROM federation_blocks WHERE id = ?');
        return $stmt->execute([$id]);
    }

    #[\Override]
    public function getBlocks(): array
    {
        try {
            $stmt = $this->getDb()->query('SELECT id, domain, actor_url, action, reason, created_at FROM federation_blocks ORDER BY created_at DESC, id DESC');
            return $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    #[\Override]
    public function checkAction(?string $actorUrl, ?string $domain = null): ?string
    {
        $cleanDomain = null;
        if (!empty($domain)) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            $cleanDomain = strtolower(trim($parsed ?: $domain));
        }

        $cleanActor = !empty($actorUrl) ? trim($actorUrl) : null;

        if ($cleanDomain === null && $cleanActor !== null) {
            $host = parse_url($cleanActor, PHP_URL_HOST);
            if ($host) {
                $cleanDomain = strtolower($host);
            }
        }

        $blocks = $this->getBlocks();
        foreach ($blocks as $rule) {
            $ruleAction = (string) $rule['action'];

            // Match specific actor URL
            if (!empty($rule['actor_url']) && $cleanActor !== null) {
                if (strcasecmp($rule['actor_url'], $cleanActor) === 0) {
                    return $ruleAction;
                }
            }

            // Match domain or subdomain
            if (!empty($rule['domain']) && $cleanDomain !== null) {
                $ruleDomain = strtolower(trim($rule['domain']));
                if ($cleanDomain === $ruleDomain || str_ends_with($cleanDomain, '.' . $ruleDomain)) {
                    return $ruleAction;
                }
            }
        }

        return null;
    }

    #[\Override]
    public function isBlocked(?string $domain, ?string $actorUrl = null): bool
    {
        return $this->checkAction($actorUrl, $domain) === 'block';
    }

    #[\Override]
    public function isSilenced(?string $domain, ?string $actorUrl = null): bool
    {
        $action = $this->checkAction($actorUrl, $domain);
        return $action === 'silence' || $action === 'block';
    }
}
