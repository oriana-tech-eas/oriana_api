<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('family_blocked_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_rule_id');
            $table->string('domain', 255);
            $table->uuid('category_id')->nullable(); // Reference to filtering_categories
            $table->string('category_slug', 50)->nullable(); // Denormalized for performance
            $table->text('reason')->nullable();
            $table->enum('added_by', ['admin', 'parent', 'automatic'])->default('parent');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['family_rule_id', 'domain'], 'unique_family_domain');
            $table->index(['category_slug']);
            $table->index(['domain']);
            $table->foreign('family_rule_id')->references('id')->on('family_rules')->onDelete('cascade');
            $table->foreign('category_id')->references('id')->on('filtering_categories')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_blocked_domains');
    }
};
