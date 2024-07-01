<?php

use Illuminate\Support\Facades\Route;
use Twilio\Rest\Client;

Route::post('/new_message',[\App\Http\Controllers\ConversationalController::class, 'new_message']);
Route::post('/status',[\App\Http\Controllers\ConversationalController::class, 'status']);
