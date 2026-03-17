<?php

namespace App\Livewire;

use App\Models\Currency;
use Livewire\Component;

class GlobalCurrencySwitcher extends Component
{
    public $currencyId;

    public function mount()
    {
        $this->currencyId = session('global_currency_id');

        if (!$this->currencyId) {
            $defaultCurrency = Currency::where('code', 'EUR')->first() ?? Currency::first();
            if ($defaultCurrency) {
                $this->currencyId = $defaultCurrency->id;
                session(['global_currency_id' => $this->currencyId]);
                session(['global_currency' => $defaultCurrency->code]);
            }
        }
    }

    public function updatedCurrencyId($value)
    {
        if (empty($value)) {
            return;
        }

        $currency = Currency::find($value);
        if ($currency) {
            session(['global_currency_id' => $currency->id]);
            session(['global_currency' => $currency->code]);
            $this->redirect(request()->header('Referer')); // Reload current page
        }
    }

    public function render()
    {
        return view('livewire.global-currency-switcher', [
            'currencies' => Currency::orderBy('code')->get(),
        ]);
    }
}
