<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    use HasFactory;

    protected $table = 'companies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id',
        'name',
        'address',
        'phone',
        'owner_id',
    ];

    /**
     * Obtener el propietario de la empresa
     */
    public function owner(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_user', 'company_id', 'user_id');
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function contacts(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Contact::class);
    }
}
