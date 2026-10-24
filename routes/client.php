<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use PterodactylMcp\Http\Controllers\McpController;

// POST /api/client/extensions/mcp
Route::post('/', McpController::class);
Route::match(['get', 'delete'], '/', [McpController::class, 'stream']);
Route::get('/tools', [McpController::class, 'tools']);
