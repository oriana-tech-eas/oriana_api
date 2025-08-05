<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FilteringCategory extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug',
        'name',
        'description',
        'default_severity',
        'icon',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean'
    ];

    // Relationships
    public function blockedDomains(): HasMany
    {
        return $this->hasMany(FamilyBlockedDomain::class, 'category_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeBySlug($query, string $slug)
    {
        return $query->where('slug', $slug);
    }

    public function scopeBySeverity($query, string $severity)
    {
        return $query->where('default_severity', $severity);
    }

    // Business Logic
    public function getDisplayNameAttribute(): string
    {
        return $this->name;
    }

    public function getSeverityColorAttribute(): string
    {
        return match($this->default_severity) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function getBlockedDomainsCountAttribute(): int
    {
        return $this->blockedDomains()->count();
    }

    public function isHighRisk(): bool
    {
        return in_array($this->default_severity, ['high', 'critical']);
    }
}