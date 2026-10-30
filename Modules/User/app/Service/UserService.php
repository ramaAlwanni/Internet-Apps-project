<?php

namespace Modules\User\Service;

use Modules\User\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;

class UserService
{
    public function signUp(array $validatedData): array
    {
        $user = User::create([
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'password' => bcrypt($validatedData['password']),
        ]);
        $token = FacadesJWTAuth::fromUser($user);

        return ['user' => $user, 'token' => $token];
    }

    public function signIn(array $validatedData): array
    {
        $credentials = [
            'email' => $validatedData['email'],
            'password' => $validatedData['password'],
        ];

        if (!$token = FacadesJWTAuth::attempt($credentials)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
        'message' => ['Invalid credentials provided.'],
    ]);
}
        return ['user' => auth()->user(), 'token' => $token];
    }
}
