<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cotización tesorería por empresa (Biyemas / Kandiko / Rebisco).
 * Precarga empresa_id = 1 en filas ya importadas desde bridge Biyemas.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cotizacion_tesoreria')) {
            return;
        }

        if (! Schema::hasColumn('cotizacion_tesoreria', 'empresa_id')) {
            Schema::table('cotizacion_tesoreria', function (Blueprint $table) {
                $table->unsignedInteger('empresa_id')->nullable()->after('id');
            });
        }

        DB::table('cotizacion_tesoreria')->whereNull('empresa_id')->update(['empresa_id' => 1]);

        DB::statement('ALTER TABLE cotizacion_tesoreria MODIFY empresa_id INT UNSIGNED NOT NULL DEFAULT 1');

        $this->dropIndexIfExists('cotizacion_tesoreria', 'uq_cotizacion_tesoreria_fecha');
        $this->dropIndexIfExists('cotizacion_tesoreria', 'uq_cotizacion_tesoreria_fecha_anita');
        $this->dropIndexIfExists('cotizacion_tesoreria', 'cotizacion_tesoreria_fecha_unique');
        $this->dropIndexIfExists('cotizacion_tesoreria', 'cotizacion_tesoreria_fecha_anita_unique');

        Schema::table('cotizacion_tesoreria', function (Blueprint $table) {
            if (! $this->indexExists('cotizacion_tesoreria', 'uq_cotiz_tes_empresa_fecha')) {
                $table->unique(['empresa_id', 'fecha'], 'uq_cotiz_tes_empresa_fecha');
            }
            if (! $this->indexExists('cotizacion_tesoreria', 'uq_cotiz_tes_empresa_fecha_anita')) {
                $table->unique(['empresa_id', 'fecha_anita'], 'uq_cotiz_tes_empresa_fecha_anita');
            }
            if (! $this->indexExists('cotizacion_tesoreria', 'idx_cotiz_tes_empresa')) {
                $table->index('empresa_id', 'idx_cotiz_tes_empresa');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('cotizacion_tesoreria')) {
            return;
        }

        $this->dropIndexIfExists('cotizacion_tesoreria', 'uq_cotiz_tes_empresa_fecha');
        $this->dropIndexIfExists('cotizacion_tesoreria', 'uq_cotiz_tes_empresa_fecha_anita');
        $this->dropIndexIfExists('cotizacion_tesoreria', 'idx_cotiz_tes_empresa');

        if (Schema::hasColumn('cotizacion_tesoreria', 'empresa_id')) {
            Schema::table('cotizacion_tesoreria', function (Blueprint $table) {
                $table->dropColumn('empresa_id');
            });
        }

        Schema::table('cotizacion_tesoreria', function (Blueprint $table) {
            if (! $this->indexExists('cotizacion_tesoreria', 'uq_cotizacion_tesoreria_fecha')) {
                $table->unique('fecha', 'uq_cotizacion_tesoreria_fecha');
            }
            if (! $this->indexExists('cotizacion_tesoreria', 'uq_cotizacion_tesoreria_fecha_anita')) {
                $table->unique('fecha_anita', 'uq_cotizacion_tesoreria_fecha_anita');
            }
        });
    }

    private function dropIndexIfExists(string $tabla, string $indice): void
    {
        if (! $this->indexExists($tabla, $indice)) {
            return;
        }

        // Unique e index se dropean igual en MySQL con DROP INDEX
        DB::statement('ALTER TABLE `'.$tabla.'` DROP INDEX `'.$indice.'`');
    }

    private function indexExists(string $tabla, string $indice): bool
    {
        $db = Schema::getConnection()->getDatabaseName();
        $row = DB::selectOne(
            'SELECT 1 AS ok FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ?
             LIMIT 1',
            [$db, $tabla, $indice]
        );

        return $row !== null;
    }
};
