<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Welcome', [
    'laravel' => app()->version(),
    'php' => PHP_VERSION,
]))->name('home');
