<?php

use App\Models\User;
use App\Services\Totp;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    throw new RuntimeException('Browser fixtures are limited to local environments.');
}
if (($argv[1] ?? '') === 'cleanup') {
    $user = User::where('email', $argv[2])->where('email', 'like', 'preview-%@medica.test')->firstOrFail();
    DB::table('sessions')->where('user_id', $user->id)->delete();
    DB::table('audit_events')->where('user_id', $user->id)->update(['user_id' => null]);
    $user->delete();
    exit;
}
$secret = (new Totp)->generateSecret();
$password = bin2hex(random_bytes(16));
$enrolling = ($argv[1] ?? '') === 'enrollment';
$user = User::forceCreate(['name' => 'Alex Morgan', 'email' => 'preview-'.bin2hex(random_bytes(6)).'@medica.test', 'password' => $password, 'role' => 'owner', 'clinic_id' => DB::table('clinics')->value('id'), 'mfa_secret' => $enrolling ? null : $secret, 'mfa_confirmed_at' => $enrolling ? null : now()]);
echo json_encode(['email' => $user->email, 'password' => $password, 'secret' => $secret]);
