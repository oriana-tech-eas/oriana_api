<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('device_profiles', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->onDelete('cascade');
            $table->string('name'); // "Perfil de Padre", "Perfil de Niños"
            $table->text('description')->nullable();
            $table->boolean('is_default')->default(false);
            $table->json('default_time_limits')->nullable(); // {"weekday_hours": 8, "weekend_hours": 12}
            $table->timestamps();
            
            $table->index(['customer_id', 'is_default']);
        });
    }

    public function down()
    {
        Schema::dropIfExists('device_profiles');
    }
};
