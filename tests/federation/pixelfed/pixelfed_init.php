<?php

declare(strict_types=1);

/**
 * Pixelfed test profile & photo album initialization script.
 */
if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
    $app = require_once __DIR__ . '/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();

    $user = \App\Models\User::firstOrNew(['username' => 'photographer']);
    $user->email = 'photographer@pixelfed.local';
    $user->email_verified_at = now();
    $user->password = bcrypt('pixelfed_pass_123');
    $user->save();

    $profile = $user->profile ?? new \App\Models\Profile(['user_id' => $user->id]);
    $profile->name = 'Test Photographer';
    $profile->bio = 'Automated interop test bot for Pixelfed galleries.';
    $profile->save();

    echo "✓ Pixelfed test user initialized successfully.\n";
} else {
    echo "Pixelfed vendor autoload not found directly in host; running in container context.\n";
}
