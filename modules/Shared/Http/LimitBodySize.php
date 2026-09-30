<?php

namespace Modules\Shared\Http;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Rejects oversized bodies before anything parses them. Default 64 KB. */
final class LimitBodySize
{
    public function handle(Request $request, Closure $next, int $maxBytes = 65_536): Response
    {
        $declared = (int) $request->headers->get('Content-Length', '0');
        if ($declared > $maxBytes || strlen($request->getContent()) > $maxBytes) {
            throw new HttpException(413, 'Payload too large');
        }

        return $next($request);
    }
}
