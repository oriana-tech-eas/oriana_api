<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeviceTrafficLog extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'family_device_id',
        'recorded_at',
        'bytes_downloaded',
        'bytes_uploaded',
        'session_id'
    ];

    protected $casts = [
        'recorded_at' => 'datetime',
        'bytes_downloaded' => 'integer',
        'bytes_uploaded' => 'integer'
    ];

    // Relationships
    public function familyDevice(): BelongsTo
    {
        return $this->belongsTo(FamilyDevice::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(DeviceSession::class, 'session_id');
    }

    // Scopes
    public function scopeToday($query)
    {
        return $query->whereDate('recorded_at', today());
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('recorded_at', [
            now()->startOfWeek(),
            now()->endOfWeek()
        ]);
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('recorded_at', now()->month)
                    ->whereYear('recorded_at', now()->year);
    }

    // Accessors
    public function getTotalBytesAttribute(): int
    {
        return $this->bytes_downloaded + $this->bytes_uploaded;
    }

    public function getFormattedTotalAttribute(): string
    {
        return $this->formatBytes($this->total_bytes);
    }

    // Helper Methods
    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 1) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 1) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 1) . ' KB';
        }
        return $bytes . ' bytes';
    }
}
