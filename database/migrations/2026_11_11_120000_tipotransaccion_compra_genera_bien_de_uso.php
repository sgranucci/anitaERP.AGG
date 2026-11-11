<?php

use App\Support\Compras\TipotransaccionCompraGeneraAsiAnitaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Anita `tcomp_genera_asi` = U significa asiento + bien de uso.
 * El import aplanaba U a asientocontable=N. Separa el flag y remapea *U.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tipotransaccion_compra')) {
            return;
        }

        if (! Schema::hasColumn('tipotransaccion_compra', 'genera_bien_de_uso')) {
            Schema::table('tipotransaccion_compra', function (Blueprint $table) {
                $table->string('genera_bien_de_uso', 1)
                    ->default(TipotransaccionCompraGeneraAsiAnitaSupport::ERP_NO)
                    ->after('asientocontable');
            });
        }

        // Legado: si quedó U en asientocontable, partirlo.
        DB::table('tipotransaccion_compra')
            ->whereRaw('UPPER(TRIM(asientocontable)) = ?', [TipotransaccionCompraGeneraAsiAnitaSupport::ANITA_BIEN_USO])
            ->update([
                'asientocontable' => TipotransaccionCompraGeneraAsiAnitaSupport::ERP_SI,
                'genera_bien_de_uso' => TipotransaccionCompraGeneraAsiAnitaSupport::ERP_SI,
            ]);

        // Familia bienes de uso: abreviatura termina en U (FIU, FDU, FNU, …).
        DB::table('tipotransaccion_compra')
            ->whereRaw('UPPER(TRIM(abreviatura)) LIKE ?', ['%U'])
            ->update([
                'asientocontable' => TipotransaccionCompraGeneraAsiAnitaSupport::ERP_SI,
                'genera_bien_de_uso' => TipotransaccionCompraGeneraAsiAnitaSupport::ERP_SI,
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('tipotransaccion_compra')) {
            return;
        }

        if (Schema::hasColumn('tipotransaccion_compra', 'genera_bien_de_uso')) {
            // Antes del split, Anita guardaba U en un solo campo.
            DB::table('tipotransaccion_compra')
                ->where('genera_bien_de_uso', TipotransaccionCompraGeneraAsiAnitaSupport::ERP_SI)
                ->update(['asientocontable' => TipotransaccionCompraGeneraAsiAnitaSupport::ANITA_BIEN_USO]);

            Schema::table('tipotransaccion_compra', function (Blueprint $table) {
                $table->dropColumn('genera_bien_de_uso');
            });
        }
    }
};
