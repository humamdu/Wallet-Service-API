<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireIdempotencyKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('Idempotency-Key');

        if (is_null($raw)) {
            return response()->json(['message' => 'Idempotency-Key is required'], Response::HTTP_BAD_REQUEST);
        }

        // Normalize: trim and collapse spaces
        $key = trim((string) $raw);

        if ($key === '') {
            return response()->json(['message' => 'Idempotency-Key must not be empty'], Response::HTTP_BAD_REQUEST);
        }

        // Validate length
        if (mb_strlen($key) > 255) {
            return response()->json(['message' => 'Idempotency-Key is too long'], Response::HTTP_BAD_REQUEST);
        }

        $request->merge(['idempotency_key' => $key]);
        return $next($request);
    }
}
