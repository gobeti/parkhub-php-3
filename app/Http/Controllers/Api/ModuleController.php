<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Providers\ModuleServiceProvider;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    /**
     * GET /api/v1/modules — list all modules with their enabled state.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'modules' => ModuleServiceProvider::all(),
            'version' => SystemController::appVersion(),
        ]);
    }
}
