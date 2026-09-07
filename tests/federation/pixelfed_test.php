<?php
$user = App\Models\User::where('username', 'aaron')->first();
$profile = $user->profile;
$profile->avatar_url = config('app.url') . '/storage/avatars/aaron.png';
$profile->save();
echo "Pixelfed avatar created successfully!\n";
