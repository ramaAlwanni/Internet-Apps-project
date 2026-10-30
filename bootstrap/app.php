<?php

use App\Exceptions\Auth\InvalidCredentialsException;
use App\Exceptions\BaseException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /**
         * For this exceptions:
         * we don't need to register the message in the log file
         *  because it is a business exception and we will handle it in the render method
         */
        $exceptions->dontReport([
            ValidationException::class,
            InvalidCredentialsException::class,
        ]);

        $exceptions->render(function (AuthenticationException $e){
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated',
                'data' => null
            ], 401);
        });

        $exceptions->render(function (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error Validation failed',
                'data' =>  $e->errors(),
            ], 422);
        });

        $exceptions->render(function (NotFoundHttpException $e)  {
            $previous = $e->getPrevious();
            $message = $previous instanceof ModelNotFoundException
                ? class_basename($previous->getModel()) . ' not found'
                : 'Resource not found';

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => null
            ], 404);
        });

        $exceptions->render(function (BaseException $e)  {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'data'    => null,
            ], $e->getHttpCode());
        });

        $exceptions->render(function (Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Server error',
                'data'    => null
            ], 500);
        });

    })->create();
