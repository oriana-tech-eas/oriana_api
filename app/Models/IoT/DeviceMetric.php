<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceMetric extends Model
{
    use HasFactory, HasUuids;

    // Use UUID as primary key
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'device_id',
        'metric_type',
        'data',
        'collected_at'
    ];

    protected $casts = [
        'data' => 'array',
        'collected_at' => 'datetime',
    ];

    // Relationships
    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'device_id');
    }

    // Scopes
    public function scopeByType($query, string $type)
    {
        return $query->where('metric_type', $type);
    }

    public function scopeLatest($query)
    {
        return $query->orderBy('collected_at', 'desc');
    }
}
