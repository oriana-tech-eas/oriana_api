<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->uuid('family_rule_id')->nullable()->after('customer_id');
            $table->foreign('family_rule_id')->references('id')->on('family_rules')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('devices', function (Blueprint $table) {
            $table->dropForeign(['family_rule_id']);
            $table->dropColumn('family_rule_id');
        });
    }
};
