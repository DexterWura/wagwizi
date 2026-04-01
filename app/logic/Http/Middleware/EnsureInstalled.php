<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        $installedFile = dirname(base_path(), 2) . DIRECTORY_SEPARATOR . 'secrets' . DIRECTORY_SEPARATOR . 'installed';

        if (!file_exists($installedFile) && !$request->is('install*')) {
            return redirect('/install');
        }

        return $next($request);
    }
}
