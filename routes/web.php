<?php

use App\Http\Controllers\SeatController;
use Illuminate\Support\Facades\Route;


Route::get('/', [SeatController::class,'index'])->name('seats.home');
Route::post('/allocate', [SeatController::class,'allocate'])->name('seats.allocate');
Route::get('/map', [SeatController::class,'map'])->name('seats.map');