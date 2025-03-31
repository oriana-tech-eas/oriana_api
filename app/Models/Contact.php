<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contact extends Model
{
    use HasFactory;

    protected $table = 'contacts';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'address',
        'document',
        'contacts_type',
        'user_id',
        'company_id',
        'updated_at',
    ];

    protected $appends = ['initials'];

    public function getInitialsAttribute()
    {
        $name = explode(' ', $this->name);

        return strtoupper($name[0][0].$name[1][0]);
    }

    public function users()
    {
        return $this->belongsTo(User::class, 'id', 'user_id');
    }

    public function companies()
    {
        return $this->belongsTo(Companies::class, 'id', 'company_id');
    }
}
