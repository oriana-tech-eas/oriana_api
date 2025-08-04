<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('family_allowed_domains', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('family_rule_id');
            $table->string('domain', 255);
            $table->text('reason')->nullable();
            $table->enum('added_by', ['admin', 'parent'])->default('parent');
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['family_rule_id', 'domain'], 'unique_family_allowed');
            $table->index(['domain']);
            $table->foreign('family_rule_id')->references('id')->on('family_rules')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_allowed_domains');
    }
};
