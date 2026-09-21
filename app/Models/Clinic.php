<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Clinic extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',          // ✅ الصحيح
        'specialty',
        'location',
        'phone',
        'avatar_path',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'avatar_url',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function appointments()
    {
        return $this->hasMany(Appointment::class);
    }

    public function slots()
    {
        return $this->hasMany(\App\Models\ClinicSlot::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getAvatarUrlAttribute()
    {
        return $this->avatar_path
            ? asset('storage/' . $this->avatar_path)
            : null;
    }

    /*
    |--------------------------------------------------------------------------
    | Backward compatibility (اختياري)
    | إذا عندك كود قديم يستخدم clinic_name، خلي هالـ accessor
    |--------------------------------------------------------------------------
    */

    public function getClinicNameAttribute()
    {
        return $this->attributes['name'] ?? null;
    }

    public function setClinicNameAttribute($value)
    {
        $this->attributes['name'] = $value;
    }
}