<?php

namespace Modules\User\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Traits\ApiResponseTrait;
use Modules\User\Http\Requests\SignUpRequest;
use Modules\User\Service\UserService;
use Modules\User\Http\Requests\SignInRequest;
use Modules\User\Resources\UserResource;

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

            $userData = $this->userService->signUp($request->validated());
            $userData['user'] = new UserResource($userData['user']);
            return $this->successResponse('User registered successfully', $userData, 201);

    }

    /**
     * Handle user login.
     *
     * @param  \Modules\User\Http\Requests\SignInRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function signIn(SignInRequest $request)
    {
            $userData = $this->userService->signIn($request->validated());
            $userData['user'] = new UserResource($userData['user']);
            return $this->successResponse('User signed in successfully', $userData, 200);

    }

}
