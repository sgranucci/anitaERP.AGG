<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tipotransaccion', function (Blueprint $table) {
            $table->string('operacionstock', 1)->default('O')->after('operacion');
        });

        DB::table('tipotransaccion')
            ->whereIn('operacion', ['V', 'U'])
            ->whereNull('deleted_at')
            ->update(['operacionstock' => 'S']);

        DB::table('tipotransaccion')
            ->where('operacion', 'C')
            ->whereNull('deleted_at')
            ->update(['operacionstock' => 'E']);
    }

    public function down(): void
    {
        Schema::table('tipotransaccion', function (Blueprint $table) {
            $table->dropColumn('operacionstock');
        });
    }
};
