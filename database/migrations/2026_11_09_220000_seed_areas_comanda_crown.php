<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Áreas de comanda Crown = Anita `areacom` (cncadmin / comanda.fc).
 *
 * areac_area | areac_desc
 * ---------- | ----------
 * 1          | ALIMENTOS
 * 2          | BEBIDAS
 * 3          | KIOSCO
 * 4          | POSTRES   (COMAN_AREA_POSTRE en comanda.fc)
 * 5          | ENTRADAS
 * 6          | SUSHI
 *
 * En Anita el ítem toma el área vía agrupación (`stkagr.stka_area`);
 * `confcomanda(host, area)` decide qué áreas imprime cada PC.
 */
return new class extends Migration
{
    /** @var list<array{codigo: string, nombre: string}> */
    private array $areasAnita = [
        ['codigo' => '1', 'nombre' => 'ALIMENTOS'],
        ['codigo' => '2', 'nombre' => 'BEBIDAS'],
        ['codigo' => '3', 'nombre' => 'KIOSCO'],
        ['codigo' => '4', 'nombre' => 'POSTRES'],
        ['codigo' => '5', 'nombre' => 'ENTRADAS'],
        ['codigo' => '6', 'nombre' => 'SUSHI'],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('area_comanda_gastronomia')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?: 1);
        $ahora = now();

        foreach ($this->areasAnita as $area) {
            $existente = DB::table('area_comanda_gastronomia')
                ->where('empresa_id', $empresaId)
                ->where('codigo', $area['codigo'])
                ->first();

            if ($existente) {
                DB::table('area_comanda_gastronomia')
                    ->where('id', $existente->id)
                    ->update([
                        'nombre' => $area['nombre'],
                        'updated_at' => $ahora,
                    ]);

                continue;
            }

            DB::table('area_comanda_gastronomia')->insert([
                'empresa_id' => $empresaId,
                'codigo' => $area['codigo'],
                'nombre' => $area['nombre'],
                'salida_id' => null,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            return;
        }
        if (! Schema::hasTable('area_comanda_gastronomia')) {
            return;
        }

        $empresaId = (int) (DB::table('empresa')->orderBy('id')->value('id') ?: 1);
        $codigos = array_column($this->areasAnita, 'codigo');

        DB::table('area_comanda_gastronomia')
            ->where('empresa_id', $empresaId)
            ->whereIn('codigo', $codigos)
            ->delete();
    }
};
