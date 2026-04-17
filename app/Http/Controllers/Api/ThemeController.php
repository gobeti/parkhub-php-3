<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateDesignThemeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ThemeController extends Controller
{
    private const VALID_THEMES = ['classic', 'glass', 'bento', 'brutalist', 'neon', 'warm'];

    private const DEFAULT_THEME = 'classic';

    public function show(Request $request): JsonResponse
    {
        $prefs = $request->user()->preferences ?? [];
        $theme = $prefs['design_theme'] ?? self::DEFAULT_THEME;

        if (! in_array($theme, self::VALID_THEMES, true)) {
            $theme = self::DEFAULT_THEME;
        }

        return response()->json(['design_theme' => $theme]);
    }

    public function update(UpdateDesignThemeRequest $request): JsonResponse
    {
        $user = $request->user();
        $prefs = $user->preferences ?? [];
        $prefs['design_theme'] = $request->input('design_theme');
        $user->update(['preferences' => $prefs]);

        return response()->json(['design_theme' => $prefs['design_theme']]);
    }
}
