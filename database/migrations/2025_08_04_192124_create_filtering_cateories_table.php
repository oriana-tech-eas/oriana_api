<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('filtering_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 50)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->enum('default_severity', ['low', 'medium', 'high', 'critical'])->default('medium');
            $table->string('icon', 50)->default('shield');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('filtering_categories');
    }
};
