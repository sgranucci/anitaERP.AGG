<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Una precarga genera a lo sumo un comprobante. Sin este unique, un doble clic en
 * «Generar desde precarga» podía crear dos borradores (el chequeo en PHP era
 * read-then-write sin lock).
 */
return new class extends Migration
{
    private const TABLA = 'comprobante_proveedor';

    private const INDICE = 'uq_comprobante_proveedor_precarga';

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLA) || ! Schema::hasColumn(self::TABLA, 'precarga_comprobante_proveedor_id')) {
            return;
        }

        if (MigrationDialectSupport::tieneIndice(self::TABLA, self::INDICE)) {
            return;
        }

        $colisiones = DB::table(self::TABLA)
            ->select('precarga_comprobante_proveedor_id')
            ->whereNotNull('precarga_comprobante_proveedor_id')
            ->groupBy('precarga_comprobante_proveedor_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('precarga_comprobante_proveedor_id')
            ->all();

        if ($colisiones !== []) {
            throw new \RuntimeException(
                'No se puede crear '.self::INDICE.': hay precargas con más de un comprobante ('
                .implode(', ', array_slice($colisiones, 0, 10)).'). Resolvélas antes de migrar.'
            );
        }

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->unique('precarga_comprobante_proveedor_id', self::INDICE);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable(self::TABLA)) {
            return;
        }

        MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::INDICE);
    }
};
