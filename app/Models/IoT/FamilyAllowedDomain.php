<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyAllowedDomain extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = [
        'family_rule_id',
        'domain',
        'reason',
        'added_by'
    ];

    protected $dates = [
        'created_at'
    ];

    // Relationships
    public function familyRule(): BelongsTo
    {
        return $this->belongsTo(FamilyRule::class);
    }

    // Scopes
    public function scopeForFamilyRule($query, $familyRuleId)
    {
        return $query->where('family_rule_id', $familyRuleId);
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
    public function getDisplayDomainAttribute(): string
    {
        return $this->domain;
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

        // Check if the domain is a subdomain of the allowed domain
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
            'reason' => $this->reason,
            'added_by' => $this->added_by,
            'created_at' => $this->created_at
        ];
    }
}