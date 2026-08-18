<?php

use App\Support\Database\MigrationDialectSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Unique global de Biyemas choca con Kandiko/Rebisco (mismos inroclienteid locales).
        $this->dropIndexIfExists('cliente_uif', 'uk_cliente_uif_inroclienteid');
        $this->dropIndexIfExists('cliente_uif', 'cliente_uif_inroclienteid_unique');

        Schema::table('cliente_uif', function (Blueprint $table) {
            // Índice no-unique de la migración anterior (puede coexistir; el unique lo reemplaza).
            try {
                $table->dropIndex('cliente_uif_anita_origen_inro_idx');
            } catch (\Throwable $e) {
                // ignore
            }
            $table->unique(['anita_origen', 'inroclienteid'], 'uk_cliente_uif_origen_inro');
        });
    }

    public function down(): void
    {
        Schema::table('cliente_uif', function (Blueprint $table) {
            try {
                $table->dropUnique('uk_cliente_uif_origen_inro');
            } catch (\Throwable $e) {
                // ignore
            }
            $table->unique('inroclienteid', 'uk_cliente_uif_inroclienteid');
        });
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        MigrationDialectSupport::dropIndiceOUnique($table, $index);
    }
};
