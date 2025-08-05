<?php

namespace App\Models\IoT;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FamilyRuleActivityLog extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'family_rules_activity_log';
    
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'family_rule_id',
        'family_device_id',
        'action_type',
        'action_details',
        'performed_by',
        'ip_address'
    ];

    protected $casts = [
        'action_details' => 'array'
    ];

    protected $dates = [
        'created_at'
    ];

    // Relationships
    public function familyRule(): BelongsTo
    {
        return $this->belongsTo(FamilyRule::class);
    }

    public function familyDevice(): BelongsTo
    {
        return $this->belongsTo(FamilyDevice::class);
    }

    // Scopes
    public function scopeForFamilyRule($query, $familyRuleId)
    {
        return $query->where('family_rule_id', $familyRuleId);
    }

    public function scopeForDevice($query, $deviceId)
    {
        return $query->where('family_device_id', $deviceId);
    }

    public function scopeByActionType($query, $actionType)
    {
        return $query->where('action_type', $actionType);
    }

    public function scopeByPerformedBy($query, $performedBy)
    {
        return $query->where('performed_by', $performedBy);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('created_at', '>=', now()->subDays($days));
    }

    // Business Logic
    public function getActionDescriptionAttribute(): string
    {
        return match($this->action_type) {
            'rule_created' => 'Family rule was created',
            'rule_updated' => 'Family rule was updated',
            'domain_blocked' => 'Domain was blocked',
            'domain_allowed' => 'Domain was allowed',
            'category_blocked' => 'Category was blocked',
            'category_unblocked' => 'Category was unblocked',
            'temporary_override_granted' => 'Temporary override was granted',
            'rule_deleted' => 'Family rule was deleted',
            default => 'Unknown action'
        };
    }

    public function getActionIconAttribute(): string
    {
        return match($this->action_type) {
            'rule_created' => 'plus-circle',
            'rule_updated' => 'edit',
            'domain_blocked' => 'shield',
            'domain_allowed' => 'check-circle',
            'category_blocked' => 'shield',
            'category_unblocked' => 'unlock',
            'temporary_override_granted' => 'clock',
            'rule_deleted' => 'trash',
            default => 'help-circle'
        };
    }

    public function getActionColorAttribute(): string
    {
        return match($this->action_type) {
            'rule_created' => 'green',
            'rule_updated' => 'blue',
            'domain_blocked' => 'red',
            'domain_allowed' => 'green',
            'category_blocked' => 'orange',
            'category_unblocked' => 'green',
            'temporary_override_granted' => 'yellow',
            'rule_deleted' => 'red',
            default => 'gray'
        };
    }

    public function isSystemAction(): bool
    {
        return $this->performed_by === 'system';
    }

    public function isParentAction(): bool
    {
        return $this->performed_by === 'parent';
    }

    public function isAdminAction(): bool
    {
        return $this->performed_by === 'admin';
    }

    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'action_type' => $this->action_type,
            'action_description' => $this->getActionDescriptionAttribute(),
            'action_icon' => $this->getActionIconAttribute(),
            'action_color' => $this->getActionColorAttribute(),
            'action_details' => $this->action_details,
            'performed_by' => $this->performed_by,
            'device' => $this->familyDevice ? [
                'id' => $this->familyDevice->id,
                'name' => $this->familyDevice->name
            ] : null,
            'ip_address' => $this->ip_address,
            'created_at' => $this->created_at,
            'created_at_human' => $this->created_at?->diffForHumans()
        ];
    }

    // Static methods for creating activity logs
    public static function logRuleCreated(string $familyRuleId, string $performedBy, ?string $ipAddress = null): self
    {
        return static::create([
            'family_rule_id' => $familyRuleId,
            'action_type' => 'rule_created',
            'performed_by' => $performedBy,
            'ip_address' => $ipAddress
        ]);
    }

    public static function logDomainBlocked(string $familyRuleId, string $domain, string $performedBy, ?string $ipAddress = null): self
    {
        return static::create([
            'family_rule_id' => $familyRuleId,
            'action_type' => 'domain_blocked',
            'action_details' => ['domain' => $domain],
            'performed_by' => $performedBy,
            'ip_address' => $ipAddress
        ]);
    }

    public static function logDomainAllowed(string $familyRuleId, string $domain, string $performedBy, ?string $ipAddress = null): self
    {
        return static::create([
            'family_rule_id' => $familyRuleId,
            'action_type' => 'domain_allowed',
            'action_details' => ['domain' => $domain],
            'performed_by' => $performedBy,
            'ip_address' => $ipAddress
        ]);
    }

    public static function logCategoryBlocked(string $familyRuleId, string $categorySlug, string $performedBy, ?string $ipAddress = null): self
    {
        return static::create([
            'family_rule_id' => $familyRuleId,
            'action_type' => 'category_blocked',
            'action_details' => ['category_slug' => $categorySlug],
            'performed_by' => $performedBy,
            'ip_address' => $ipAddress
        ]);
    }
}