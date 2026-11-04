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
        $user->assignRole('user');

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

    public function signOut(): void
    {
        $blacklist = FacadesJWTAuth::manager()->getBlacklist();
        $gracePeriod = $blacklist->getGracePeriod();
        // Logout must revoke the token immediately, regardless of the grace period for concurrent requests.
        $blacklist->setGracePeriod(0);

        try {
            FacadesJWTAuth::setToken(request()->bearerToken())->invalidate();
        } finally {
            $blacklist->setGracePeriod($gracePeriod);
        }
    }
}
