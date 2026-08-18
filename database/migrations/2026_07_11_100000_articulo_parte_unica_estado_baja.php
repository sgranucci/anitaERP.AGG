<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Estado de NPU: activo (A) o dado de baja (B) por rotura / no funcional.
 * El numeroparte permanece en la tabla (índice único) para impedir reutilización.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulo_parte_unica', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo_parte_unica', 'estado')) {
                $table->char('estado', 1)->default('A')->after('numeroparte');
            }
            if (! Schema::hasColumn('articulo_parte_unica', 'fecha_baja')) {
                $table->timestamp('fecha_baja')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('articulo_parte_unica', 'motivo_baja')) {
                $table->string('motivo_baja', 255)->nullable()->after('fecha_baja');
            }
            if (! Schema::hasColumn('articulo_parte_unica', 'movimientostock_id')) {
                $table->unsignedBigInteger('movimientostock_id')->nullable()->after('motivo_baja');
            }
        });

        Schema::table('articulo_parte_unica', function (Blueprint $table) {
            if (! $this->indexExists('articulo_parte_unica', 'idx_apu_estado_numeroparte')) {
                $table->index(['estado', 'numeroparte'], 'idx_apu_estado_numeroparte');
            }
            if (! $this->indexExists('articulo_parte_unica', 'idx_apu_movimientostock_id')) {
                $table->index('movimientostock_id', 'idx_apu_movimientostock_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articulo_parte_unica', function (Blueprint $table) {
            if ($this->indexExists('articulo_parte_unica', 'idx_apu_estado_numeroparte')) {
                $table->dropIndex('idx_apu_estado_numeroparte');
            }
            if ($this->indexExists('articulo_parte_unica', 'idx_apu_movimientostock_id')) {
                $table->dropIndex('idx_apu_movimientostock_id');
            }
            foreach (['movimientostock_id', 'motivo_baja', 'fecha_baja', 'estado'] as $col) {
                if (Schema::hasColumn('articulo_parte_unica', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return \App\Support\Database\MigrationDialectSupport::tieneIndice($table, $index);
    }
};
