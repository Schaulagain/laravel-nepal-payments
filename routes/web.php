<?php

use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\HblNotificationController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

Route::get('/', [StoreController::class, 'index'])->name('store.index');

Route::get('/products/{product}/checkout', [CheckoutController::class, 'create'])->name('checkout.create');
Route::post('/products/{product}/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

Route::get('/payments/{payment}', [PaymentController::class, 'show'])->name('payments.show');
Route::match(['get', 'post'], '/payments/{payment}/callback', [PaymentController::class, 'callback'])->name('payments.callback');
Route::post('/payments/hbl/notify', HblNotificationController::class)->name('payments.hbl.notify');
Route::get('/payments/{payment}/invoice', [PaymentController::class, 'invoice'])->name('payments.invoice');
