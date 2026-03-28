<?php
use App\Models\Transaction;
use App\Models\Account;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// Mock user
$user = \App\Models\User::first();
Auth::login($user);

$account1 = Account::where('user_id', $user->id)->first();
$account2 = Account::where('user_id', $user->id)->skip(1)->first();

if (!$account1 || !$account2) {
    echo "Need at least 2 accounts for testing.\n";
    exit;
}

echo "Testing transfer between {$account1->name} and {$account2->name}\n";

DB::transaction(function () use ($account1, $account2, $user) {
    // 1. Create t1
    $t1 = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account1->id,
        'type' => 'transfer',
        'amount' => -100,
        'transaction_date' => now(),
        'description' => 'Debug Transfer (Odchod)',
    ]);

    echo "Created T1 with ID: {$t1->id}\n";

    // 2. Create t2
    $t2 = Transaction::create([
        'user_id' => $user->id,
        'account_id' => $account2->id,
        'linked_transaction_id' => $t1->id,
        'type' => 'transfer',
        'amount' => 100,
        'transaction_date' => now(),
        'description' => 'Debug Transfer (Príchod)',
    ]);

    echo "Created T2 with ID: {$t2->id}, linked to T1: {$t2->linked_transaction_id}\n";

    // 3. Update t1
    echo "Updating T1 with link to T2: {$t2->id}\n";
    $success = $t1->updateQuietly(['linked_transaction_id' => $t2->id]);
    echo "UpdateQuietly success: " . ($success ? "YES" : "NO") . "\n";
});

// Verify from DB
$finalT1 = Transaction::find(Transaction::where('description', 'Debug Transfer (Odchod)')->latest()->first()->id);
$finalT2 = Transaction::find(Transaction::where('description', 'Debug Transfer (Príchod)')->latest()->first()->id);

echo "Final State:\n";
echo "T1 ID: {$finalT1->id}, Linked: " . ($finalT1->linked_transaction_id ?: 'NULL') . "\n";
echo "T2 ID: {$finalT2->id}, Linked: " . ($finalT2->linked_transaction_id ?: 'NULL') . "\n";

// Test deletion
echo "Deleting T1...\n";
$finalT1->delete();

$checkT2 = Transaction::find($finalT2->id);
echo "T2 still exists? " . ($checkT2 ? "YES" : "NO") . "\n";
