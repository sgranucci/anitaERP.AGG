<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('flash_caja', function (Blueprint $table) {
            $table->dropColumn([
                'poker_coin_in',
                'poker_d',
                'poker_r',
                'poker_soft_count',
                'poker_hard_count',
                'cant_poker',
                'win_ol_poker',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('flash_caja', function (Blueprint $table) {
            $table->decimal('poker_coin_in', 16, 2)->default(0)->after('pos_online');
            $table->decimal('poker_d', 16, 2)->default(0)->after('poker_coin_in');
            $table->decimal('poker_r', 16, 2)->default(0)->after('poker_d');
            $table->decimal('poker_soft_count', 16, 2)->default(0)->after('poker_r');
            $table->decimal('poker_hard_count', 16, 2)->default(0)->after('poker_soft_count');
            $table->unsignedInteger('cant_poker')->default(0)->after('poker_hard_count');
            $table->decimal('win_ol_poker', 16, 2)->default(0)->after('win_ol_rul');
        });
    }
};
