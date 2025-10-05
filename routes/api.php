<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/instructor/certificate/autofill', [App\Http\Controllers\Instructor\ProfessionalDevelopmentController::class, 'autofillCertificate'])->middleware('auth');