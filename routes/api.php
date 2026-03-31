<?php

use App\Http\Controllers\BeneficiaryController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:sanctum');

Route::post('/register', [UserController::class, 'createUser']);

Route::post('/login', [UserController::class, 'login']);

Route::middleware('auth:sanctum')->get('/dashboard', [UserController::class, 'dashboard']);
Route::middleware('auth:sanctum')->get('/profile', [UserController::class, 'getProfile']);
Route::middleware('auth:sanctum')->put('/profile', [UserController::class, 'updateProfile']);
Route::middleware('auth:sanctum')->post('/profile/picture', [UserController::class, 'uploadProfilePicture']);
Route::middleware('auth:sanctum')->delete('/profile/picture', [UserController::class, 'deleteProfilePicture']);

Route::middleware('auth:sanctum')->post('/logout', [UserController::class, 'logout']);

Route::middleware('auth:sanctum')->post('/deposit', [TransactionController::class, 'deposit']);

Route::middleware('auth:sanctum')->post('/verify-account', [TransactionController::class, 'verifyAccount']);

Route::middleware('auth:sanctum')->post('/transfer', [TransactionController::class, 'transfer']);

Route::middleware('auth:sanctum')->post('/withdraw', [TransactionController::class, 'withdraw']);

Route::middleware('auth:sanctum')->get('/balance', [UserController::class, 'balance']);

Route::middleware('auth:sanctum')->get('/transactions', [TransactionController::class, 'history']);

Route::middleware('auth:sanctum')->post('/set-pin', [UserController::class, 'setPin']);

Route::middleware('auth:sanctum')->put('/change-pin', [UserController::class, 'changePin']);

Route::middleware('auth:sanctum')->get('/beneficiaries', [BeneficiaryController::class, 'index']);

Route::middleware('auth:sanctum')->post('/beneficiaries', [BeneficiaryController::class, 'store']);

Route::middleware('auth:sanctum')->put('/beneficiaries/{id}', [BeneficiaryController::class, 'update']);

Route::middleware('auth:sanctum')->delete('/beneficiaries/{id}', [BeneficiaryController::class, 'destroy']);