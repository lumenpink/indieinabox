<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Indieinabox\Repositories\Contracts\MicrosubRepositoryInterface;
use Indieinabox\Services\MicrosubFilterService;

beforeEach(function () {
    // In-memory mock repository implementing MicrosubRepositoryInterface
    $this->mockRepo = new class implements MicrosubRepositoryInterface {
        public array $filters = [];
        public int $nextId = 1;

        public function getChannels(): array { return []; }
        public function createChannel(string $uid, string $name): bool { return true; }
        public function deleteChannel(string $uid): bool { return true; }
        public function getSubscriptions(string $channelUid): array { return []; }
        public function addSubscription(string $channelUid, string $url, string $type = 'feed', string $name = '', string $photo = ''): bool { return true; }
        public function getSubscriptionType(string $channelUid, string $url): ?string { return null; }
        public function removeSubscription(string $channelUid, string $url): bool { return true; }
        public function countSubscriptionsByUrl(string $url): int { return 0; }

        public function getFilters(?string $channelUid = null): array {
            if ($channelUid === null) {
                return $this->filters;
            }
            return array_values(array_filter($this->filters, function ($f) use ($channelUid) {
                return $f['channel_uid'] === '*' || $f['channel_uid'] === $channelUid;
            }));
        }

        public function addFilter(string $filterType, string $content, string $action = 'mute', string $channelUid = '*'): int {
            $id = $this->nextId++;
            $this->filters[] = [
                'id' => $id,
                'filter_type' => $filterType,
                'content' => $content,
                'action' => $action,
                'channel_uid' => $channelUid,
                'created_at' => time(),
            ];
            return $id;
        }

        public function deleteFilter(int $id): bool {
            foreach ($this->filters as $k => $f) {
                if ($f['id'] === $id) {
                    unset($this->filters[$k]);
                    $this->filters = array_values($this->filters);
                    return true;
                }
            }
            return false;
        }
    };

    $this->service = new MicrosubFilterService($this->mockRepo);
});

describe('MicrosubFilterService', function () {
    it('matches keyword filters in content, title, and summary', function () {
        $this->service->addFilter('keyword', 'cryptocurrency', 'mute', '*');

        // Match in content text
        $entry1 = [
            'name' => 'Tech News',
            'content' => ['text' => 'Here is some discussion on cryptocurrency and web3.'],
        ];
        expect($this->service->evaluate($entry1))->toBe('mute');
        expect($this->service->shouldMute($entry1))->toBeTrue();

        // Match in HTML content
        $entry2 = [
            'name' => 'Market Report',
            'content' => ['html' => '<p>Investing in <b>CryptoCurrency</b> is risky.</p>'],
        ];
        expect($this->service->shouldMute($entry2))->toBeTrue();

        // Match in title/name
        $entry3 = [
            'name' => 'Top Cryptocurrency Picks',
            'content' => ['text' => 'Generic text without the word.'],
        ];
        expect($this->service->shouldMute($entry3))->toBeTrue();

        // Clean entry does not match
        $entryClean = [
            'name' => 'Garden Notes',
            'content' => ['text' => 'Tomatoes and basil are growing nicely.'],
        ];
        expect($this->service->evaluate($entryClean))->toBeNull();
        expect($this->service->shouldMute($entryClean))->toBeFalse();
    });

    it('matches author filters by name and profile URL', function () {
        $this->service->addFilter('author', 'Troll User', 'drop', '*');
        $this->service->addFilter('author', 'https://spammer.social/@bot', 'mute', '*');

        // Match author name
        $entry1 = [
            'author' => ['name' => 'Troll User', 'url' => 'https://random.org/user'],
            'content' => ['text' => 'Harassment post'],
        ];
        expect($this->service->shouldDrop($entry1))->toBeTrue();

        // Match author URL
        $entry2 = [
            'author' => ['name' => 'Innocent Name', 'url' => 'https://spammer.social/@bot'],
            'content' => ['text' => 'Spam payload'],
        ];
        expect($this->service->shouldMute($entry2))->toBeTrue();

        // Clean author does not match
        $entryClean = [
            'author' => ['name' => 'Alice', 'url' => 'https://alice.example'],
            'content' => ['text' => 'Hello world'],
        ];
        expect($this->service->evaluate($entryClean))->toBeNull();
    });

    it('matches domain filters on item URLs, origins, and subdomains', function () {
        $this->service->addFilter('domain', 'spam-instance.org', 'drop', '*');
        $this->service->addFilter('domain', 'annoying-blog.com', 'auto_archive', '*');

        // Match exact domain on item URL
        $entry1 = [
            'url' => 'https://spam-instance.org/notes/123',
            'content' => ['text' => 'Spam'],
        ];
        expect($this->service->shouldDrop($entry1))->toBeTrue();

        // Match subdomain
        $entry2 = [
            'url' => 'https://sub.spam-instance.org/notes/456',
            'content' => ['text' => 'Spam'],
        ];
        expect($this->service->shouldDrop($entry2))->toBeTrue();

        // Match origin_server in fediverse extension metadata
        $entry3 = [
            'url' => 'https://relay.com/item/1',
            '_indieinabox' => ['origin_server' => 'https://annoying-blog.com'],
            'content' => ['text' => 'Automated feed item'],
        ];
        expect($this->service->shouldAutoArchive($entry3))->toBeTrue();

        // Clean domain does not match
        $entryClean = [
            'url' => 'https://good-domain.org/article',
            'content' => ['text' => 'Quality post'],
        ];
        expect($this->service->evaluate($entryClean))->toBeNull();
    });

    it('respects channel scoping for rules', function () {
        // Rule only for notifications channel
        $this->service->addFilter('keyword', 'alert', 'mute', 'notifications');
        // Global rule
        $this->service->addFilter('keyword', 'promo', 'drop', '*');

        $entry = [
            'content' => ['text' => 'System alert message'],
        ];

        // When evaluating for inbox: 'alert' rule should NOT trigger
        expect($this->service->evaluate($entry, 'inbox'))->toBeNull();

        // When evaluating for notifications: 'alert' rule DOES trigger
        expect($this->service->evaluate($entry, 'notifications'))->toBe('mute');

        // Global rule triggers on any channel
        $promoEntry = ['content' => ['text' => 'Special promo discount']];
        expect($this->service->evaluate($promoEntry, 'inbox'))->toBe('drop');
        expect($this->service->evaluate($promoEntry, 'notifications'))->toBe('drop');
    });

    it('applies action precedence: drop > mute > auto_archive', function () {
        $this->service->addFilter('keyword', 'tag1', 'auto_archive', '*');
        $this->service->addFilter('keyword', 'tag2', 'mute', '*');
        $this->service->addFilter('keyword', 'tag3', 'drop', '*');

        // Entry with auto_archive and mute -> mute wins
        $entry1 = ['content' => ['text' => 'Contains tag1 and tag2']];
        expect($this->service->evaluate($entry1))->toBe('mute');

        // Entry with auto_archive, mute, and drop -> drop wins
        $entry2 = ['content' => ['text' => 'Contains tag1, tag2, and tag3']];
        expect($this->service->evaluate($entry2))->toBe('drop');
    });

    it('manages filter CRUD operations cleanly', function () {
        $id = $this->service->addFilter('keyword', 'test-term', 'mute', 'inbox');
        expect($id)->toBeGreaterThan(0);

        $filters = $this->service->getFilters('inbox');
        expect($filters)->toHaveCount(1);
        expect($filters[0]['content'])->toBe('test-term');

        expect($this->service->deleteFilter($id))->toBeTrue();
        expect($this->service->getFilters('inbox'))->toBeEmpty();
    });
});
