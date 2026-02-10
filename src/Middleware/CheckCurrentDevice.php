<?php

namespace UserDevices\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CheckCurrentDevice
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        $isBlocked = $this->checkUserAgent($request);

        abort_if($isBlocked, Response::HTTP_LOCKED);

        return $next($request);
    }

    /**
     * Check if the user agent is blocked.
     */
    private function checkUserAgent(Request $request): bool
    {
        $user = $request->user();

        return $user->isCurrentDeviceBlocked();
    }
}
