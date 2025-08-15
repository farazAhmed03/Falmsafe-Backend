<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CanManageAppointment
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        // Admin NOT allowed to insert/update/delete
        if ($user->role === 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Admins cannot create/update/delete appointments.',
            ], 403);
        }

        if ($request->isMethod('post')) {
            $doctor_id = $request->doctor_id;
            // Use patient_id from request if sent, otherwise fallback to authenticated user ID
            $patient_id = $request->patient_id ?? $user->id;

            if ($user->id != $doctor_id && $user->id != $patient_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You must be doctor or patient of this appointment.',
                ], 403);
            }
        } else {
            // For update or delete: check if user owns the appointment
            $appointmentId = $request->route('id');
            $appointment = Appointment::find($appointmentId);

            if (!$appointment) {
                return response()->json([
                    'success' => false,
                    'message' => 'Appointment not found.',
                ], 404);
            }

            if ($user->id != $appointment->doctor_id && $user->id != $appointment->patient_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized: You must be doctor or patient of this appointment.',
                ], 403);
            }
        }

        return $next($request);
    }
}
