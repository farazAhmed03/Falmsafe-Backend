<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Appointment;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, $appointmentId)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'nullable|string',
        ]);

        Review::create([
            'appointment_id' => $appointmentId,
            'doctor_id' => auth()->user()->id, // or from appointment relation
            'patient_id' => auth()->user()->id,
            'rating' => $validated['rating'],
            'comment' => $validated['review'],
        ]);

        return response()->json(['message' => 'Review submitted successfully']);
    }



    public function update(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        if ($review->patient_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $review->update($request->only('rating', 'comment'));

        return response()->json(['success' => true, 'data' => $review]);
    }

    public function destroy($id)
    {
        $review = Review::findOrFail($id);

        if ($review->patient_id !== auth()->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $review->delete();

        return response()->json(['success' => true]);
    }

    public function getDoctorReviews($doctorId)
    {
        $reviews = Review::where('doctor_id', $doctorId)->with('patient:id,name')->latest()->get();

        return response()->json(['success' => true, 'data' => $reviews]);
    }
}
