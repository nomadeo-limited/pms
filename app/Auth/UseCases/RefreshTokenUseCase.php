<?php

namespace App\Auth\UseCases;

use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class RefreshTokenUseCase
{
    /**
     * @throws TokenInvalidException
     */
    public function execute(): string
    {
        return auth('api')->refresh();
    }
}
