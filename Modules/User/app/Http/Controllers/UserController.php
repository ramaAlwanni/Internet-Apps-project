<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Illuminate\Validation\ValidationException;
use Modules\User\Http\Requests\SignUpRequest;
use Modules\User\Service\UserService;
use Modules\User\Http\Requests\SignInRequest;

class UserController extends Controller
{
    use ApiResponseTrait;

    public function __construct(
        private UserService $userService
    ) {}

    /**
     * Handle user registration.
     *
     * @param  \Modules\User\Http\Requests\SignUpRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signUp(SignUpRequest $request)
    {
        try{
            $userData = $this->userService->signUp($request->validated());
            return $this->successResponse('User registered successfully', $userData, 201);
        }

        catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }

    }

    /**
     * Handle user login.
     *
     * @param  \Modules\User\Http\Requests\SignInRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signIn(SignInRequest $request)
    {
        try {
            $userData = $this->userService->signIn($request->validated());
            return $this->successResponse('User signed in successfully', $userData, 200);
        } catch (ValidationException $e) {
            return $this->errorResponse('Validation failed', $e->errors(), 422);
        }
    }

}
