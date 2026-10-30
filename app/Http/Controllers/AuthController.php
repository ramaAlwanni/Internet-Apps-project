<?php

namespace App\Http\Controllers;
use App\Http\Requests\SignUpRequest;
use App\Traits\ApiResponseTrait;
use Illuminate\Validation\ValidationException;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth as FacadesJWTAuth;

class AuthController extends Controller
{
    use ApiResponseTrait;

    /**
     * Handle user registration.
     *
     * @param  \App\Http\Requests\SignUpRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signUp(SignUpRequest $request)
    {
        try{

            $validatedData = $request->validated();
            $user = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => bcrypt($validatedData['password']),
            ]);
            $token = FacadesJWTAuth::fromUser($user);
            return $this->successResponse('User registered successfully', ['user' => $user, 'token' => $token], 201);
        }

        catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }


}

