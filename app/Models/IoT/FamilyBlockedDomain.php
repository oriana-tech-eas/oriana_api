<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyBlockedDomain extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'family_rule_id',
        'domain',
        'category_id',
        'category_slug',
        'reason',
        'added_by',
        'severity'
    ];

    protected $dates = [
        'created_at'
    ];

    // Relationships
    public function familyRule(): BelongsTo
    {
        return $this->belongsTo(FamilyRule::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(FilteringCategory::class, 'category_id');
    }

    // Scopes
    public function scopeForFamilyRule($query, $familyRuleId)
    {
        return $query->where('family_rule_id', $familyRuleId);
    }

    public function scopeByCategory($query, $categorySlug)
    {
        return $query->where('category_slug', $categorySlug);
    }

    public function scopeBySeverity($query, $severity)
    {
        return $query->where('severity', $severity);
    }

    public function scopeByDomain($query, $domain)
    {
        return $query->where('domain', $domain);
    }

    public function scopeAddedBy($query, $addedBy)
    {
        return $query->where('added_by', $addedBy);
    }

    // Business Logic
    public function getSeverityColorAttribute(): string
    {
        return match($this->severity) {
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red',
            default => 'gray'
        };
    }

    public function getDisplayDomainAttribute(): string
    {
        return $this->domain;
    }

    public function getCategoryNameAttribute(): ?string
    {
        return $this->category?->name;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->severity, ['high', 'critical']);
    }

    public function wasAddedAutomatically(): bool
    {
        return $this->added_by === 'automatic';
    }

    public function wasAddedByParent(): bool
    {
        return $this->added_by === 'parent';
    }

    public function wasAddedByAdmin(): bool
    {
        return $this->added_by === 'admin';
    }

    public function matchesDomain(string $domain): bool
    {
        // Exact match
        if ($this->domain === $domain) {
            return true;
        }

        // Wildcard subdomain matching
        if (str_starts_with($this->domain, '*.')) {
            $baseDomain = substr($this->domain, 2);
            return str_ends_with($domain, $baseDomain);
        }

        // Check if the domain is a subdomain of the blocked domain
        if (str_ends_with($domain, '.' . $this->domain)) {
            return true;
        }

        return false;
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'domain' => $this->domain,
            'category' => [
                'id' => $this->category_id,
                'slug' => $this->category_slug,
                'name' => $this->getCategoryNameAttribute()
            ],
            'reason' => $this->reason,
            'added_by' => $this->added_by,
            'severity' => $this->severity,
            'severity_color' => $this->getSeverityColorAttribute(),
            'created_at' => $this->created_at,
            'is_high_risk' => $this->isHighRisk()
        ];
    }
}