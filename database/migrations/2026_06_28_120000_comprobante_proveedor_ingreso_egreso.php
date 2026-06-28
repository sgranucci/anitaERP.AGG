<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobante_proveedor', 'caja_movimiento_id')) {
                $table->unsignedBigInteger('caja_movimiento_id')->nullable()->after('asiento_id');
                $table->foreign('caja_movimiento_id', 'fk_comprobante_proveedor_caja_movimiento')
                    ->references('id')->on('caja_movimiento')->onDelete('cascade')->onUpdate('cascade');
            }

            if (! Schema::hasColumn('comprobante_proveedor', 'tipo_tesoreria')) {
                $table->string('tipo_tesoreria', 30)->nullable()->after('origen_entrada');
            }

            if (! Schema::hasColumn('comprobante_proveedor', 'proveedor_nombre_eventual')) {
                $table->string('proveedor_nombre_eventual', 255)->nullable()->after('proveedor_id');
            }

            if (! Schema::hasColumn('comprobante_proveedor', 'proveedor_documento_eventual')) {
                $table->string('proveedor_documento_eventual', 20)->nullable()->after('proveedor_nombre_eventual');
            }

            if (! Schema::hasColumn('comprobante_proveedor', 'proveedor_condicioniva_id_eventual')) {
                $table->unsignedBigInteger('proveedor_condicioniva_id_eventual')->nullable()->after('proveedor_documento_eventual');
                $table->foreign('proveedor_condicioniva_id_eventual', 'fk_cp_condicioniva_eventual')
                    ->references('id')->on('condicioniva')->onDelete('restrict')->onUpdate('restrict');
            }
        });

        if ($this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_proveedor')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropForeign('fk_comprobante_proveedor_proveedor');
            });
        }

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('comprobante_proveedor', 'proveedor_id')) {
                $table->unsignedBigInteger('proveedor_id')->nullable()->change();
            }
        });

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            if (! $this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_proveedor')) {
                $table->foreign('proveedor_id', 'fk_comprobante_proveedor_proveedor')
                    ->references('id')->on('proveedor')->onDelete('restrict')->onUpdate('restrict');
            }
        });

        Schema::table('comprobante_proveedor_concepto', function (Blueprint $table) {
            if (! Schema::hasColumn('comprobante_proveedor_concepto', 'cuentacontabledebe_id')) {
                $table->unsignedBigInteger('cuentacontabledebe_id')->nullable()->after('monto');
                $table->foreign('cuentacontabledebe_id', 'fk_cp_concepto_cuentadebe')
                    ->references('id')->on('cuentacontable')->onDelete('restrict')->onUpdate('restrict');
            }
        });
    }

    private function foreignKeyExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $name, 'FOREIGN KEY']
        );

        return $row !== null;
    }

    private function indexExists(string $table, string $name): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $row = $connection->selectOne(
            'SELECT INDEX_NAME FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND INDEX_NAME = ? LIMIT 1',
            [$database, $table, $name]
        );

        return $row !== null;
    }

    public function down(): void
    {
        Schema::table('comprobante_proveedor_concepto', function (Blueprint $table) {
            if (Schema::hasColumn('comprobante_proveedor_concepto', 'cuentacontabledebe_id')) {
                if ($this->foreignKeyExists('comprobante_proveedor_concepto', 'fk_cp_concepto_cuentadebe')) {
                    $table->dropForeign('fk_cp_concepto_cuentadebe');
                }
                $table->dropColumn('cuentacontabledebe_id');
            }
        });

        if ($this->indexExists('comprobante_proveedor', 'uq_comprobante_proveedor_identificacion_v2')) {
            Schema::table('comprobante_proveedor', function (Blueprint $table) {
                $table->dropUnique('uq_comprobante_proveedor_identificacion_v2');
            });
        }

        Schema::table('comprobante_proveedor', function (Blueprint $table) {
            if (Schema::hasColumn('comprobante_proveedor', 'caja_movimiento_id')) {
                if ($this->foreignKeyExists('comprobante_proveedor', 'fk_comprobante_proveedor_caja_movimiento')) {
                    $table->dropForeign('fk_comprobante_proveedor_caja_movimiento');
                }
                $table->dropColumn('caja_movimiento_id');
            }

            foreach (['tipo_tesoreria', 'proveedor_nombre_eventual', 'proveedor_documento_eventual'] as $col) {
                if (Schema::hasColumn('comprobante_proveedor', $col)) {
                    $table->dropColumn($col);
                }
            }

            if (Schema::hasColumn('comprobante_proveedor', 'proveedor_condicioniva_id_eventual')) {
                if ($this->foreignKeyExists('comprobante_proveedor', 'fk_cp_condicioniva_eventual')) {
                    $table->dropForeign('fk_cp_condicioniva_eventual');
                }
                $table->dropColumn('proveedor_condicioniva_id_eventual');
            }
        });
    }
};
