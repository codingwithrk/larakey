<?php

use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/settings');

Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
