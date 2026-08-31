<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * Motor LSD de detracción Ley 27.430: tabla vigente en parametro_sueldos
 * y reemplazo de la fórmula Anita del concepto 1002 por detraccion().
 *
 * El 04 ya no depende de liquidar el 1002. El concepto queda informativo
 * (no pega al neto) por si el grupo lo sigue mostrando en el recibo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ahora = now();

        $this->sembrarParametro([
            'codigo' => 'DETRACCION_LEY_27430',
            'descripcion' => 'Detracción mensual Ley 27.430 art. 4 (LSD base 10)',
            'tipo' => 'numero',
            'unidad' => '$',
            'valor' => 7003.68,
            'valor_texto' => null,
            'fecha_vigencia' => '2019-01-01',
        ], $ahora);

        $this->sembrarParametro([
            'codigo' => 'DETRACCION_TIEMPO_PARCIAL',
            'descripcion' => 'Factor detracción tiempo parcial AFIP (0.67 = 67 %)',
            'tipo' => 'numero',
            'unidad' => '',
            'valor' => 0.67,
            'valor_texto' => null,
            'fecha_vigencia' => '2019-01-01',
        ], $ahora);

        $this->sembrarParametro([
            'codigo' => 'DETRACCION_MODALIDADES_PARCIAL',
            'descripcion' => 'Códigos SIJP AFIP de tiempo parcial (vacío = no aplicar 67 %). Ej: 8,14',
            'tipo' => 'texto',
            'unidad' => null,
            'valor' => 0,
            'valor_texto' => '',
            'fecha_vigencia' => '2019-01-01',
        ], $ahora);

        if (! Schema::hasTable('concepto_sueldos')) {
            return;
        }

        DB::table('concepto_sueldos')
            ->where('codigo', 1002)
            ->update([
                'formula' => 'detraccion()',
                'tipo' => 'informativo',
                'updated_at' => $ahora,
            ]);
    }

    /**
     * @param  array{codigo: string, descripcion: string, tipo: string, unidad: ?string, valor: float|int, valor_texto: ?string, fecha_vigencia: string}  $def
     */
    private function sembrarParametro(array $def, $ahora): void
    {
        if (! Schema::hasTable('parametro_sueldos')) {
            return;
        }
        $existe = DB::table('parametro_sueldos')
            ->whereNull('empresa_id')
            ->where('codigo', $def['codigo'])
            ->exists();
        if ($existe) {
            return;
        }
        $id = DB::table('parametro_sueldos')->insertGetId([
            'empresa_id' => null,
            'codigo' => $def['codigo'],
            'descripcion' => $def['descripcion'],
            'tipo' => $def['tipo'],
            'unidad' => $def['unidad'],
            'activo' => true,
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
        DB::table('parametro_valor_sueldos')->insert([
            'parametro_id' => $id,
            'fecha_vigencia' => $def['fecha_vigencia'],
            'valor' => $def['valor'],
            'valor_texto' => $def['valor_texto'],
            'created_at' => $ahora,
            'updated_at' => $ahora,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasTable('concepto_sueldos')) {
            DB::table('concepto_sueldos')
                ->where('codigo', 1002)
                ->where('formula', 'detraccion()')
                ->update([
                    'tipo' => 'retencion',
                    'updated_at' => now(),
                ]);
        }
        if (! Schema::hasTable('parametro_sueldos')) {
            return;
        }
        $ids = DB::table('parametro_sueldos')
            ->whereNull('empresa_id')
            ->whereIn('codigo', [
                'DETRACCION_LEY_27430',
                'DETRACCION_TIEMPO_PARCIAL',
                'DETRACCION_MODALIDADES_PARCIAL',
            ])
            ->pluck('id');
        if ($ids->isEmpty()) {
            return;
        }
        DB::table('parametro_valor_sueldos')->whereIn('parametro_id', $ids)->delete();
        DB::table('parametro_sueldos')->whereIn('id', $ids)->delete();
    }
};
