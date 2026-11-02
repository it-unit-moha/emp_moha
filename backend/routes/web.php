<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['service' => 'GALMS API', 'version' => 'v1', 'health' => '/up']));
