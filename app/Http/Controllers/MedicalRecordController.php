<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use App\Http\Requests\MedicalRecordRequest;
use App\Models\Clinic;
use App\Models\User;

class MedicalRecordController extends Controller
{
    public function store(MedicalRecordRequest $request)
    {
        $recordableType = $request->is_clinic ? Clinic::class : User::class;
        $recordableId = $request->is_clinic ? auth()->user()->clinic->id : auth()->id();

        $medicalRecord = MedicalRecord::create([
            'details' => $request->details,
            'date' => $request->date,
            'pet_id' => $request->pet_id,
            'recordable_type' => $recordableType,
            'recordable_id' => $recordableId
        ]);

        return response()->json([
            'success' => true,
            'message' => 'تم إضافة السجل الطبي بنجاح',
            'data' => $medicalRecord
        ], 201);
    }
}
