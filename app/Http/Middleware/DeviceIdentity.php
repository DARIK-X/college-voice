<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DeviceIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $id = $request->cookie('voter_device');
        $valid = is_string($id) && Str::isUuid($id);
        // A POST without a previously issued identity is refused, so concurrent first requests cannot each vote.
        if (! $valid && $request->isMethod('post')) {
            abort(419, 'Обновите страницу и разрешите cookie.');
        }
        $id = $valid ? $id : (string) Str::uuid();
        $request->attributes->set('device_hash', hash_hmac('sha256', $id, config('app.key')));
        $response = $next($request);
        if (! $valid) {
            $response->headers->setCookie(cookie('voter_device', $id, 60 * 24 * 365 * 3, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }
}
