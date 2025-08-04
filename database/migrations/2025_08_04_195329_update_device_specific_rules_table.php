<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('device_specific_rules', function (Blueprint $table) {
            $table->uuid('family_rule_id')->nullable()->after('family_device_id');
            $table->boolean('override_family_rules')->default(false)->after('family_rule_id');
            
            $table->foreign('family_rule_id')->references('id')->on('family_rules')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('device_specific_rules', function (Blueprint $table) {
            $table->dropForeign(['family_rule_id']);
            $table->dropColumn(['family_rule_id', 'override_family_rules']);
        });
    }
};
