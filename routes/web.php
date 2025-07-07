<?php

use App\Http\Controllers\ChatController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ChatController::class, 'index'])->name('document.index');
Route::post('/upload', [ChatController::class, 'uploadDocument'])->name('document.upload');
Route::get('/list', [ChatController::class, 'listDocuments'])->name('document.list');
Route::post('/query', [ChatController::class, 'queryDocuments'])->name('document.query');
Route::post('/summarize', [ChatController::class, 'summarizeDocument'])->name('document.summarize');