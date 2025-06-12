<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            
            // Basic customer info
            $table->string('name'); // Company/Organization name
            $table->string('email')->unique(); // Primary contact email
            
            // Keycloak integration
            $table->string('keycloak_user_id')->nullable()->unique();
            
            // IoT subscription settings
            $table->enum('subscription_tier', ['starter', 'professional', 'enterprise'])
                  ->default('starter');
            $table->integer('max_devices')->default(5);
            
            // Account status
            $table->enum('status', ['trial', 'active', 'suspended', 'cancelled'])
                  ->default('trial');
            
            // Business details (optional)
            $table->string('tax_id')->nullable(); // For invoicing
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            
            // Platform metadata
            $table->json('settings')->nullable(); // Platform preferences
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('last_login_at')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index('subscription_tier');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
