<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->string('device_id')->unique(); // Hardware identifier (like pi-001)
            $table->enum('device_type', ['network', 'server', 'hybrid']);
            $table->string('name'); // User-friendly name like "Oficina Principal"
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->string('api_key')->unique();
            $table->timestamp('last_seen')->nullable();
            $table->json('metadata')->nullable(); // Hardware specs, location, etc.
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['customer_id', 'status']);
            $table->index('last_seen');
            $table->index('device_type');
        });
    }

    public function down()
    {
        Schema::dropIfExists('devices');
    }
};
