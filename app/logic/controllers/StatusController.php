<?php

namespace App\Controllers;

use App\Services\Health\HealthCheckService;
use Illuminate\View\View;

class StatusController extends Controller
{
    public function __construct(
        private readonly HealthCheckService $health,
    ) {}

    public function show(): View
    {
        $result = $this->health->check();

        return view('status', [
            'healthy' => $result['healthy'],
            'checks'  => $result['checks'],
        ]);
    }
}
