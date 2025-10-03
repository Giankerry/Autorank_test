<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    protected function redirectTo($request): ?string
    {
        if (! $request->expectsJson()) {
            dd('Authenticate.php redirectTo() triggered');
            return route('auth.google.redirect');
        }

        return null;
    }
}
