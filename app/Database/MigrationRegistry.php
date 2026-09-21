<?php

declare(strict_types=1);

namespace Indieinabox\Database;

use Indieinabox\Database\Contracts\MigrationInterface;
use Indieinabox\Database\Migrations\M20260921000001CreateSettingsTables;
use Indieinabox\Database\Migrations\M20260921000002CreateIndieAuthTables;
use Indieinabox\Database\Migrations\M20260921000003CreateMicrosubTables;
use Indieinabox\Database\Migrations\M20260921000004CreateActivityPubTables;
use Indieinabox\Database\Migrations\M20260921000005CreateArchiveAndQueueTables;
use Indieinabox\Database\Migrations\M20260921000006CreatePushSubscriptionsTable;

/**
 * Registry mapping migration identifiers to migration implementation classes.
 */
class MigrationRegistry
{
    /**
     * Retrieves all registered migrations in execution order.
     *
     * @return array<string, class-string<MigrationInterface>> Map of migration identifier to class name.
     */
    public static function getMigrations(): array
    {
        return [
            '2026_09_21_000001_create_settings_tables' => M20260921000001CreateSettingsTables::class,
            '2026_09_21_000002_create_indieauth_tables' => M20260921000002CreateIndieAuthTables::class,
            '2026_09_21_000003_create_microsub_tables' => M20260921000003CreateMicrosubTables::class,
            '2026_09_21_000004_create_activitypub_tables' => M20260921000004CreateActivityPubTables::class,
            '2026_09_21_000005_create_archive_and_queue_tables' => M20260921000005CreateArchiveAndQueueTables::class,
            '2026_09_21_000006_create_push_subscriptions_table' => M20260921000006CreatePushSubscriptionsTable::class,
        ];
    }
}
