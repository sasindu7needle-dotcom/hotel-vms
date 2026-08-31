<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('visitor_categories', function (Blueprint $table) {
            $table->string('card_image_path')->nullable()->after('badge_color');
            $table->string('card_image_name')->nullable()->after('card_image_path');
            $table->string('card_image_mime', 100)->nullable()->after('card_image_name');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_categories', function (Blueprint $table) {
            $table->dropColumn(['card_image_path', 'card_image_name', 'card_image_mime']);
        });
    }
};
