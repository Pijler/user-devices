<?php

namespace UserDevices\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use UserDevices\DeviceCreator;

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
     * Get the user agent from the request.
     */
    private function getUserAgent(Request $request): mixed
    {
        $userAgent = $request->userAgent();

        return with($userAgent, DeviceCreator::$userAgent);
    }

    /**
     * Check if the user agent is blocked.
     */
    private function checkUserAgent(Request $request): bool
    {
        $user = $request->user();

        $userAgent = $this->getUserAgent($request);

        return $user->userDevices()->isBlocked($userAgent);
    }
}
