<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ShelterAnimal extends Model
{
    use HasFactory;

    protected $fillable = [
        'shelter_id',
        'name',
        'type',
        'age',
        'health_status',
        'gender',
        'vaccines',
        'note',
        'image_path',
        'is_available',
    ];

    protected $appends = ['image_url'];

    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }

        if (Str::startsWith($this->image_path, ['http://', 'https://'])) {
            return $this->image_path;
        }

        return asset('storage/' . $this->image_path);
    }

    public function shelter()
    {
        return $this->belongsTo(Shelter::class);
    }

    public function adoptionRequests()
    {
        return $this->hasMany(ShelterAdoptionRequest::class, 'animal_id');
    }
}