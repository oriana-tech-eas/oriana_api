<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_registrations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->foreign('customer_id')->references('id')->on('customers')->cascadeOnDelete();
            $table->string('device_id'); // The device requesting registration
            $table->enum('device_type', ['network', 'server', 'hybrid']);
            $table->string('name'); // User-friendly name
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('requested_by')->constrained('users');
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->text('notes')->nullable();
            $table->timestamps();
            
            // Prevent duplicate registrations
            $table->unique(['customer_id', 'device_id']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_registrations');
    }
};
