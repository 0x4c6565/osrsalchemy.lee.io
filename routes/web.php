<?php

use App\Http\Controllers\AlchemyController;
use Illuminate\Support\Facades\Route;

Route::get('/', [AlchemyController::class, 'index'])->name('alchemy.index');
