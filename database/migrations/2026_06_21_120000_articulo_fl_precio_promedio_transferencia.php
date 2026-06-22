<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const SKUS_LEGACY_TITO = [
        '0000000201421',
        '000100000-006',
        '0000000201266',
        '0000000201265',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('articulo', 'fl_precio_promedio_transferencia')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->boolean('fl_precio_promedio_transferencia')
                    ->default(false)
                    ->after('ppp')
                    ->comment('Asiento transferencia: promedio 3 últ. recepciones (si no: última compra stkmae)');
            });
        }

        foreach (self::SKUS_LEGACY_TITO as $sku) {
            DB::table('articulo')
                ->where('sku', $sku)
                ->update(['fl_precio_promedio_transferencia' => true]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('articulo', 'fl_precio_promedio_transferencia')) {
            Schema::table('articulo', function (Blueprint $table) {
                $table->dropColumn('fl_precio_promedio_transferencia');
            });
        }
    }
};
