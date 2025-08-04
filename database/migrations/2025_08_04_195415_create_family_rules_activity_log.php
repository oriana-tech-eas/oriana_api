<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('family_rules_activity_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_rule_id');
            $table->uuid('family_device_id')->nullable(); // If action was device-specific
            $table->enum('action_type', [
                'rule_created',
                'rule_updated',
                'domain_blocked',
                'domain_allowed',
                'category_blocked',
                'category_unblocked',
                'temporary_override_granted',
                'rule_deleted'
            ]);
            $table->json('action_details')->nullable(); // Store what changed
            $table->string('performed_by', 100)->nullable(); // 'parent', 'admin', 'system'
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['family_rule_id', 'created_at']);
            $table->index(['action_type']);
            $table->foreign('family_rule_id')->references('id')->on('family_rules')->onDelete('cascade');
            $table->foreign('family_device_id')->references('id')->on('family_devices')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_rules_activity_log');
    }
};
