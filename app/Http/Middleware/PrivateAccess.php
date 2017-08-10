<?php

namespace App\Http\Middleware;

use Closure;

class PrivateAccess
{
    /**
     * The names of the attributes that should not be trimmed.
     *
     * @var array
     */
    public function handle($request, Closure $next, $guard = null)
    {
        if(in_array($_SERVER['REMOTE_ADDR'], config('routes.privates_ips'))){
            return $next($request);
        } else {
            return response(null, 403);
        }
    }
}
