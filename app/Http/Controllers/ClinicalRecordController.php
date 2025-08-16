<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClinicalRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ClinicalRecordController extends Controller
{
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $query = ClinicalRecord::with('appointment');

            if ($user->role === 'doctor') {
                // Doctor can see records for appointments where they are the doctor
                $query->whereHas('appointment', function ($q) use ($user) {
                    $q->where('doctor_id', $user->id);
                });
            } elseif ($user->role === 'patient') {
                // Patient can only see their own records
                $query->whereHas('appointment', function ($q) use ($user) {
                    $q->where('patient_id', $user->id);
                });
            }

            $records = $query->get();

            // Ensure files is always an array for each record and properly formatted
            $records->each(function ($record) {
                // Make sure files is always an array
                if (is_string($record->files)) {
                    $record->files = json_decode($record->files, true) ?? [];
                } else {
                    $record->files = $record->files ?? [];
                }

                // Add appointment details to each record for easier frontend access
                if ($record->appointment) {
                    $record->appointment_date = $record->appointment->appointment_date;
                    $record->doctor_name = $record->appointment->doctor_name ?? 'Unknown Doctor';
                    $record->patient_name = $record->appointment->patient_name ?? 'Unknown Patient';
                    // Add appointment status
                    $record->appointment_status = $record->appointment->status;
                    // Add patient uploaded files flag
                    $record->has_patient_files = !empty(array_filter($record->files, function($file) {
                        return isset($file['uploaded_by']) && $file['uploaded_by'] === 'patient';
                    }));
                }
            });

            // Sort records by appointment date descending
            $records = $records->sortByDesc('appointment_date')->values();

            return response()->json([
                'success' => true,
                'data' => $records,
            ]);
        } catch (\Exception $e) {
            Log::error('Clinical records retrieval error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving clinical records',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request)
    {
        try {
            // Check if user is doctor
            if ($request->user()->role !== 'doctor') {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Unauthorized - Only doctors can create records',
                    ],
                    403,
                );
            }

            $data = $request->validate([
                'appointment_id' => 'required|exists:appointments,id',
                'diagnosis' => 'required|string',
                'prescription' => 'nullable|string',
                'notes' => 'nullable|string',
                'files' => 'nullable|array',
                'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            ]);

            // Handle file uploads
            $filePaths = [];
            if ($request->hasFile('files')) {
                foreach ($request->file('files') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('clinical_records', $fileName, 'public');
                    $filePaths[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'uploaded_by' => 'doctor', // Mark as uploaded by doctor
                    ];
                }
            }

            $record = ClinicalRecord::create([
                'appointment_id' => $data['appointment_id'],
                'diagnosis' => $data['diagnosis'],
                'prescription' => $data['prescription'] ?? null,
                'notes' => $data['notes'] ?? null,
                'files' => json_encode($filePaths), // Store as JSON string
            ]);

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Clinical record saved successfully',
                    'record' => $record,
                ],
                201,
            );
        } catch (\Exception $e) {
            Log::error('Clinical record creation error: ' . $e->getMessage());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Error creating record',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function show($appointment_id)
    {
        try {
            $record = ClinicalRecord::where('appointment_id', $appointment_id)
                ->with('appointment') // Load appointment relationship if exists
                ->first();

            if (!$record) {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Record not found',
                    ],
                    404,
                );
            }

            // Ensure files is always an array and properly decoded
            if (is_string($record->files)) {
                $record->files = json_decode($record->files, true) ?? [];
            } else {
                $record->files = $record->files ?? [];
            }

            // Add appointment details for easier frontend access
            if ($record->appointment) {
                $record->appointment_date = $record->appointment->appointment_date;
                $record->doctor_name = $record->appointment->doctor_name ?? 'Unknown Doctor';
                $record->patient_name = $record->appointment->patient_name ?? 'Unknown Patient';
            }

            return response()->json([
                'success' => true,
                'data' => $record,
            ]);
        } catch (\Exception $e) {
            Log::error('Clinical record retrieval error: ' . $e->getMessage());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Error retrieving record',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function update(Request $request, $id)
    {
        try {
            // Check if user is doctor
            if ($request->user()->role !== 'doctor') {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Unauthorized - Only doctors can update records',
                    ],
                    403,
                );
            }

            $data = $request->validate([
                'diagnosis' => 'sometimes|required|string',
                'prescription' => 'nullable|string',
                'notes' => 'nullable|string',
                'files' => 'nullable|array',
                'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120',
            ]);

            $record = ClinicalRecord::findOrFail($id);

            $updateData = [
                'diagnosis' => $data['diagnosis'] ?? $record->diagnosis,
                'prescription' => $data['prescription'] ?? $record->prescription,
                'notes' => $data['notes'] ?? $record->notes,
            ];

            // Handle new file uploads
            if ($request->hasFile('files')) {
                // Get existing files and decode if string
                $existingFiles = is_string($record->files)
                    ? json_decode($record->files, true) ?? []
                    : ($record->files ?? []);

                $newFilePaths = [];

                foreach ($request->file('files') as $file) {
                    $fileName = time() . '_' . $file->getClientOriginalName();
                    $path = $file->storeAs('clinical_records', $fileName, 'public');
                    $newFilePaths[] = [
                        'name' => $file->getClientOriginalName(),
                        'path' => $path,
                        'size' => $file->getSize(),
                        'type' => $file->getMimeType(),
                        'uploaded_by' => 'doctor',
                    ];
                }

                $updateData['files'] = json_encode(array_merge($existingFiles, $newFilePaths));
            }

            $record->update($updateData);

            return response()->json([
                'success' => true,
                'message' => 'Record updated successfully',
                'record' => $record,
            ]);
        } catch (\Exception $e) {
            Log::error('Clinical record update error: ' . $e->getMessage());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Error updating record',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    public function uploadPatientFiles(Request $request, $appointment_id)
    {
        try {
            Log::info("Patient file upload started for appointment: $appointment_id");

            $request->validate([
                'files' => 'required|array',
                'files.*' => 'file|mimes:jpeg,png,pdf,doc,docx|max:10240', // 10MB max
            ]);

            // Get or create record
            $record = ClinicalRecord::where('appointment_id', $appointment_id)->first();
            if (!$record) {
                // Create a basic record if it doesn't exist
                $record = ClinicalRecord::create([
                    'appointment_id' => $appointment_id,
                    'diagnosis' => 'Patient uploaded documents - Awaiting doctor review',
                    'files' => json_encode([]),
                ]);
                Log::info("Created new clinical record for appointment: $appointment_id");
            }

            $uploadedFiles = [];
            foreach ($request->file('files') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('clinical_records', $fileName, 'public');
                $uploadedFiles[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType(),
                    'uploaded_by' => 'patient',
                    'uploaded_at' => now()->toISOString(),
                ];
                Log::info("Uploaded file: " . $file->getClientOriginalName() . " as patient file");
            }

            // Get existing files and decode if string
            $existingFiles = is_string($record->files)
                ? json_decode($record->files, true) ?? []
                : ($record->files ?? []);

            Log::info("Existing files: " . json_encode($existingFiles));
            Log::info("New uploaded files: " . json_encode($uploadedFiles));

            // Merge with existing files and save as JSON
            $allFiles = array_merge($existingFiles, $uploadedFiles);
            $record->files = json_encode($allFiles);
            $record->save();

            Log::info("Saved all files: " . json_encode($allFiles));

            return response()->json([
                'success' => true,
                'message' => 'Files uploaded successfully by patient',
                'files' => $uploadedFiles,
                'record' => $record,
                'total_files' => count($allFiles),
                'patient_files' => count(array_filter($allFiles, function($file) {
                    return isset($file['uploaded_by']) && $file['uploaded_by'] === 'patient';
                }))
            ]);
        } catch (\Exception $e) {
            Log::error('Patient file upload error: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Error uploading files',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // Delete specific file from record
    public function deleteFile(Request $request, $recordId)
    {
        try {
            $record = ClinicalRecord::findOrFail($recordId);
            $fileIndex = $request->file_index;

            // Decode files if string
            $files = is_string($record->files)
                ? json_decode($record->files, true) ?? []
                : ($record->files ?? []);

            if (isset($files[$fileIndex])) {
                $filePath = $files[$fileIndex]['path'];
                Storage::disk('public')->delete($filePath);

                unset($files[$fileIndex]);
                $record->files = json_encode(array_values($files));
                $record->save();

                return response()->json([
                    'success' => true,
                    'message' => 'File deleted successfully',
                ]);
            }

            return response()->json(
                [
                    'success' => false,
                    'message' => 'File not found',
                ],
                404,
            );
        } catch (\Exception $e) {
            Log::error('File deletion error: ' . $e->getMessage());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Error deleting file',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }

    // Download file
    public function downloadFile($recordId, $fileIndex)
    {
        try {
            $record = ClinicalRecord::findOrFail($recordId);

            // Decode files if string
            $files = is_string($record->files)
                ? json_decode($record->files, true) ?? []
                : ($record->files ?? []);

            if (isset($files[$fileIndex])) {
                $filePath = $files[$fileIndex]['path'];
                $fileName = $files[$fileIndex]['name'];

                if (Storage::disk('public')->exists($filePath)) {
                    return Storage::disk('public')->download($filePath, $fileName);
                }
            }

            return response()->json(
                [
                    'success' => false,
                    'message' => 'File not found',
                ],
                404,
            );
        } catch (\Exception $e) {
            Log::error('File download error: ' . $e->getMessage());
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Error downloading file',
                    'error' => $e->getMessage(),
                ],
                500,
            );
        }
    }
}
