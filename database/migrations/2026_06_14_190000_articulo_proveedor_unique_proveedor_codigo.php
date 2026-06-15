<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('articulo_proveedor')) {
            throw new \RuntimeException(
                'La tabla articulo_proveedor no existe. Ejecute antes las migraciones base '
                .'(2026_06_07_163217_crear_tabla_articulo_proveedor y siguientes).'
            );
        }

        $duplicados = DB::table('articulo_proveedor')
            ->select('proveedor_id', 'codigo_articulo_proveedor', DB::raw('COUNT(*) as total'))
            ->whereNotNull('codigo_articulo_proveedor')
            ->where('codigo_articulo_proveedor', '!=', '')
            ->groupBy('proveedor_id', 'codigo_articulo_proveedor')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicados as $dup) {
            $filas = DB::table('articulo_proveedor')
                ->where('proveedor_id', $dup->proveedor_id)
                ->where('codigo_articulo_proveedor', $dup->codigo_articulo_proveedor)
                ->orderByDesc('preferido')
                ->orderByDesc('activo')
                ->orderBy('id')
                ->pluck('id')
                ->all();

            $conservar = array_shift($filas);
            if ($filas !== []) {
                DB::table('articulo_proveedor')->whereIn('id', $filas)->delete();
            }
        }

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->index('articulo_id', 'idx_articulo_proveedor_articulo_fk');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropUnique('uk_articulo_proveedor_art_prov');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->index(['articulo_id', 'proveedor_id'], 'idx_articulo_proveedor_art_prov');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropIndex('idx_articulo_proveedor_prov_codigo');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->unique(
                ['proveedor_id', 'codigo_articulo_proveedor'],
                'uk_articulo_proveedor_prov_codigo'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('articulo_proveedor')) {
            return;
        }

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropUnique('uk_articulo_proveedor_prov_codigo');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->index(
                ['proveedor_id', 'codigo_articulo_proveedor'],
                'idx_articulo_proveedor_prov_codigo'
            );
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropIndex('idx_articulo_proveedor_art_prov');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->dropIndex('idx_articulo_proveedor_articulo_fk');
        });

        Schema::table('articulo_proveedor', function (Blueprint $table) {
            $table->unique(['articulo_id', 'proveedor_id'], 'uk_articulo_proveedor_art_prov');
        });
    }
};
