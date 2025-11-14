<?php

namespace Ps4tek\CoreComponentRepository\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Ps4tek\CoreComponentRepository\CoreComponentRepository;

class CoreComponentGate
{
    public function handle(Request $request, Closure $next)
    {
        CoreComponentRepository::initializeCache();

        if (! CoreComponentRepository::verificationStatus()) {
            return CoreComponentRepository::interruptionResponse();
        }

        return $next($request);
    }
}
