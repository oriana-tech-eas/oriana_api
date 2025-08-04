<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_rule_overrides', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_device_id');
            $table->uuid('family_rule_id');
            
            // What type of override this is
            $table->enum('override_type', [
                'allow_domain',
                'block_domain',
                'extend_time',
                'restrict_time',
                'disable_category',
                'enable_category'
            ]);
            
            // The specific value being overridden
            $table->string('override_value', 255);
            
            // Optional metadata
            $table->text('reason')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('created_by', ['parent', 'admin'])->default('parent');
            
            $table->timestamps();

            $table->index(['family_device_id']);
            $table->index(['override_type']);
            $table->index(['expires_at']);
            $table->foreign('family_device_id')->references('id')->on('family_devices')->onDelete('cascade');
            $table->foreign('family_rule_id')->references('id')->on('family_rules')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_rule_overrides');
    }
};
