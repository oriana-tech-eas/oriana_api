<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('family_rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('customer_id');
            $table->string('name', 100)->default('Default Family Rules');
            $table->boolean('is_active')->default(true);
            
            // Category-based filtering (JSON for flexibility)
            $table->json('blocked_categories')->nullable();
            
            // Time restrictions for entire family
            $table->json('global_time_restrictions')->nullable();
            
            // Adult supervision settings
            $table->boolean('require_adult_approval')->default(false);
            $table->string('adult_override_password')->nullable();
            
            $table->timestamps();

            $table->index(['customer_id', 'is_active']);
            $table->foreign('customer_id')->references('id')->on('customers')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_rules');
    }
};
