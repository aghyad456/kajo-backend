<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShelterDonationSubmission extends Model
{
    use HasFactory;

    protected $fillable = [
        'shelter_id',
        'donation_request_id',
        'user_id',
        'donor_name',
        'phone',
        'donation_type',
        'amount_or_item',
        'note',
        'status',
    ];

    public function shelter()
    {
        return $this->belongsTo(Shelter::class);
    }

    public function donationRequest()
    {
        return $this->belongsTo(ShelterDonationRequest::class, 'donation_request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}