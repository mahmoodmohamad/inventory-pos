<?php

namespace App\Http\Middleware;

use App\Livewire\Actions\Logout;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            app(Logout::class)();

            return redirect('/')->with('status', __('Your account is disabled.'));
        }

        return $next($request);
    }
}
