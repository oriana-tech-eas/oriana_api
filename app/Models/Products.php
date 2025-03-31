<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Products extends Model
{
    use HasFactory;

    protected $table = 'products';

    protected $fillable = [
        'image',
        'name',
        'description',
        'price',
        'barcode',
        'cost',
        'stock',
        'is_composite',
        'inventory',
        'inventory_enabled',
        'category_id',
        'company_id',
        'user_id',
        'updated_at',
    ];

    protected $appends = [
        'category_name',
        'tax_name',
    ];

    public function getCategoryNameAttribute()
    {
        return $this->category->name;
    }

    public function getTaxNameAttribute()
    {
        return $this->taxes->pluck('name');
    }

    public function company()
    {
        return $this->belongsTo(Companies::class);
    }

    public function taxes()
    {
        return $this->belongsToMany(Taxes::class, 'product_tax');
    }

    public function category()
    {
        return $this->belongsTo(Categories::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
