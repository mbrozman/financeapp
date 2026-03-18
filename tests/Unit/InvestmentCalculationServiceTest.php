<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Investment;
use App\Models\InvestmentTransaction;
use App\Services\InvestmentCalculationService;
use App\Enums\TransactionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\Test;

class InvestmentCalculationServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_calculates_fifo_stats_for_simple_buy_and_sell()
    {
        // 1. Setup Investment
        $investment = Investment::factory()->create(['ticker' => 'AAPL']);

        // 2. Buy 10 shares @ 100 with 5 commission
        InvestmentTransaction::factory()->create([
            'investment_id' => $investment->id,
            'type' => TransactionType::BUY,
            'quantity' => '10',
            'price_per_unit' => '100',
            'commission' => '5',
            'transaction_date' => Carbon::now()->subDays(10),
        ]);

        // 3. Sell 5 shares @ 150 with 10 commission
        InvestmentTransaction::factory()->create([
            'investment_id' => $investment->id,
            'type' => TransactionType::SELL,
            'quantity' => '5',
            'price_per_unit' => '150',
            'commission' => '10',
            'transaction_date' => Carbon::now()->subDays(5),
        ]);

        // 4. Run Service
        $stats = InvestmentCalculationService::getStats($investment);

        // 5. Assertions
        // Current Qty: 10 - 5 = 5
        $this->assertEquals('5', $stats['current_quantity']);
        
        // Avg Buy Price: Original price was 100
        $this->assertEquals('100.0000', $stats['average_buy_price']);

        // Realized Gain:
        // Nákup mal poplatok 5 na 10 ks => 0.5/ks, pre predaných 5 ks je to 2.5
        // Predajný poplatok je 10 celkom => 2.0/ks, čistá predajná cena je 148/ks
        // Zisk = (148 - 100.5) * 5 = 237.5
        $this->assertEqualsWithDelta(237.5, (float) $stats['realized_gain_base'], 0.0001);

        // Total Invested: (10 * 100) + 5 = 1005
        $this->assertEqualsWithDelta(1005.0, (float) $stats['total_invested_base'], 0.0001);

        // Total Sales: (5 * 150) - 10 = 740
        $this->assertEqualsWithDelta(740.0, (float) $stats['total_sales_base'], 0.0001);
    }

    #[Test]
    public function it_calculates_fifo_stats_with_multiple_buy_lots()
    {
        $investment = Investment::factory()->create(['ticker' => 'TSLA']);

        // Lot 1: Buy 10 @ 200
        InvestmentTransaction::factory()->create([
            'investment_id' => $investment->id,
            'type' => TransactionType::BUY,
            'quantity' => '10',
            'price_per_unit' => '200',
            'commission' => '0',
            'transaction_date' => Carbon::now()->subDays(20),
        ]);

        // Lot 2: Buy 10 @ 300
        InvestmentTransaction::factory()->create([
            'investment_id' => $investment->id,
            'type' => TransactionType::BUY,
            'quantity' => '10',
            'price_per_unit' => '300',
            'commission' => '0',
            'transaction_date' => Carbon::now()->subDays(15),
        ]);

        // Sell 15 @ 400
        // FIFO: 10 from Lot 1, 5 from Lot 2
        InvestmentTransaction::factory()->create([
            'investment_id' => $investment->id,
            'type' => TransactionType::SELL,
            'quantity' => '15',
            'price_per_unit' => '400',
            'commission' => '0',
            'transaction_date' => Carbon::now()->subDays(10),
        ]);

        $stats = InvestmentCalculationService::getStats($investment);

        // Remaining Qty: 20 - 15 = 5
        $this->assertEquals('5', $stats['current_quantity']);

        // Avg Buy Price of remaining: Lot 2 remaining has price 300
        $this->assertEquals('300.0000', $stats['average_buy_price']);

        // Realized Gain: 
        // From Lot 1: (400 - 200) * 10 = 2000
        // From Lot 2: (400 - 300) * 5 = 500
        // Total = 2500
        $this->assertEqualsWithDelta(2500.0, (float) $stats['realized_gain_base'], 0.0001);
    }
}
