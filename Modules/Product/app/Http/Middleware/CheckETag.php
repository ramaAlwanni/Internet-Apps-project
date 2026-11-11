<?php

namespace Modules\Product\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckETag 
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->isSuccessful() && $request->isMethod('GET')) {
            $content = $response->getContent();

            $etag = '"' . md5($content) . '"';

            $response->setEtag($etag);

            if ($request->header('If-None-Match') === $etag) {
                return response()->noContent(304);
            }
        }
        return $response;
    }
}