<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('precarga_comprobante_proveedor', 'identificacion_proveedor_cuit')) {
                $table->char('identificacion_proveedor_cuit', 11)->nullable()->after('proveedor_id');
            }
        });

        $this->backfillCuitNormalizado();

        $conflictos = DB::select("
            SELECT identificacion_proveedor_cuit, empresa_id, tipotransaccion_compra_id, letra, sucursal, numerocomprobante, COUNT(*) AS cnt
            FROM precarga_comprobante_proveedor
            WHERE identificacion_proveedor_cuit IS NOT NULL
              AND identificacion_proveedor_cuit != ''
            GROUP BY identificacion_proveedor_cuit, empresa_id, tipotransaccion_compra_id, letra, sucursal, numerocomprobante
            HAVING COUNT(*) > 1
            LIMIT 5
        ");

        if ($conflictos !== []) {
            throw new RuntimeException(
                'No se puede crear índice único por CUIT en precarga: existen facturas duplicadas con el mismo CUIT.'
            );
        }

        if (! $this->indexExists('precarga_comprobante_proveedor', 'uq_precarga_comprobante_proveedor_por_cuit')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->unique(
                    [
                        'empresa_id',
                        'tipotransaccion_compra_id',
                        'letra',
                        'sucursal',
                        'numerocomprobante',
                        'identificacion_proveedor_cuit',
                    ],
                    'uq_precarga_comprobante_proveedor_por_cuit',
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('precarga_comprobante_proveedor', 'uq_precarga_comprobante_proveedor_por_cuit')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropUnique('uq_precarga_comprobante_proveedor_por_cuit');
            });
        }

        if (Schema::hasColumn('precarga_comprobante_proveedor', 'identificacion_proveedor_cuit')) {
            Schema::table('precarga_comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('identificacion_proveedor_cuit');
            });
        }
    }

    private function backfillCuitNormalizado(): void
    {
        $cuitExpr = "LEFT(REPLACE(REPLACE(REPLACE(REPLACE(p.nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', ''), 11)";
        $cuitLen = "CHAR_LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(p.nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', ''))";

        \App\Support\Database\MigrationDialectSupport::statementPorDriver(
            "UPDATE precarga_comprobante_proveedor pcp
             INNER JOIN proveedor p ON p.id = pcp.proveedor_id
             SET pcp.identificacion_proveedor_cuit = {$cuitExpr}
             WHERE pcp.identificacion_proveedor_cuit IS NULL
               AND {$cuitLen} = 11",
            "UPDATE precarga_comprobante_proveedor AS pcp
             SET identificacion_proveedor_cuit = {$cuitExpr}
             FROM proveedor AS p
             WHERE p.id = pcp.proveedor_id
               AND pcp.identificacion_proveedor_cuit IS NULL
               AND {$cuitLen} = 11"
        );
    }

    private function indexExists(string $table, string $name): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneIndice($table, $name);
    }
};
