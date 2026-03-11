<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictSuperadminAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if ($user && $user->is_superadmin) {
            // Povolené cesty pre superadmina
            $allowedPaths = [
                'admin/users',
                'admin/users/*',
                'admin/logout', // aby sa mohol odhlásiť
                'livewire/*',   // pre prekliky a formuláre vo filamente
                'filament/*',
            ];

            $isAllowed = false;
            foreach ($allowedPaths as $path) {
                if ($request->is($path)) {
                    $isAllowed = true;
                    break;
                }
            }

            // Zamedziť prístup na dashboard a do iných častí
            if (!$isAllowed && $request->path() !== 'admin/users') {
                return redirect('admin/users');
            }
            
            // Ak ide na root admina (Dashboard), taktiež ho presmerujeme na užívateľov
            if ($request->path() === 'admin' || $request->path() === '/') {
                 return redirect('admin/users');
            }
        }

        return $next($request);
    }
}
