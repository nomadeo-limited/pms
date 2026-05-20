<?php

namespace App\Auth\UseCases;

use Illuminate\Auth\AuthenticationException;
use Tymon\JWTAuth\Facades\JWTAuth;

class LoginUseCase
{
    /**
     * @throws AuthenticationException
     */
    public function execute(string $email, string $password): string
    {
        $token = auth('api')->attempt(['email' => $email, 'password' => $password, 'is_active' => true]);

        if (!$token) {
            throw new AuthenticationException('Invalid credentials.');
        }

        return $token;
    }
}
