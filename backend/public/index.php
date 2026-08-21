<?php

declare(strict_types=1);

/**
 * Single entry point for the PHP API.
 * All requests are routed through ApiController.
 */

$backendRoot = dirname(__DIR__);

require $backendRoot . '/vendor/autoload.php';

use App\Http\ApiController;
use Dotenv\Dotenv;

// Load .env (optional — env vars can also be set at the OS level)
$dotenv = Dotenv::createImmutable($backendRoot);
$dotenv->safeLoad();

(new ApiController())->handle();
