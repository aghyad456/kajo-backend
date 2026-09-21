<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    // ✅ Roles
    public const ROLE_ADMIN   = 1;
    public const ROLE_USER    = 2;
    public const ROLE_SHOP    = 3;
    public const ROLE_SHELTER = 4;
    public const ROLE_DOCTOR  = 5;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',

        // ✅ Profile fields
        'phone',
        'address',
        'bio',
        'avatar_path',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    // ✅ مهم: حتى يطلع avatar_url ضمن JSON تلقائياً
    protected $appends = ['avatar_url'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ✅ اختياري: لتسهيل التحقق
    public function isAdmin(): bool
    {
        return (int) $this->role === self::ROLE_ADMIN;
    }

    public static function roleLabel(int $role): string
    {
        return match ($role) {
            self::ROLE_ADMIN   => 'admin',
            self::ROLE_USER    => 'user',
            self::ROLE_SHOP    => 'shop_owner',
            self::ROLE_SHELTER => 'shelter_owner',
            self::ROLE_DOCTOR  => 'doctor',
            default => 'unknown',
        };
    }

    // ✅ avatar_url accessor

    public function getAvatarUrlAttribute(): ?string
    {
        if (!$this->avatar_path) return null;

        $v = $this->updated_at?->timestamp ?? time(); // ✅ يكسر الكاش
        return asset('storage/' . $this->avatar_path) . '?v=' . $v;
    }

    public function clinic()
    {
        return $this->hasOne(Clinic::class);
    }

    public function store()
    {
        return $this->hasOne(Store::class);
    }

    public function storeOrders()
    {
        return $this->hasMany(StoreOrder::class);
    }

    public function shelter()
    {
        return $this->hasOne(\App\Models\Shelter::class);
    }

    public function shelterAdoptionRequests()
    {
        return $this->hasMany(\App\Models\ShelterAdoptionRequest::class);
    }
    public function shelterDonationSubmissions()
    {
        return $this->hasMany(\App\Models\ShelterDonationSubmission::class);
    }

    public function savedPosts()
    {
        return $this->hasMany(\App\Models\SavedPost::class);
    }

    public function savedFeedPosts()
    {
        return $this->belongsToMany(\App\Models\FeedPost::class, 'saved_posts', 'user_id', 'post_id')
            ->withTimestamps();
    }
    
}