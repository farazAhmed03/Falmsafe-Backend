<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicalRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'appointment_id',
        'diagnosis',
        'prescription',
        'notes',
        'files'
    ];

    protected $casts = [
        'files' => 'array'
    ];

    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }
}
