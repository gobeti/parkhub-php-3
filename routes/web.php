<?php

use Illuminate\Support\Facades\Route;

// Health check endpoint
Route::get('/health', function () {
    return response('OK', 200)
        ->header('Content-Type', 'text/plain');
});

// Serve favicon.ico if it exists
Route::get('/favicon.ico', function () {
    $faviconPath = public_path('favicon.ico');
    if (file_exists($faviconPath)) {
        return response()->file($faviconPath);
    }
    return response('', 204); // No content if favicon not present
});

// Redirect language-prefixed login URLs to /login
Route::get('/{lang}/login', function ($lang) {
    return redirect('/login');
})->where('lang', 'en|es'); // add more language codes here if needed

// SPA root route (optional)
Route::get('/', function () {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return response('ParkHub PHP Edition - Frontend not built yet. Run: cd resources/js && npm install && npm run build', 200);
});

// SPA fallback - serve index.html for all other non-API, non-health, non-storage routes
Route::get('/{any}', function ($any) {
    $indexPath = public_path('index.html');
    if (file_exists($indexPath)) {
        return response()->file($indexPath);
    }
    return response('ParkHub PHP Edition - Frontend not built yet. Run: cd resources/js && npm install && npm run build', 200);
})->where('any', '^(?!api|health|sanctum|storage|favicon\.ico).*$');
