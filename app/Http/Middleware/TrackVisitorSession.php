<?php

namespace App\Http\Middleware;

use App\Services\VisitorTrackingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackVisitorSession
{
    public function __construct(private readonly VisitorTrackingService $visitorTrackingService) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->isMethod('get')) {
            $this->visitorTrackingService->track($request);
        }

        return $response;
    }
}
