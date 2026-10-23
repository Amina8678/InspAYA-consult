<?php

/*
 * Subprocess helper for PendingRevisionConcurrencyTest: boots the app against
 * the test database it inherits and submits one pending revision, so the test
 * can run real, overlapping submissions in separate database connections.
 *
 * Usage: php submit_pending_revision.php <basePath> <pageId> <userId> <title>
 */

use App\Models\Page;
use App\Models\User;
use App\Support\ContentApproval;
use Illuminate\Contracts\Console\Kernel;

[, $base, $pageId, $userId, $title] = $argv;

require $base.'/vendor/autoload.php';

$app = require $base.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Never touch anything but a scratch database.
if (! str_ends_with((string) config('database.connections.mysql.database'), '_testing') || config('database.default') !== 'mysql') {
    fwrite(STDERR, "refusing to run outside a *_testing MySQL database\n");
    exit(2);
}

$app->make(ContentApproval::class)->submit(Page::findOrFail($pageId), User::findOrFail($userId), ['title' => $title]);

echo 'submitted';
