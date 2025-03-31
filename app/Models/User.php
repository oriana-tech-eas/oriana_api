<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'username',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = [
        'initials',
    ];

    public function roles()
    {
        return $this->hasManyThrough(Role::class, RoleUser::class, 'user_id', 'id', 'id', 'role_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function hasRole($role)
    {
        return $this->roles()->count() ? $this->roles()->where('name', $role)->exists() : false;
    }

    public function hasAnyRole($roles)
    {
        return $this->roles()->count() ? $this->roles()->whereIn('name', $roles)->exists() : false;
    }

    public function permissions()
    {
        return $this->roles->map->permissions->flatten()->pluck('name')->unique();
    }

    public function hasPermission($permission)
    {
        return $this->permissions->contains($permission);
    }

    public function getInitialsAttribute()
    {
        $name = explode(' ', $this->name);
        $initials = '';
        foreach ($name as $n) {
            $initials .= $n[0];
        }

        return $initials;
    }

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
}
