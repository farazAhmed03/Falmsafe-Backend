<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClinicalRecord;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ClinicalRecordController extends Controller
{
    // Doctor creates record
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'appointment_id' => 'required|exists:appointments,id',
            'diagnosis' => 'required|string',
            'prescription' => 'nullable|string',
            'notes' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120' // 5MB max per file
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = [
            'appointment_id' => $request->appointment_id,
            'diagnosis' => $request->diagnosis,
            'prescription' => $request->prescription,
            'notes' => $request->notes
        ];

        // Handle file uploads
        if ($request->hasFile('files')) {
            $filePaths = [];
            foreach ($request->file('files') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('clinical_records', $fileName, 'public');
                $filePaths[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType()
                ];
            }
            $data['files'] = $filePaths;
        }

        $record = ClinicalRecord::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Clinical record saved successfully',
            'record' => $record
        ], 201);
    }

    // Patient views record
    public function show($appointment_id)
    {
        $record = ClinicalRecord::where('appointment_id', $appointment_id)->first();

        if (!$record) {
            return response()->json([
                'success' => false,
                'message' => 'Record not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $record
        ]);
    }

    // NEW: Patient uploads files to existing appointment
    public function uploadPatientFiles(Request $request, $appointment_id)
    {
        $validator = Validator::make($request->all(), [
            'files' => 'required|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if record exists, if not create one
        $record = ClinicalRecord::where('appointment_id', $appointment_id)->first();

        if (!$record) {
            // Create new record with patient files
            $record = ClinicalRecord::create([
                'appointment_id' => $appointment_id,
                'diagnosis' => 'Patient uploaded documents',
                'files' => []
            ]);
        }

        $existingFiles = $record->files ?? [];
        $newFilePaths = [];

        foreach ($request->file('files') as $file) {
            $fileName = time() . '_' . $file->getClientOriginalName();
            $path = $file->storeAs('clinical_records', $fileName, 'public');
            $newFilePaths[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'size' => $file->getSize(),
                'type' => $file->getMimeType(),
                'uploaded_by' => 'patient'
            ];
        }

        $record->files = array_merge($existingFiles, $newFilePaths);
        $record->save();

        return response()->json([
            'success' => true,
            'message' => 'Files uploaded successfully',
            'record' => $record
        ]);
    }

    // Doctor updates record
    public function update(Request $request, $id)
    {
        $record = ClinicalRecord::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'diagnosis' => 'sometimes|required|string',
            'prescription' => 'nullable|string',
            'notes' => 'nullable|string',
            'files' => 'nullable|array',
            'files.*' => 'file|mimes:jpg,jpeg,png,pdf,doc,docx|max:5120'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $updateData = $request->only(['diagnosis', 'prescription', 'notes']);

        // Handle new file uploads
        if ($request->hasFile('files')) {
            $existingFiles = $record->files ?? [];
            $newFilePaths = [];

            foreach ($request->file('files') as $file) {
                $fileName = time() . '_' . $file->getClientOriginalName();
                $path = $file->storeAs('clinical_records', $fileName, 'public');
                $newFilePaths[] = [
                    'name' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'type' => $file->getMimeType()
                ];
            }

            $updateData['files'] = array_merge($existingFiles, $newFilePaths);
        }

        $record->update($updateData);

        return response()->json([
            'success' => true,
            'message' => 'Record updated successfully',
            'record' => $record
        ]);
    }

    // Delete specific file from record
    public function deleteFile(Request $request, $recordId)
    {
        $record = ClinicalRecord::findOrFail($recordId);
        $fileIndex = $request->file_index;

        if (isset($record->files[$fileIndex])) {
            $filePath = $record->files[$fileIndex]['path'];
            Storage::disk('public')->delete($filePath);

            $files = $record->files;
            unset($files[$fileIndex]);
            $record->files = array_values($files); // Re-index array
            $record->save();

            return response()->json([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found'
        ], 404);
    }

    // Download file - FIXED METHOD
    public function downloadFile($recordId, $fileIndex)
    {
        $record = ClinicalRecord::findOrFail($recordId);

        if (isset($record->files[$fileIndex])) {
            $filePath = $record->files[$fileIndex]['path'];
            $fileName = $record->files[$fileIndex]['name'];

            if (Storage::disk('public')->exists($filePath)) {
                return Storage::disk('public')->download($filePath, $fileName);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'File not found'
        ], 404);
    }
}
