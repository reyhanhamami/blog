<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

use function Livewire\before;

class LogLivewireTransportFailures
{
    public function handle(Request $request, Closure $next): Response
    {
        $entryMethod = $request->getRealMethod();
        $isUpdate = $request->hasHeader('X-Livewire')
            && preg_match('~\Alivewire-[a-f0-9]{8}/update/?\z~', $request->path());
        $origins = [];
        // Observe only checksum-verified metadata, before persistent routing can fail.
        // This listener neither repairs nor alters snapshots or middleware execution.
        $stop = $isUpdate ? before('snapshot-verified', function (array $snapshot) use (&$origins, $request): void {
            if (count($origins) >= 10) {
                return;
            }
            $memo = $snapshot['memo'];
            $path = is_string($memo['path'] ?? null) ? trim($memo['path'], '/') : null;
            $origins[] = [
                'component' => ($memo['name'] ?? null) === 'public-theme-editor' ? 'public-theme-editor' : 'other',
                'origin' => match ($path) {
                    'admin/settings' => 'settings',
                    trim($request->path(), '/') => 'update',
                    default => 'other',
                },
                'method' => in_array($memo['method'] ?? null, ['GET', 'POST'], true) ? $memo['method'] : 'other',
            ];
        }) : null;
        try {
            $response = $next($request);
        } finally {
            // Required for long-running workers and repeated HTTP test requests.
            $stop && $stop();
        }

        if ($isUpdate
            && ($response->getStatusCode() === 405 || $response->isRedirection())) {
            $forwarded = strtolower((string) $request->header('X-Forwarded-Proto', ''));
            $exception = $response->exception ?? null;
            $rejectedMethod = null;
            $frames = [];
            if ($exception instanceof \Throwable && $response->getStatusCode() === 405) {
                preg_match('/The ([A-Z]+) method/', $exception->getMessage(), $match);
                $rejectedMethod = $match[1] ?? null;
                foreach (array_slice($exception->getTrace(), 0, 5) as $frame) {
                    $frames[] = [
                        'file' => basename($frame['file'] ?? ''),
                        'class' => $frame['class'] ?? '',
                        'function' => $frame['function'] ?? '',
                    ];
                }
            }
            // Metadata only: never log cookies, tokens, snapshots, headers,
            // payloads, query strings, or the exception object.
            Log::warning('Livewire transport failure.', [
                'method' => $request->method(),
                'server_method' => $request->getRealMethod(),
                'entry_method' => $entryMethod,
                'internal_redirect_status' => (int) $request->server('REDIRECT_STATUS', 0),
                'rejected_method' => $rejectedMethod,
                'routing_frames' => $frames,
                'verified_origins' => $origins,
                'path' => $request->path(),
                'status' => $response->getStatusCode(),
                'scheme' => $request->getScheme(),
                'forwarded_proto' => in_array($forwarded, ['http', 'https'], true) ? $forwarded : ($forwarded === '' ? 'absent' : 'unrecognized'),
                'trusted_proxy' => $request->isFromTrustedProxy(),
                'has_redirect_location' => $response->headers->has('Location'),
            ]);
        }

        return $response;
    }
}
