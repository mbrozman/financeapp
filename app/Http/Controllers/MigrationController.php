<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class MigrationController extends Controller
{
    /**
     * Spustí migrácie na produkcii cez webový endpoint.
     * Vyžaduje 'key' v query stringu zhodný s APP_KEY.
     */
    public function migrate(Request $request)
    {
        $inputKey = $request->query('key');
        $appKey = config('app.key');

        if (empty($inputKey) || $inputKey !== $appKey) {
            abort(403, 'Neautorizovaný prístup. Kľúč sa nezhoduje.');
        }

        try {
            Artisan::call('migrate', [
                '--force' => true,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Migrácie úspešne prebehli.',
                'output' => Artisan::output(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Chyba pri spúšťaní migrácií.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
