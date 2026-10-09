<?php

use App\Http\Controllers\ClinicController;
use App\Http\Controllers\MfaController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::view('/login', 'auth.login')->name('login');
    Route::post('/login', [ClinicController::class, 'login'])->middleware('throttle:10,1');
});
Route::middleware('auth')->group(function () {
    Route::get('/mfa', [MfaController::class, 'show'])->name('mfa');
    Route::post('/mfa', [MfaController::class, 'verify'])->middleware('throttle:10,1');
    Route::post('/logout', [ClinicController::class, 'logout'])->name('logout');
});
Route::middleware(['auth', 'mfa'])->group(function () {
    Route::get('/', [ClinicController::class, 'dashboard'])->name('dashboard');
    Route::get('/patients', [ClinicController::class, 'patientIndex'])->name('patients.index');
    Route::get('/patients/create', [ClinicController::class, 'patientCreate'])->name('patients.create');
    Route::post('/patients', [ClinicController::class, 'patientStore'])->name('patients.store');
    Route::get('/patients/{patient}', [ClinicController::class, 'patientShow'])->name('patients.show');
    Route::post('/patients/{patient}/notes', [ClinicController::class, 'noteStore'])->name('notes.store');
    Route::get('/appointments', [ClinicController::class, 'appointmentIndex'])->name('appointments');
    Route::post('/appointments', [ClinicController::class, 'appointmentStore'])->name('appointments.store');
    Route::patch('/appointments/{appointment}', [ClinicController::class, 'appointmentStatus'])->name('appointments.status');
    Route::get('/billing', [ClinicController::class, 'billing'])->name('billing');
    Route::get('/team', [ClinicController::class, 'team'])->name('team');
    Route::get('/audit', [ClinicController::class, 'auditIndex'])->name('audit');
});
