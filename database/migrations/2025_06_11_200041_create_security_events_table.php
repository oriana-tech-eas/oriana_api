<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('device_id')->constrained()->cascadeOnDelete();
            $table->string('event_id')->unique(); // From the agent
            $table->enum('event_type', ['blocked_request', 'malware_detected', 'intrusion_attempt']);
            $table->enum('severity', ['low', 'medium', 'high', 'critical']);
            $table->ipAddress('source_ip');
            $table->string('domain')->nullable();
            $table->string('category')->nullable(); // social-media, malware, etc.
            $table->string('action'); // blocked, quarantined, etc.
            $table->text('reason');
            $table->json('details')->nullable(); // Additional event data
            $table->timestamp('occurred_at');
            $table->timestamps();
            
            // Indexes for security analysis
            $table->index(['device_id', 'severity', 'occurred_at']);
            $table->index(['event_type', 'occurred_at']);
            $table->index('source_ip');
        });
    }

    public function down()
    {
        Schema::dropIfExists('security_events');
    }
};
