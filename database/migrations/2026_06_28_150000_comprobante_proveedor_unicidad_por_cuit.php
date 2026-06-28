<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobante_proveedor', 'identificacion_proveedor_cuit')) {
                $table->char('identificacion_proveedor_cuit', 11)->nullable()->after('proveedor_documento_eventual');
            }
        });

        $this->backfillCuitNormalizado();

        $conflictos = DB::select("
            SELECT identificacion_proveedor_cuit, empresa_id, tipotransaccion_compra_id, letra, sucursal, numerocomprobante, COUNT(*) AS cnt
            FROM comprobante_proveedor
            WHERE deleted_at IS NULL
              AND identificacion_proveedor_cuit IS NOT NULL
              AND identificacion_proveedor_cuit != ''
            GROUP BY identificacion_proveedor_cuit, empresa_id, tipotransaccion_compra_id, letra, sucursal, numerocomprobante
            HAVING cnt > 1
            LIMIT 5
        ");

        if ($conflictos !== []) {
            throw new RuntimeException(
                'No se puede crear índice único por CUIT: existen comprobantes duplicados con el mismo CUIT e identificación fiscal.'
            );
        }

        if ($this->indexExists('comprobante_proveedor', 'uq_comprobante_proveedor_identificacion')) {
            if ($this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_empresa')) {
                Schema::table('comprobante_proveedor', function (Blueprint $table) {
                    $table->dropForeign('fk_comprobante_proveedor_empresa');
                });
            }

            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropUnique('uq_comprobante_proveedor_identificacion');
            });

            if (! $this->indexExists('comprobante_proveedor', 'idx_comprobante_proveedor_empresa')) {
                Schema::table('comprobante_proveedor', function (Blueprint $table) {
                    $table->index('empresa_id', 'idx_comprobante_proveedor_empresa');
                });
            }

            if (! $this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_empresa')) {
                Schema::table('comprobante_proveedor', function (Blueprint $table) {
                    $table->foreign('empresa_id', 'fk_comprobante_proveedor_empresa')
                        ->references('id')->on('empresa')->onDelete('restrict')->onUpdate('restrict');
                });
            }
        }

        if (! $this->indexExists('comprobante_proveedor', 'uq_comprobante_proveedor_por_cuit')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->unique(
                    [
                        'empresa_id',
                        'tipotransaccion_compra_id',
                        'letra',
                        'sucursal',
                        'numerocomprobante',
                        'identificacion_proveedor_cuit',
                    ],
                    'uq_comprobante_proveedor_por_cuit',
                );
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('comprobante_proveedor', 'uq_comprobante_proveedor_por_cuit')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropUnique('uq_comprobante_proveedor_por_cuit');
            });
        }

        if (! $this->indexExists('comprobante_proveedor', 'uq_comprobante_proveedor_identificacion')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->unique(
                    ['empresa_id', 'proveedor_id', 'tipotransaccion_compra_id', 'letra', 'sucursal', 'numerocomprobante'],
                    'uq_comprobante_proveedor_identificacion',
                );
            });
        }

        if (Schema::hasColumn('comprobante_proveedor', 'identificacion_proveedor_cuit')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropColumn('identificacion_proveedor_cuit');
            });
        }
    }

    private function backfillCuitNormalizado(): void
    {
        DB::statement("
            UPDATE comprobante_proveedor cp
            INNER JOIN proveedor p ON p.id = cp.proveedor_id
            SET cp.identificacion_proveedor_cuit = LEFT(
                REPLACE(REPLACE(REPLACE(REPLACE(p.nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', ''),
                11
            )
            WHERE cp.identificacion_proveedor_cuit IS NULL
              AND cp.proveedor_id IS NOT NULL
              AND CHAR_LENGTH(REPLACE(REPLACE(REPLACE(REPLACE(p.nroinscripcion, '-', ''), ' ', ''), '.', ''), '/', '')) = 11
        ");

        DB::statement("
            UPDATE comprobante_proveedor cp
            SET cp.identificacion_proveedor_cuit = LEFT(
                REPLACE(REPLACE(REPLACE(cp.proveedor_documento_eventual, '-', ''), ' ', ''), '.', ''),
                11
            )
            WHERE cp.identificacion_proveedor_cuit IS NULL
              AND cp.proveedor_documento_eventual IS NOT NULL
              AND CHAR_LENGTH(REPLACE(REPLACE(REPLACE(cp.proveedor_documento_eventual, '-', ''), ' ', ''), '.', '')) = 11
        ");
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $name],
        );

        return $row !== null;
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $name, 'FOREIGN KEY'],
        );

        return $row !== null;
    }
};
