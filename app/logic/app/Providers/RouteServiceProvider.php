<?php
declare(strict_types=1);

// Compatibility shim for stale Composer classmaps on shared hosting.
// Canonical provider lives at app/logic/providers/RouteServiceProvider.php.
require_once dirname(__DIR__, 2) . '/providers/RouteServiceProvider.php';

