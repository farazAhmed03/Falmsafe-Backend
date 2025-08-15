<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    // Get all doctors
    public function getAllDoctors()
    {
        try {
            $doctors = User::where('role', 'doctor')
                          ->select('id', 'first_name', 'last_name', 'email', 'specialization', 'photo', 'bio')
                          ->get();

            return response()->json([
                'success' => true,
                'data' => $doctors
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch doctors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get single doctor by ID
    public function getDoctor($id)
    {
        try {
            $doctor = User::where('role', 'doctor')
                         ->select('id', 'first_name', 'last_name', 'email', 'specialization', 'bio', 'photo')
                         ->find($id);

            if (!$doctor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Doctor not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $doctor
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch doctor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get all patients
    public function getAllPatients()
    {
        try {
            $patients = User::where('role', 'patient')
                           ->select('id', 'first_name', 'last_name', 'email', 'photo', 'bio')
                           ->get();

            return response()->json([
                'success' => true,
                'data' => $patients
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patients',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get single patient by ID
    public function getPatient($id)
    {
        try {
            $patient = User::where('role', 'patient')
                          ->select('id', 'first_name', 'last_name', 'email', 'photo', 'bio')
                          ->find($id);

            if (!$patient) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $patient
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch patient',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
