<?php

namespace App\Http\Middleware;

use App\Models\Post;
use App\Services\Analytics\VisitorTracker;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

class PublicVisitReceipt
{
    public function handle(Request $request, Closure $next): SymfonyResponse
    {
        $response = $next($request);
        try {
            $tracker = app(VisitorTracker::class);
            if ($request->method() !== 'GET' || ! $tracker->eligible($request) || ! $response instanceof Response || $response->status() !== 200) {
                return $response;
            }
            $view = $response->getOriginalContent();
            if (! $view instanceof View || ! in_array($view->name(), ['public.home', 'public.post', 'public.page', 'public.archive', 'public.explore', 'public.categories', 'public.discovery', 'public.video', 'public.course', 'public.path', 'public.quiz'], true)
                || $request->routeIs('search') || ($view->getData()['preview'] ?? false)) {
                return $response;
            }
            $post = $view->getData()['post'] ?? null;
            if ($post instanceof Post && ($post->status !== 'published' || ! $post->published_at?->isPast())) {
                return $response;
            }
            $visitor = $request->cookie(config('analytics.cookie'));
            if (! is_string($visitor) || ! preg_match('/\A[a-f0-9]{64}\z/', $visitor)) {
                $visitor = bin2hex(random_bytes(32));
                $response->headers->setCookie(cookie(config('analytics.cookie'), $visitor, 90 * 24 * 60, '/', null, $request->isSecure() || app()->environment('production'), true, false, 'lax'));
            }
            $receipt = $tracker->receipt($visitor, '/'.trim($request->path(), '/'), $post instanceof Post ? $post->id : null);
            $marker = '<span hidden data-visit-receipt="'.e($receipt).'" data-visit-url="'.e(route('visits.confirm')).'" data-visit-csrf="'.e(csrf_token()).'"></span>';
            $response->setContent(str_replace('</main>', $marker.'</main>', $response->getContent()));
        } catch (Throwable $exception) {
            VisitorTracker::failure($exception);
        }

        return $response;
    }
}
