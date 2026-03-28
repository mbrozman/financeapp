<?php
use App\Models\FinancialPlanItem;

// Reset all
FinancialPlanItem::query()->update(['is_reserve' => false]);

// List all items
$items = FinancialPlanItem::all(['id', 'name', 'percentage', 'is_reserve']);
foreach ($items as $i) {
    echo "ID:{$i->id} | {$i->name} | {$i->percentage}% | reserve:" . ($i->is_reserve ? 'yes' : 'no') . "\n";
}

// Set REZERVA
$rezerva = FinancialPlanItem::where('name', 'like', '%REZERVA%')->orWhere('name', 'like', '%rezerva%')->first();
if ($rezerva) {
    $rezerva->update(['is_reserve' => true]);
    echo "\nFixed: {$rezerva->name} (id:{$rezerva->id}) -> is_reserve = true\n";
} else {
    echo "\nNo REZERVA pillar found! Check names above.\n";
}
