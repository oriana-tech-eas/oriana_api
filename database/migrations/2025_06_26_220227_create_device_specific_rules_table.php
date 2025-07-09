<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_specific_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_device_id')->constrained()->onDelete('cascade');
            $table->string('rule_type'); // 'domain_blacklist', 'domain_whitelist', 'time_restriction'
            $table->string('target_value', 500); // domain, category, or pattern
            $table->boolean('is_enabled')->default(true);
            $table->json('schedule')->nullable(); // {"days": ["monday"], "hours": {"start": "09:00", "end": "17:00"}}
            $table->json('custom_settings')->nullable(); // Additional rule-specific settings
            $table->timestamps();
            
            // Indexes
            $table->index(['family_device_id', 'is_enabled']);
            $table->index(['family_device_id', 'rule_type']);
            $table->unique(['family_device_id', 'rule_type', 'target_value'], 'unique_device_rule');
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_specific_rules');
    }
};
