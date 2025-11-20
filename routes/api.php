<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Models\User;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Login route to authenticate and create token
Route::post('/tokens/create', function (Request $request) {
    $request->validate([
        'email' => 'required|email',
        'password' => 'required',
        'token_name' => 'required',
    ]);

    $user = User::where('email', $request->email)->first();

    if (! $user || ! Hash::check($request->password, $user->password)) {
        throw ValidationException::withMessages([
            'email' => ['The provided credentials are incorrect.'],
        ]);
    }

    $token = $user->createToken($request->token_name);

    return ['token' => $token->plainTextToken];
});

// API Login route (named 'login' for Laravel's auth system)
Route::post('/login', function (Request $request) {
    return response()->json([
        'message' => 'Unauthenticated. Please use /api/tokens/create to get a token.',
        'required_fields' => ['email', 'password', 'token_name']
    ], 401);
})->name('login');

// Handle GET requests to login (for redirect compatibility)
Route::get('/login', function (Request $request) {
    return response()->json([
        'message' => 'Unauthenticated. Please use POST /api/tokens/create to get a token.',
        'required_fields' => ['email', 'password', 'token_name']
    ], 401);
});

// Revoke current token
Route::post('/tokens/revoke', function (Request $request) {
    $request->user()->currentAccessToken()->delete();
    
    return ['message' => 'Token revoked successfully'];
})->middleware('auth:sanctum');

// Get Readings (protected route)
Route::get('/readings', [App\Http\Controllers\ReadingController::class, 'index'])->middleware('auth:sanctum');
Route::get('/readings/list', [App\Http\Controllers\ReadingController::class, 'list'])->middleware('auth:sanctum');
Route::get('/readings/show', [App\Http\Controllers\ReadingController::class, 'show'])->middleware('auth:sanctum');
Route::get('/readings/calculate-power', [App\Http\Controllers\ReadingController::class, 'calculatePower'])->middleware('auth:sanctum');
Route::get('/readings/monthly-people-counter', [App\Http\Controllers\ReadingController::class, 'getMonthlyReadingsPeopleCounter'])->middleware('auth:sanctum');