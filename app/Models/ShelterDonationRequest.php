<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShelterDonationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'shelter_id',
        'item',
        'priority',
        'quantity',
        'date',
        'status',
    ];

    protected $casts = [
        'date' => 'date:Y-m-d',
    ];

    public function shelter()
    {
        return $this->belongsTo(Shelter::class);
    }

    public function submissions()
    {
        return $this->hasMany(ShelterDonationSubmission::class, 'donation_request_id');
    }
}