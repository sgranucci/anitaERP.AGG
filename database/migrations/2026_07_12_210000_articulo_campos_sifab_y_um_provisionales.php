<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Campos SIFAB en articulo + U.M. provisionales (INTERFORMING).
 * Los IDs de unidadmedida parten en 9001 para no chocar con Anita/ERP actuales.
 * unidadmedida.codigo guarda el codigoInterno SIFAB (1329, 1330, …).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articulo', function (Blueprint $table) {
            if (! Schema::hasColumn('articulo', 'codigo_interno_sifab')) {
                $table->unsignedInteger('codigo_interno_sifab')->nullable()->unique()->after('sku');
            }
            if (! Schema::hasColumn('articulo', 'rubro_sifab')) {
                $table->string('rubro_sifab', 20)->nullable()->after('grupoproducto');
            }
            if (! Schema::hasColumn('articulo', 'clasematerial')) {
                $table->string('clasematerial', 20)->nullable()->after('rubro_sifab');
            }
            if (! Schema::hasColumn('articulo', 'gestioncompra')) {
                $table->string('gestioncompra', 20)->nullable()->after('clasematerial');
            }
        });

        if (strtoupper((string) config('app.empresa')) !== 'INTERFORMING') {
            return;
        }

        // U.M. vistas en el Excel de materiales; descripción provisional hasta confirmación SIFAB.
        $ums = [
            1329 => ['nombre' => 'SIFAB 1329 (confirmar descripción)', 'abreviatura' => 'SF29'],
            1330 => ['nombre' => 'SIFAB 1330 (confirmar descripción)', 'abreviatura' => 'SF30'],
            1332 => ['nombre' => 'SIFAB 1332 (confirmar descripción)', 'abreviatura' => 'SF32'],
            1333 => ['nombre' => 'SIFAB 1333 (confirmar descripción)', 'abreviatura' => 'SF33'],
            1335 => ['nombre' => 'SIFAB 1335 (confirmar descripción)', 'abreviatura' => 'SF35'],
        ];

        $nextId = max(9001, ((int) DB::table('unidadmedida')->max('id')) + 1);
        if ($nextId < 9001) {
            $nextId = 9001;
        }

        foreach ($ums as $codigoSifab => $meta) {
            $existe = DB::table('unidadmedida')->where('codigo', (string) $codigoSifab)->exists();
            if ($existe) {
                continue;
            }
            while (DB::table('unidadmedida')->where('id', $nextId)->exists()) {
                $nextId++;
            }
            DB::table('unidadmedida')->insert([
                'id' => $nextId,
                'nombre' => $meta['nombre'],
                'abreviatura' => $meta['abreviatura'],
                'codigo' => (string) $codigoSifab,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $nextId++;
        }
    }

    public function down(): void
    {
        if (strtoupper((string) config('app.empresa')) === 'INTERFORMING') {
            DB::table('unidadmedida')->whereIn('codigo', ['1329', '1330', '1332', '1333', '1335'])->delete();
        }

        Schema::table('articulo', function (Blueprint $table) {
            foreach (['gestioncompra', 'clasematerial', 'rubro_sifab', 'codigo_interno_sifab'] as $col) {
                if (Schema::hasColumn('articulo', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
