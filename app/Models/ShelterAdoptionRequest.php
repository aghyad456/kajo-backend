<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShelterAdoptionRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'shelter_id',
        'animal_id',
        'user_id',
        'phone',
        'has_adopted_before',
        'delivery_date',
        'note',
        'status',
    ];

    protected $casts = [
        'has_adopted_before' => 'boolean',
        'delivery_date' => 'date:Y-m-d',
    ];

    public function shelter()
    {
        return $this->belongsTo(Shelter::class);
    }

    public function animal()
    {
        return $this->belongsTo(ShelterAnimal::class, 'animal_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}