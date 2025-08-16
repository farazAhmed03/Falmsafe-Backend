<?php
class ClinicalRecordController extends Controller {

    public function store(Request $request)
    {
        try {
            // Check if user is doctor
            if ($request->user()->role !== 'doctor') {
                return response()->json([
                    'message' => 'Unauthorized - Only doctors can create records'
                ], 403);
            }

            $data = $request->validate([
                'appointment_id' => 'required|exists:appointments,id',
                'diagnosis' => 'required|string',
                'prescription' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $record = ClinicalRecord::create($data);

            return response()->json([
                'message' => 'Clinical record saved',
                'record' => $record
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error creating record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(int $appointment_id)
    {
        try {
            $record = ClinicalRecord::where('appointment_id', $appointment_id)->first();

            if (!$record) {
                return response()->json(['message' => 'Record not found'], 404);
            }

            return response()->json($record);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            // Check if user is doctor
            if ($request->user()->role !== 'doctor') {
                return response()->json([
                    'message' => 'Unauthorized - Only doctors can update records'
                ], 403);
            }

            $data = $request->validate([
                'diagnosis' => 'sometimes|required|string',
                'prescription' => 'nullable|string',
                'notes' => 'nullable|string',
            ]);

            $record = ClinicalRecord::findOrFail($id);
            $record->update($data);

            return response()->json([
                'message' => 'Record updated',
                'record' => $record
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Error updating record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
