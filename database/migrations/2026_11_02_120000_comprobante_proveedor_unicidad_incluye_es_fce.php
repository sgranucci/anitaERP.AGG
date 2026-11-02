<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La factura común y la FCE del mismo proveedor, tipo, letra, sucursal y número
 * son dos comprobantes. Anita las separa con com_es_fce. El índice fiscal pasa
 * a incluir es_fce para permitirlo.
 */
return new class extends Migration
{
    private const TABLA = 'comprobante_proveedor';

    private const INDICE = 'uq_comprobante_proveedor_por_cuit';

    public function up(): void
    {
        if ($this->indiceYaIncluyeEsFce()) {
            return;
        }

        $conflictos = DB::table(self::TABLA)
            ->select([
                'empresa_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'identificacion_proveedor_cuit',
                'es_fce',
            ])
            ->selectRaw('COUNT(*) as cnt')
            ->whereNotNull('identificacion_proveedor_cuit')
            ->where('identificacion_proveedor_cuit', '!=', '')
            ->groupBy(
                'empresa_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'identificacion_proveedor_cuit',
                'es_fce',
            )
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($conflictos->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede incluir es_fce en el índice único: ya hay comprobantes repetidos con la misma marca FCE.'
            );
        }

        if (! MigrationDialectSupport::tieneIndice(self::TABLA, 'idx_comprobante_proveedor_empresa')) {
            Schema::table(self::TABLA, function (Blueprint $table) {
                $table->index('empresa_id', 'idx_comprobante_proveedor_empresa');
            });
        }

        MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::INDICE);

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->unique([
                'empresa_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'identificacion_proveedor_cuit',
                'es_fce',
            ], self::INDICE);
        });
    }

    public function down(): void
    {
        if (! MigrationDialectSupport::tieneIndice(self::TABLA, self::INDICE)) {
            return;
        }

        $pares = DB::table(self::TABLA)
            ->select([
                'empresa_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'identificacion_proveedor_cuit',
            ])
            ->selectRaw('COUNT(*) as cnt')
            ->whereNotNull('identificacion_proveedor_cuit')
            ->where('identificacion_proveedor_cuit', '!=', '')
            ->groupBy(
                'empresa_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'identificacion_proveedor_cuit',
            )
            ->havingRaw('COUNT(*) > 1')
            ->limit(1)
            ->get();

        if ($pares->isNotEmpty()) {
            throw new RuntimeException(
                'No se puede volver al índice sin es_fce: hay una factura común y una FCE con el mismo número.'
            );
        }

        MigrationDialectSupport::dropIndiceOUnique(self::TABLA, self::INDICE);

        Schema::table(self::TABLA, function (Blueprint $table) {
            $table->unique([
                'empresa_id',
                'tipotransaccion_compra_id',
                'letra',
                'sucursal',
                'numerocomprobante',
                'identificacion_proveedor_cuit',
            ], self::INDICE);
        });
    }

    private function indiceYaIncluyeEsFce(): bool
    {
        if (! MigrationDialectSupport::tieneIndice(self::TABLA, self::INDICE)) {
            return false;
        }

        $columnas = Schema::getIndexes(self::TABLA);
        foreach ($columnas as $indice) {
            if (($indice['name'] ?? '') !== self::INDICE) {
                continue;
            }

            return in_array('es_fce', $indice['columns'] ?? [], true);
        }

        return false;
    }
};
