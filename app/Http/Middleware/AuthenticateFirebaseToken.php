<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\FirebaseTokenVerifier;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateFirebaseToken
{
    public function __construct(
        private readonly FirebaseTokenVerifier $tokens,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = (string) $request->header('Authorization', '');
        if (! preg_match('/^Bearer\s+(.+)$/i', $header, $matches)) {
            return $this->unauthorized('Missing bearer token.');
        }

        try {
            $claims = $this->tokens->verify(trim($matches[1]));
        } catch (AuthenticationException $exception) {
            return $this->unauthorized($exception->getMessage());
        }

        $request->attributes->set('firebase_claims', $claims);
        $request->attributes->set('firebase_uid', (string) $claims['sub']);

        return $next($request);
    }

    private function unauthorized(string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'error' => 'Unauthenticated',
        ], 401);
    }
}
