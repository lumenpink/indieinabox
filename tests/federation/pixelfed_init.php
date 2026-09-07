<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$user = App\Models\User::where('username', 'aaron')->first();
if (!$user->email_verified_at) { $user->email_verified_at = now(); $user->save(); }
$profile = $user->profile;
$profile->name = 'Aaron Pixelfed';
$profile->bio = 'Sou um bot de teste no Pixelfed.';
$profile->save();
$avatar = App\Models\Avatar::firstOrNew(['profile_id' => $profile->id]);
$avatar->media_path = 'public/avatars/aaron.png';
$avatar->change_count = 1;
$avatar->save();
echo "Pixelfed profile initialized\n";
