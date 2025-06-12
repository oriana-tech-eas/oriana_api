<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_metrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->enum('metric_type', ['network_data', 'system_data', 'bandwidth_usage']);
            $table->json('data'); // The actual metrics payload
            $table->timestamp('collected_at');
            $table->timestamps();
            
            // Indexes for time-series queries
            $table->index(['device_id', 'metric_type', 'collected_at']);
            $table->index('collected_at'); // For cleanup jobs
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_metrics');
    }
};
