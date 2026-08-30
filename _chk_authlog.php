<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\DB;
use Modules\Core\Entities\User;

$model = config('activitylog.activity_model');

echo "listeners registered:\n";
foreach ([Login::class, Logout::class, Failed::class] as $event) {
    printf("  %-8s %d\n", class_basename($event), count(app('events')->getListeners($event)));
}

// Everything below is rolled back, so the database is left untouched.
DB::beginTransaction();

try {
    $user = User::create([
        'name' => 'ZZ Temp Probe',
        'email' => 'zz-temp-probe-'.uniqid().'@example.test',
        'password' => bcrypt('irrelevant'),
    ]);

    $before = $model::query()->count();

    echo "\nfiring events for user #{$user->id} ...\n";

    event(new Login('web', $user, false));
    event(new Logout('web', $user));
    event(new Failed('web', null, ['email' => 'someone@example.test', 'password' => 'SHOULD-NOT-BE-LOGGED']));

    $rows = $model::query()->where('log_name', 'auth')->latest('id')->limit(5)->get();

    echo 'rows written: ', $model::query()->count() - $before, "\n\n";

    foreach ($rows as $row) {
        $properties = $row->properties->all();

        printf(
            "  event=%-13s causer=%-5s desc=%-32s props=%s\n",
            $row->event,
            $row->causer_id ?? '-',
            $row->description,
            json_encode($properties, JSON_UNESCAPED_UNICODE)
        );
    }

    // The failed-attempt row must never carry the submitted password.
    $failed = $rows->firstWhere('event', 'failed_login');
    $leaked = $failed && str_contains(json_encode($failed->properties->all()), 'SHOULD-NOT-BE-LOGGED');
    echo "\n  password leaked into log: ", $leaked ? 'YES  <-- BUG' : 'no', "\n";
} catch (Throwable $e) {
    echo "\nFAILED: ", get_class($e), ': ', $e->getMessage(), "\n";
    echo '  at ', $e->getFile(), ':', $e->getLine(), "\n";
} finally {
    DB::rollBack();
    echo "\n(transaction rolled back - database unchanged)\n";
}
