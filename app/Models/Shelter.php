<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Shelter extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'specialty',
        'location',
        'phone',
        'avatar_path',
        'rating',
        'is_active',
    ];

    protected $appends = ['avatar_url'];

    public function getAvatarUrlAttribute()
    {
        if (!$this->avatar_path) {
            return null;
        }

        if (Str::startsWith($this->avatar_path, ['http://', 'https://'])) {
            return $this->avatar_path;
        }

        return asset('storage/' . $this->avatar_path);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function animals()
    {
        return $this->hasMany(ShelterAnimal::class);
    }

    public function donationRequests()
    {
        return $this->hasMany(ShelterDonationRequest::class);
    }

    public function adoptionRequests()
    {
        return $this->hasMany(ShelterAdoptionRequest::class);
    }
    
    public function donationSubmissions()
    {
        return $this->hasMany(ShelterDonationSubmission::class);
    }
}