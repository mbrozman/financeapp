<x-filament-panels::page>
    <style>
        :root {
            --glass-bg: rgba(255, 255, 255, 0.04);
            --glass-border: rgba(255, 255, 255, 0.1);
            --header-bg: rgba(0, 0, 0, 0.03);
            --bubble-bg: rgba(255, 255, 255, 0.95);
            --text-main: #1f2937;
            --text-muted: #6b7280;
            --input-border: rgba(0, 0, 0, 0.12);
        }
        .dark {
            --glass-bg: rgba(0, 0, 0, 0.2);
            --glass-border: rgba(255, 255, 255, 0.05);
            --header-bg: rgba(255, 255, 255, 0.05);
            --bubble-bg: rgba(31, 41, 55, 0.9);
            --text-main: #f3f4f6;
            --text-muted: #9ca3af;
            --input-border: rgba(255, 255, 255, 0.1);
        }

        /* MASONRY */
        .masonry-container {
            column-count: 1;
            column-gap: 1.25rem;
            width: 100%;
            margin-top: 10px;
        }
        @media (min-width: 640px)  { .masonry-container { column-count: 2; } }
        @media (min-width: 1024px) { .masonry-container { column-count: 3; } }
        @media (min-width: 1536px) { .masonry-container { column-count: 4; } }

        .kanban-col {
            break-inside: avoid;
            margin-bottom: 1.25rem;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .col-header {
            padding: 16px;
            background: var(--header-bg);
        }
        .col-title {
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .col-sum {
            font-size: 0.85rem;
            color: var(--text-muted);
            font-weight: 600;
        }

        /* GROUPED BUBBLE */
        .cards-container {
            padding: 14px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .group-bubble {
            background: var(--bubble-bg);
            border-radius: 12px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease;
        }
        .group-bubble:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 20px -4px rgba(0,0,0,0.12);
        }
        .group-bubble-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 14px;
        }
        .group-name {
            font-size: 0.8rem;
            font-weight: 700;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .group-amount {
            font-size: 0.95rem;
            font-weight: 800;
            white-space: nowrap;
            margin-left: 10px;
        }
        .group-count {
            font-size: 0.65rem;
            color: var(--text-muted);
            padding: 0 14px 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        .group-count svg {
            width: 10px;
            height: 10px;
        }

        /* Non-grouped bubble (for specials like transfers/income) */
        .transaction-bubble {
            background: var(--bubble-bg);
            padding: 10px 14px;
            border-radius: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--glass-border);
        }
        .bubble-info { display: flex; flex-direction: column; overflow: hidden; }
        .bubble-category {
            font-size: 0.7rem;
            font-weight: 700;
            color: var(--text-muted);
            text-transform: uppercase;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .bubble-desc {
            font-size: 0.65rem;
            color: var(--text-muted);
            opacity: 0.7;
        }
        .bubble-amount {
            font-size: 0.95rem;
            font-weight: 800;
            white-space: nowrap;
            margin-left: 10px;
        }
        .type-expense .bubble-amount { color: #ef4444; }
        .type-income  .bubble-amount { color: #22c55e; }
        .type-transfer .bubble-amount { color: #232323; }

        /* QUICK ADD */
        .quick-add-box {
            padding: 12px;
            border-top: 1px solid var(--glass-border);
            background: rgba(0,0,0,0.02);
        }
        .quick-input-group { display: flex; gap: 6px; }
        .q-input {
            width: 75px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--input-border) !important;
            color: var(--text-main);
            font-size: 0.875rem;
            font-weight: 700;
            border-radius: 10px;
            padding: 6px 10px;
            outline: none !important;
        }
        .q-select {
            flex: 1;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--input-border) !important;
            color: var(--text-main);
            font-size: 0.75rem;
            font-weight: 600;
            border-radius: 10px;
            padding: 6px 10px;
            outline: none !important;
            cursor: pointer;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 14px;
        }
        .q-btn {
            background: #3b82f6;
            color: white;
            border-radius: 10px;
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            transition: all 0.2s;
            border: none;
            cursor: pointer;
        }
        .q-btn:hover { background: #2563eb; transform: scale(1.05); }

        /* DETAIL MODAL */
        .detail-modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 200;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .detail-modal {
            background: white;
            border-radius: 20px;
            width: 100%;
            max-width: 520px;
            max-height: 80vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
            overflow: hidden;
        }
        .dark .detail-modal {
            background: #1f2937;
        }
        .modal-header {
            padding: 20px 24px 16px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dark .modal-header { border-color: rgba(255,255,255,0.05); }
        .modal-title {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--text-main);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .modal-close {
            width: 32px; height: 32px;
            border-radius: 50%;
            background: #f3f4f6;
            border: none;
            cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.15s;
        }
        .modal-close:hover { background: #e5e7eb; }
        .dark .modal-close { background: rgba(255,255,255,0.08); }
        .modal-body {
            overflow-y: auto;
            flex: 1;
            padding: 16px 24px;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .modal-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 10px 14px;
            border-radius: 12px;
            background: #f9fafb;
            border: 1px solid #f3f4f6;
        }
        .dark .modal-row { background: rgba(255,255,255,0.04); border-color: rgba(255,255,255,0.05); }
        .modal-row-left { display: flex; flex-direction: column; gap: 2px; }
        .modal-row-date { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); }
        .modal-row-desc { font-size: 0.8rem; color: var(--text-main); }
        .modal-row-amount { font-size: 0.95rem; font-weight: 800; color: #ef4444; white-space: nowrap; margin-left: 12px; }
        .modal-row-amount.income { color: #22c55e; }
        .modal-footer {
            padding: 12px 24px;
            border-top: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .dark .modal-footer { border-color: rgba(255,255,255,0.05); }
        .modal-total { font-size: 0.85rem; color: var(--text-muted); }
        .modal-total strong { color: var(--text-main); font-size: 1rem; }
    </style>

    {{-- SMART COMMAND BAR --}}
    <div class="mb-8 w-full">
        <div class="relative group">
            <div class="absolute -inset-1 bg-gradient-to-r from-blue-500 to-indigo-500 rounded-2xl blur opacity-25 group-hover:opacity-40 transition duration-1000 group-hover:duration-200"></div>
            <div class="relative flex items-center bg-white dark:bg-gray-900 ring-1 ring-gray-950/5 dark:ring-white/10 rounded-xl overflow-hidden shadow-sm">
                <div class="pl-4 text-gray-400">
                    <x-heroicon-m-bolt class="w-5 h-5" />
                </div>
                <input 
                    type="text" 
                    wire:model="smartInput"
                    wire:keydown.enter="saveSmartTransaction"
                    placeholder="Rýchly zápis: 25.90 potraviny..."
                    class="w-full bg-transparent border-none focus:ring-0 text-base py-4 px-4 text-gray-900 dark:text-white placeholder-gray-400 font-medium"
                >
                <div class="pr-4 flex items-center gap-2">
                    <span class="hidden sm:inline text-[10px] font-bold text-gray-400 bg-gray-100 dark:bg-white/5 px-2 py-1 rounded-md uppercase tracking-tighter">Enter</span>
                    <button wire:click="saveSmartTransaction" class="p-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg transition-colors">
                        <x-heroicon-m-plus class="w-4 h-4" />
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- MASONRY BOARD --}}
    <div class="masonry-container">
        @foreach($this->boardSections as $section)
            @php
                $hex = $section['color'] ?? '#6b7280';
                // Jednoduchý prevod HEX na RGB
                $hex = ltrim($hex, '#');
                if(strlen($hex) == 3) {
                    $r = hexdec(substr($hex,0,1).substr($hex,0,1));
                    $g = hexdec(substr($hex,1,1).substr($hex,1,1));
                    $b = hexdec(substr($hex,2,1).substr($hex,2,1));
                } else {
                    $r = hexdec(substr($hex,0,2));
                    $g = hexdec(substr($hex,2,2));
                    $b = hexdec(substr($hex,4,2));
                }
                $rgb = "$r, $g, $b";
            @endphp
            <div class="kanban-col" 
                 wire:key="sec-{{ $section['id'] }}"
                 style="--col-color-rgb: {{ $rgb }}; border-top: 4px solid rgb({{ $rgb }})">
                <div class="col-header">
                    <div class="col-title">
                        <span>{{ $section['title'] }}</span>
                        @if($section['type'] === 'special')
                            <x-heroicon-m-star class="w-4 h-4" style="color: {{ $section['color'] }}" />
                        @endif
                    </div>
                    <div class="col-sum">
                        {{ number_format($section['sum'], 2, ',', ' ') }} EUR
                    </div>
                </div>

                <div class="cards-container">
                    @if(in_array($section['type'], ['expense_category', 'income_section']))
                        {{-- GRUPOVANIE podľa podkategórie --}}
                        @php
                            $grouped = $section['transactions']->groupBy(fn($t) => $t->category_id ?? 'bez-kategorie');
                        @endphp

                        @foreach($grouped as $catId => $txGroup)
                            @php
                                $catName = $txGroup->first()->category?->name ?? 'Bez kategórie';
                                $groupSum = $txGroup->sum(fn($t) => abs((float) $t->amount));
                                $count = $txGroup->count();
                                $isIncome = $section['type'] === 'income_section';
                            @endphp
                            <div class="group-bubble"
                                 wire:click="showSubcategoryDetail({{ (int) $catId }}, '{{ addslashes($catName) }}')"
                                 wire:key="grp-{{ $section['id'] }}-{{ $catId }}">
                                <div class="group-bubble-header">
                                    <span class="group-name" style="display:flex; align-items:center; gap:6px;">
                                        @php
                                            $cat = $txGroup->first()->category;
                                            $icon = $cat?->parent?->icon ?? $cat?->icon;
                                        @endphp
                                        @if($icon)
                                            <x-dynamic-component :component="$icon" class="w-4 h-4 flex-shrink-0" style="color: {{ $section['color'] }}" />
                                        @endif
                                        {{ $catName }}
                                    </span>
                                    <span class="group-amount" style="color: {{ $isIncome ? '#22c55e' : '#ef4444' }}">
                                        {{ number_format($groupSum, 2, ',', ' ') }}
                                    </span>
                                </div>
                                <div class="group-count">
                                    <svg fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h7"/></svg>
                                    {{ $count }} {{ $count === 1 ? 'transakcia' : ($count <= 4 ? 'transakcie' : 'transakcií') }}
                                </div>
                            </div>
                        @endforeach

                    @else
                        {{-- ŠPECIÁLNE sekcie: Prevody, Pravidelné, Investície – bez grupovania --}}
                        @foreach($section['transactions'] as $transaction)
                            <div class="transaction-bubble type-{{ $transaction->type instanceof \App\Enums\TransactionType ? $transaction->type->value : $transaction->type }}"
                                 wire:key="card-{{ $transaction->id }}">
                                <div class="bubble-info">
                                    <span class="bubble-category">
                                        @if($section['id'] === 'transfers')
                                            {{ (float)$transaction->amount < 0 ? 'ODCHOD' : 'PRÍCHOD' }}
                                        @else
                                            {{ $transaction->category?->name ?? 'DETAIL' }}
                                        @endif
                                    </span>
                                    <span class="bubble-desc">{{ Str::limit($transaction->description, 30) }}</span>
                                </div>
                                <div class="bubble-amount" style="color: {{ $section['color'] }}">
                                    {{ number_format(abs((float) $transaction->amount), 2, ',', ' ') }}
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>

                @if(in_array($section['type'], ['expense_category', 'income_section']))
                    <div class="quick-add-box">
                        <div class="quick-input-group">
                            <input
                                type="text"
                                wire:model.defer="quickAdd.{{ $section['model_id'] ?? $section['id'] }}.amount"
                                placeholder="0.00"
                                class="q-input"
                                wire:keydown.enter="saveQuickTransaction('{{ $section['id'] }}', {{ $section['model_id'] ?? 'null' }})"
                            >
                            <select
                                wire:model.defer="quickAdd.{{ $section['model_id'] ?? $section['id'] }}.sub_category_id"
                                class="q-select"
                            >
                                <option value="">Detail...</option>
                                @foreach($section['children'] as $child)
                                    <option value="{{ $child->id }}">{{ $child->name }}</option>
                                @endforeach
                            </select>
                            <button
                                wire:click="saveQuickTransaction('{{ $section['id'] }}', {{ $section['model_id'] ?? 'null' }})"
                                class="q-btn"
                            >
                                <x-heroicon-m-plus class="w-4 h-4" />
                            </button>
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>

    {{-- DETAIL MODAL --}}
    @if($showDetailModal)
        <div class="detail-modal-backdrop" wire:click.self="closeDetailModal">
            <div class="detail-modal">
                <div class="modal-header">
                    <span class="modal-title">{{ $detailTitle }}</span>
                    <button class="modal-close" wire:click="closeDetailModal">
                        <x-heroicon-m-x-mark class="w-4 h-4 text-gray-500" />
                    </button>
                </div>

                <div class="modal-body">
                    @forelse($detailTransactions as $tx)
                        <div class="modal-row">
                            <div class="modal-row-left">
                                <span class="modal-row-date">{{ $tx['date'] }}</span>
                                <span class="modal-row-desc">{{ $tx['description'] }}</span>
                            </div>
                            <span class="modal-row-amount {{ $tx['type'] === 'income' ? 'income' : '' }}">
                                {{ $tx['amount'] }} €
                            </span>
                        </div>
                    @empty
                        <p style="text-align:center; color: var(--text-muted); padding: 20px 0;">
                            Žiadne transakcie v tomto mesiaci.
                        </p>
                    @endforelse
                </div>

                <div class="modal-footer">
                    <span class="modal-total">
                        Spolu:
                        <strong>
                            {{ number_format(collect($detailTransactions)->sum(fn($t) => (float) str_replace([' ', ','], ['', '.'], $t['amount'])), 2, ',', ' ') }} €
                        </strong>
                    </span>
                    <span style="font-size: 0.75rem; color: var(--text-muted);">
                        {{ count($detailTransactions) }} {{ count($detailTransactions) === 1 ? 'záznam' : (count($detailTransactions) <= 4 ? 'záznamy' : 'záznamov') }}
                    </span>
                </div>
            </div>
        </div>
    @endif
</x-filament-panels::page>
