<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\ClinicalRecordController;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

// Protected routes — require authentication with sanctum token
Route::middleware('auth:sanctum')->group(function () {
    // Current logged-in user info
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'data' => $request->user(),
        ]);
    });

    // Logout
    Route::post('/logout', [AuthController::class, 'logout']);

    // User profile
    Route::get('/profile', [ProfileController::class, 'getProfile']);
    Route::put('/profile/update-profile', [ProfileController::class, 'updateProfile']);

    // Users (Doctors and Patients)
    Route::get('/doctors', [UserController::class, 'getAllDoctors']);
    Route::get('/doctors/{id}', [UserController::class, 'getDoctor']);
    Route::get('/patients', [UserController::class, 'getAllPatients']);
    Route::get('/patients/{id}', [UserController::class, 'getPatient']);

    // Appointments
    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::get('/appointments/all', [AppointmentController::class, 'getBothAppointments']);
    Route::get('/appointments/{id}', [AppointmentController::class, 'show']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::put('/appointments/{id}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{id}', [AppointmentController::class, 'destroy']);

    // Reviews
    Route::post('/appointments/{appointment}/review', [ReviewController::class, 'store']);
    Route::put('/reviews/{id}', [ReviewController::class, 'update']);
    Route::delete('/reviews/{id}', [ReviewController::class, 'destroy']);
    Route::get('/doctors/{id}/reviews', [ReviewController::class, 'getDoctorReviews']);

    // Clinical Records Routes
    Route::prefix('clinical-records')->group(function () {
        Route::get('/', [ClinicalRecordController::class, 'index']);
        Route::post('/', [ClinicalRecordController::class, 'store']);
        Route::get('/{appointment_id}', [ClinicalRecordController::class, 'show']);
        Route::put('/{id}', [ClinicalRecordController::class, 'update']);
        Route::post('/{appointment_id}/upload-files', [ClinicalRecordController::class, 'uploadPatientFiles']);
        Route::delete('/{recordId}/files', [ClinicalRecordController::class, 'deleteFile']);
        Route::get('/{recordId}/download/{fileIndex}', [ClinicalRecordController::class, 'downloadFile']);
    });
});
