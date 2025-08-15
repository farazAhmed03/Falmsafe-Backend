<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Appointment;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class AppointmentController extends Controller
{
    // Get appointments for logged-in user (doctor or patient)
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Appointment::with(['doctor:id,first_name,last_name', 'patient:id,first_name,last_name']);

        if ($user->role === 'doctor') {
            $query->where('doctor_id', $user->id);
        } elseif ($user->role === 'patient') {
            $query->where('patient_id', $user->id);
        }

        $appointments = $query->get();

        $data = $appointments->map(function ($appt) {
            return [
                'id' => $appt->id,
                'doctor_id' => $appt->doctor_id,
                'doctor_name' => $appt->doctor ? $appt->doctor->first_name . ' ' . $appt->doctor->last_name : null,
                'patient_id' => $appt->patient_id,
                'patient_name' => $appt->patient ? $appt->patient->first_name . ' ' . $appt->patient->last_name : null,
                'appointment_date' => $appt->appointment_date,
                'appointment_time' => $appt->appointment_time,
                'status' => $appt->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // Get all appointments where user is either doctor or patient
    public function getBothAppointments(Request $request)
    {
        $userId = $request->user()->id;

        $appointments = Appointment::with(['doctor', 'patient'])
            ->where(function ($query) use ($userId) {
                $query->where('doctor_id', $userId)->orWhere('patient_id', $userId);
            })
            ->get();

        $data = $appointments->map(function ($appt) {
            return [
                'id' => $appt->id,
                'doctor_id' => $appt->doctor_id,
                'doctor_name' => $appt->doctor ? $appt->doctor->first_name . ' ' . $appt->doctor->last_name : null,
                'patient_id' => $appt->patient_id,
                'patient_name' => $appt->patient ? $appt->patient->first_name . ' ' . $appt->patient->last_name : null,
                'appointment_date' => $appt->appointment_date,
                'appointment_time' => $appt->appointment_time,
                'status' => $appt->status,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    // Create new appointment (only patient)
    public function store(Request $request)
    {
        try {
            $user = $request->user();

            if ($user->role !== 'patient') {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Only patients can create appointments',
                    ],
                    403,
                );
            }

            $validator = Validator::make($request->all(), [
                'doctor_id' => 'required|exists:users,id',
                'appointment_date' => 'required|date|after_or_equal:today',
                'appointment_time' => 'required|date_format:H:i',
            ]);

            if ($validator->fails()) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Validation failed',
                        'errors' => $validator->errors(),
                    ],
                    422,
                );
            }

            // Prevent multiple active appointments with same doctor
            $existingAppointment = Appointment::where('doctor_id', $request->doctor_id)
                ->where('patient_id', $user->id)
                ->whereIn('status', ['scheduled', 'confirmed'])
                ->first();

            if ($existingAppointment) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'You already have an active appointment with this doctor. Please complete or cancel it before booking a new one.',
                    ],
                    400,
                );
            }

            $appointment = Appointment::create([
                'doctor_id' => $request->doctor_id,
                'patient_id' => $user->id,
                'appointment_date' => $request->appointment_date,
                'appointment_time' => $request->appointment_time,
                'status' => 'scheduled',
            ]);

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Appointment booked successfully, awaiting confirmation from doctor',
                    'data' => $appointment,
                ],
                201,
            );
        } catch (\Throwable $e) {
            \Log::error('Appointment store error: ' . $e->getMessage());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Server error: ' . $e->getMessage(),
                ],
                500,
            );
        }
    }

    // Update appointment status (only doctor can update own appointments)
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:scheduled,confirmed,cancelled,completed',
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'success' => false,
                    'message' => $validator->errors()->first(),
                ],
                422,
            );
        }

        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Appointment not found',
                ],
                404,
            );
        }

        // Use fill() and save() or update() method for better handling
        $appointment->update([
            'status' => $request->status,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Appointment updated successfully',
            'data' => $appointment->fresh(), // Get updated model
        ]);
    }

    // Delete appointment (patient or doctor can delete their own appointment)
    public function destroy(Request $request, $id)
    {
        $user = $request->user();

        $appointment = Appointment::find($id);

        if (!$appointment) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Appointment not found',
                ],
                404,
            );
        }

        if ($appointment->patient_id !== $user->id && $appointment->doctor_id !== $user->id) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Unauthorized',
                ],
                403,
            );
        }

        $appointment->delete();

        return response()->json([
            'success' => true,
            'message' => 'Appointment deleted successfully',
        ]);
    }
}
