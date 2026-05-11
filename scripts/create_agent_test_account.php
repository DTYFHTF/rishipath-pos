<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Role;
use App\Models\SalesAgent;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$orgId = 1;
$email = 'agent.test@rishipath.local';
$phone = '9800001234';
$password = 'Agent@12345';

$role = Role::where('organization_id', $orgId)
    ->where('slug', 'cashier')
    ->first();

if (! $role) {
    echo "ERROR: cashier role not found for org {$orgId}" . PHP_EOL;
    exit(1);
}

$user = User::where('email', $email)->first();
if (! $user) {
    $user = User::create([
        'organization_id' => $orgId,
        'name' => 'Agent Test User',
        'email' => $email,
        'phone' => $phone,
        'password' => Hash::make($password),
        'role_id' => $role->id,
        'permissions' => ['access_pos_billing', 'view_inventory_reports'],
        'active' => true,
    ]);

    echo "USER_CREATED|id={$user->id}|email={$email}|password={$password}" . PHP_EOL;
} else {
    $user->update([
        'phone' => $phone,
        'active' => true,
    ]);

    echo "USER_EXISTS|id={$user->id}|email={$email}" . PHP_EOL;
}

$agent = SalesAgent::where('organization_id', $orgId)
    ->where('phone', $phone)
    ->first();

if (! $agent) {
    $agent = SalesAgent::create([
        'organization_id' => $orgId,
        'agent_code' => 'AGT-TEST-01',
        'name' => 'Agent Test User',
        'phone' => $phone,
        'email' => $email,
        'commission_retail_pct' => 0,
        'commission_wholesale_profit_pct' => 25,
        'min_wholesale_amount' => 10000,
        'active' => true,
    ]);

    echo "AGENT_CREATED|id={$agent->id}|code={$agent->agent_code}" . PHP_EOL;
} else {
    echo "AGENT_EXISTS|id={$agent->id}|code={$agent->agent_code}" . PHP_EOL;
}
