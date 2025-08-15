<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    // Get authenticated user's profile
    public function getProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data' => $user->only([
                    'id', 'first_name', 'last_name', 'email', 'role',
                    'address', 'dob', 'gender', 'bio', 'photo', 'specialization'
                ]),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update authenticated user's profile
    public function updateProfile(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Log incoming request for debugging
            \Log::info('Profile update request:', [
                'user_id' => $user->id,
                'request_data' => $request->except(['password', 'photo']),
                'has_photo' => $request->hasFile('photo')
            ]);

            // Validate incoming fields
            $validator = Validator::make($request->all(), [
                'first_name' => 'sometimes|required|string|max:255',
                'last_name' => 'sometimes|required|string|max:255',
                'bio' => 'sometimes|nullable|string|max:1000',
                'specialization' => 'sometimes|nullable|string|max:255',
                'dob' => 'sometimes|nullable|date|before:today',
                'gender' => 'sometimes|nullable|string|in:male,female,other',
                'password' => 'sometimes|nullable|string|min:3',
                'photo' => 'sometimes|nullable|image|mimes:jpeg,jpg,png',
            ]);

            if ($validator->fails()) {
                \Log::error('Validation failed:', $validator->errors()->toArray());
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Update only provided fields (excluding password and photo handled separately)
            $fieldsToUpdate = ['first_name', 'last_name', 'bio', 'specialization', 'dob', 'gender', 'address'];

            foreach ($fieldsToUpdate as $field) {
                if (array_key_exists($field, $data)) {
                    $user->$field = $data[$field];
                }
            }

            // Handle password update separately if provided and not empty
            if (!empty($data['password'])) {
                $user->password = Hash::make($data['password']);
            }

            // Handle photo upload separately if provided
            if ($request->hasFile('photo')) {
                \Log::info('Processing photo upload');

                // Delete old photo if exists and stored locally (not external URLs)
                if ($user->photo &&
                    !str_contains($user->photo, 'dicebear.com') &&
                    !str_contains($user->photo, 'http') &&
                    Storage::disk('public')->exists($user->photo)) {
                    Storage::disk('public')->delete($user->photo);
                    \Log::info('Old photo deleted');
                }

                // Store new photo
                $path = $request->file('photo')->store('profile_photos', 'public');
                $user->photo = Storage::url($path); // Get full URL path
                \Log::info('New photo stored:', ['path' => $user->photo]);
            }

            // Save the user
            $saved = $user->save();
            \Log::info('User save result:', ['saved' => $saved]);

            if (!$saved) {
                throw new \Exception('Failed to save user data');
            }

            // Refresh user data to get updated values
            $user->refresh();

            return response()->json([
                'success' => true,
                'message' => 'Profile updated successfully',
                'data' => $user->only([
                    'id', 'first_name', 'last_name', 'email', 'role',
                    'address', 'dob', 'gender', 'bio', 'photo', 'specialization'
                ]),
            ]);

        } catch (\Exception $e) {
            \Log::error('Profile update error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request)
    {
        return $this->getProfile($request);
    }
}
