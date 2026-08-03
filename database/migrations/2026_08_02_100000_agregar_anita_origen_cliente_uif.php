<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            if (! Schema::hasColumn('cliente_uif', 'anita_origen')) {
                $table->string('anita_origen', 20)->nullable()->after('inroclienteid');
                $table->index(['anita_origen', 'inroclienteid'], 'cliente_uif_anita_origen_inro_idx');
            }
        });

        DB::table('cliente_uif')
            ->whereNotNull('inroclienteid')
            ->where('inroclienteid', '>', 0)
            ->whereNull('anita_origen')
            ->update(['anita_origen' => 'biyemas']);
    }

    public function down(): void
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            if (Schema::hasColumn('cliente_uif', 'anita_origen')) {
                $table->dropIndex('cliente_uif_anita_origen_inro_idx');
                $table->dropColumn('anita_origen');
            }
        });
    }
};
