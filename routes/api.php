<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\ReconciliationController;
use App\Http\Controllers\StorefrontController;
use Illuminate\Support\Facades\Route;

Route::post('/orders', [OrderController::class, 'store']);
Route::get('/orders/{id}', [OrderController::class, 'show']);
Route::post('/webhooks/payment', PaymentWebhookController::class);
Route::get('/admin/reconciliation', ReconciliationController::class);
Route::get('/storefront', StorefrontController::class);
