<?php

namespace App\Models\IoT;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FamilyRule extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'name',
        'is_active',
        'blocked_categories',
        'global_time_restrictions',
        'require_adult_approval',
        'adult_override_password'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'blocked_categories' => 'array',
        'global_time_restrictions' => 'array',
        'require_adult_approval' => 'boolean'
    ];

    protected $hidden = [
        'adult_override_password'
    ];

    // Relationships
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function blockedDomains(): HasMany
    {
        return $this->hasMany(FamilyBlockedDomain::class);
    }

    public function allowedDomains(): HasMany
    {
        return $this->hasMany(FamilyAllowedDomain::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(FamilyRuleActivityLog::class);
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function deviceSpecificRules(): HasMany
    {
        return $this->hasMany(DeviceSpecificRule::class);
    }

    public function deviceOverrides(): HasMany
    {
        return $this->hasMany(DeviceRuleOverride::class);
    }

    // Scopes
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Business Logic
    public function getBlockedCategorySlugsAttribute(): array
    {
        return $this->blocked_categories ?? [];
    }

    public function getBlockedDomainsCountAttribute(): int
    {
        return $this->blockedDomains()->count();
    }

    public function getAllowedDomainsCountAttribute(): int
    {
        return $this->allowedDomains()->count();
    }

    public function isCategoryBlocked(string $categorySlug): bool
    {
        return in_array($categorySlug, $this->getBlockedCategorySlugsAttribute());
    }

    public function isDomainBlocked(string $domain): bool
    {
        return $this->blockedDomains()
            ->where('domain', $domain)
            ->exists();
    }

    public function isDomainAllowed(string $domain): bool
    {
        return $this->allowedDomains()
            ->where('domain', $domain)
            ->exists();
    }

    public function addBlockedCategory(string $categorySlug): bool
    {
        $categories = $this->getBlockedCategorySlugsAttribute();
        
        if (!in_array($categorySlug, $categories)) {
            $categories[] = $categorySlug;
            return $this->update(['blocked_categories' => $categories]);
        }
        
        return false;
    }

    public function removeBlockedCategory(string $categorySlug): bool
    {
        $categories = $this->getBlockedCategorySlugsAttribute();
        $key = array_search($categorySlug, $categories);
        
        if ($key !== false) {
            unset($categories[$key]);
            return $this->update(['blocked_categories' => array_values($categories)]);
        }
        
        return false;
    }

    public function verifyAdultPassword(string $password): bool
    {
        return $this->adult_override_password && 
               hash_equals($this->adult_override_password, hash('sha256', $password));
    }

    public function setAdultPassword(string $password): bool
    {
        return $this->update([
            'adult_override_password' => hash('sha256', $password)
        ]);
    }

    public function hasTimeRestrictions(): bool
    {
        return !empty($this->global_time_restrictions);
    }

    public function isCurrentlyRestricted(): bool
    {
        if (!$this->hasTimeRestrictions()) {
            return false;
        }

        $currentTime = now();
        $currentDay = strtolower($currentTime->format('l'));
        $currentHour = $currentTime->format('H:i');

        $restrictions = $this->global_time_restrictions;
        
        if (isset($restrictions[$currentDay])) {
            $dayRestrictions = $restrictions[$currentDay];
            
            if (isset($dayRestrictions['blocked_hours'])) {
                foreach ($dayRestrictions['blocked_hours'] as $timeRange) {
                    $start = $timeRange['start'] ?? '00:00';
                    $end = $timeRange['end'] ?? '23:59';
                    
                    if ($currentHour >= $start && $currentHour <= $end) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}