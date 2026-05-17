<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'job_title',
        'email',
        'phone',
        'bio',
        'facebook_url',
        'x_url',
        'linkedin_url',
        'instagram_url',
        'dribbble_url',
        'country',
        'city_state',
        'postal_code',
        'tax_id',
        'profile_photo_path',
        'device_name',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getDisplayNameAttribute(): string
    {
        $fullName = trim(implode(' ', array_filter([
            $this->first_name,
            $this->last_name,
        ])));

        return $fullName !== '' ? $fullName : ($this->name ?: 'User');
    }

    public function getProfilePhotoUrlAttribute(): string
    {
        if (! $this->profile_photo_path) {
            return asset('images/user/owner.jpg');
        }

        if (Str::startsWith($this->profile_photo_path, ['http://', 'https://'])) {
            return $this->profile_photo_path;
        }

        if (Str::startsWith($this->profile_photo_path, ['/'])) {
            return asset(ltrim($this->profile_photo_path, '/'));
        }

        if (Str::startsWith($this->profile_photo_path, ['images/', 'storage/'])) {
            return asset($this->profile_photo_path);
        }

        return Storage::disk('public')->url($this->profile_photo_path);
    }
}
