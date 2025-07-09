<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_traffic_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('family_device_id')->constrained()->onDelete('cascade');
            $table->timestamp('recorded_at')->useCurrent();
            $table->bigInteger('bytes_downloaded')->default(0);
            $table->bigInteger('bytes_uploaded')->default(0);
            $table->foreignUuid('session_id')->nullable()->constrained('device_sessions')->onDelete('set null');
            $table->timestamps();
            
            // Indexes for performance (traffic data queries are time-heavy)
            $table->index(['family_device_id', 'recorded_at']);
            $table->index('recorded_at');
            $table->index('session_id');
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_traffic_logs');
    }
};
