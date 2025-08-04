<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up()
    {
        $categories = [
            [
                'id' => Str::uuid(),
                'slug' => 'adult',
                'name' => 'Adult Content',
                'description' => 'Sexually explicit and adult-oriented content',
                'default_severity' => 'high',
                'icon' => 'eye-slash',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'gambling',
                'name' => 'Gambling & Gaming',
                'description' => 'Online gambling, betting, and casino sites',
                'default_severity' => 'high',
                'icon' => 'dice',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'social_media',
                'name' => 'Social Media',
                'description' => 'Facebook, Instagram, TikTok, Twitter platforms',
                'default_severity' => 'medium',
                'icon' => 'users',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'streaming',
                'name' => 'Video Streaming',
                'description' => 'YouTube, Netflix, streaming video content',
                'default_severity' => 'low',
                'icon' => 'play',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'gaming',
                'name' => 'Online Gaming',
                'description' => 'Online games and gaming platforms',
                'default_severity' => 'medium',
                'icon' => 'gamepad',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'news',
                'name' => 'News & Politics',
                'description' => 'News websites and political content',
                'default_severity' => 'low',
                'icon' => 'newspaper',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'shopping',
                'name' => 'Shopping & E-commerce',
                'description' => 'Online stores and shopping sites',
                'default_severity' => 'low',
                'icon' => 'shopping-cart',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'education',
                'name' => 'Educational',
                'description' => 'Educational resources and learning platforms',
                'default_severity' => 'low',
                'icon' => 'book',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => Str::uuid(),
                'slug' => 'malware',
                'name' => 'Malware & Threats',
                'description' => 'Known malicious and dangerous websites',
                'default_severity' => 'critical',
                'icon' => 'shield-exclamation',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        DB::table('filtering_categories')->insert($categories);
    }

    public function down()
    {
        DB::table('filtering_categories')->truncate();
    }
};
