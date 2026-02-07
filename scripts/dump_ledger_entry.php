<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$id = $argv[1] ?? null;
if (! $id) {
    echo "Usage: php scripts/dump_ledger_entry.php <id>\n";
    exit(1);
}

$entry = App\Models\CustomerLedgerEntry::find($id);
if (! $entry) {
    echo "CustomerLedgerEntry {$id} not found\n";
    exit(1);
}

print_r($entry->toArray());
