<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LogArticleSaveFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (preg_match('~\Aadmin/posts(?:/[0-9]+)?/?\z~', $request->path())
            && ($request->isMethod('POST') || $request->isMethod('PATCH') || ($request->isMethod('GET') && $response->getStatusCode() === 405))
            && ($response->getStatusCode() >= 400 || ($response->isRedirection() && ! preg_match('~/admin/posts(?:/[0-9]+/edit|/create)$~', parse_url($response->headers->get('Location', ''), PHP_URL_PATH) ?? '')))) {
            // Never log article content, URLs with queries, cookies, CSRF or exception data.
            Log::warning('Article save transport failure.', [
                'method' => $request->method(),
                'server_method' => $request->getRealMethod(),
                'status' => $response->getStatusCode(),
                'internal_redirect_status' => (int) $request->server('REDIRECT_STATUS', 0),
                'parent_redirect_status' => (int) $request->server('REDIRECT_REDIRECT_STATUS', 0),
                'content_length' => (int) $request->server('CONTENT_LENGTH', 0),
                'post_max_size' => ini_get('post_max_size'),
                'has_redirect_location' => $response->headers->has('Location'),
            ]);
        }

        return $response;
    }
}
