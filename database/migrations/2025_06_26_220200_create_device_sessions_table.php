<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_device_id')->constrained()->onDelete('cascade');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->bigInteger('data_usage_bytes')->default(0); // Session-specific usage
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['family_device_id', 'is_active']);
            $table->index(['is_active', 'started_at']);
            $table->index('started_at');
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_sessions');
    }
};
