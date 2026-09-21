<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClinicSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'clinic_id',
        'date',
        'time',
        'status',
        'notes',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function clinic()
    {
        return $this->belongsTo(Clinic::class);
    }
}