<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ClinicalRecord;

class ClinicalRecordController extends Controller {

    // Doctor creates record
    public function store(Request $request){
        $data = $request->validate([
            'appointment_id'=>'required|exists:appointments,id',
            'diagnosis'=>'required|string',
            'prescription'=>'nullable|string',
            'notes'=>'nullable|string',
        ]);

        $record = ClinicalRecord::create($data);
        return response()->json(['message'=>'Clinical record saved','record'=>$record]);
    }

    // Patient views record
    public function show($appointment_id){
        $record = ClinicalRecord::where('appointment_id',$appointment_id)->first();
        if(!$record) return response()->json(['message'=>'Record not found'],404);
        return response()->json($record);
    }

    // Optional: Doctor updates record
    public function update(Request $request, $id){
        $record = ClinicalRecord::findOrFail($id);
        $record->update($request->only(['diagnosis','prescription','notes']));
        return response()->json(['message'=>'Record updated','record'=>$record]);
    }
}
