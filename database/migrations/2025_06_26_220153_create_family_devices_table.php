<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up()
    {
        Schema::create('family_devices', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('customer_id')->constrained()->onDelete('cascade');
            $table->string('mac_address', 17);
            $table->string('name');
            $table->enum('avatar', [
                'bear','crocodile','duck','elephant','flamingo','horse','koala','lion','moose','penguin','rabbit','raccoon','rhino','shark','tiger','toucan','wildboar','zebra',
            ])->default('penguin');
            $table->string('device_model')->nullable();
            $table->enum('device_type', [
                'mobile', 'laptop', 'desktop', 'tablet', 'smart_tv',
                'iot_device', 'gaming_console', 'unknown'
            ])->default('unknown');
            $table->string('manufacturer', 100)->nullable();
            $table->string('current_ip', 15)->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_identified')->default(false); // Has user named it?
            $table->foreignUuid('profile_id')->nullable()->constrained('device_profiles')->onDelete('set null');
            $table->timestamp('first_seen')->useCurrent();
            $table->timestamp('last_seen')->nullable();
            $table->timestamp('connection_started_at')->nullable(); // Current session start
            $table->bigInteger('total_data_usage_bytes')->default(0); // Cumulative usage
            $table->timestamps();
            
            // Indexes for performance
            $table->unique(['customer_id', 'mac_address'], 'unique_customer_mac');
            $table->index(['customer_id', 'is_active']);
            $table->index(['customer_id', 'is_identified']);
            $table->index('mac_address');
            $table->index('current_ip');
        });
    }

    public function down()
    {
        Schema::dropIfExists('family_devices');
    }
};
