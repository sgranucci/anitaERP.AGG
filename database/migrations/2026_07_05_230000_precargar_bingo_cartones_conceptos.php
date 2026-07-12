<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo inicial bingo según planilla manual (scan 01/07/2026).
 *
 * Columna derecha: cartones por precio unitario ($2.000 / $3.000 / $4.000).
 * Columna izquierda: conceptos de rendición con signo y porcentaje.
 */
return new class extends Migration
{
    /** @var list<int> */
    private array $empresaIds = [1, 2, 3];

    /** @var list<array<string, mixed>> */
    private array $cartones = [
        [
            'codigo' => 'C2000',
            'nombre' => 'Cartón $2.000',
            'precio_unitario' => 2000,
            'lineas' => 4,
            'es_azar' => false,
            'orden' => 10,
        ],
        [
            'codigo' => 'C3000',
            'nombre' => 'Cartón $3.000',
            'precio_unitario' => 3000,
            'lineas' => 4,
            'es_azar' => false,
            'orden' => 20,
        ],
        [
            'codigo' => 'C4000',
            'nombre' => 'Cartón $4.000',
            'precio_unitario' => 4000,
            'lineas' => 4,
            'es_azar' => false,
            'orden' => 30,
        ],
    ];

    /** @var list<array<string, mixed>> */
    private array $conceptos = [
        [
            'codigo' => 'REFUERZO',
            'signo' => '+',
            'detalle' => 'Refuerzo',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 10,
        ],
        [
            'codigo' => 'BINGO47',
            'signo' => '-',
            'detalle' => 'Bingo 47%',
            'porcentaje' => 47,
            'base_calculo' => 'total_cartones',
            'monto_fijo' => null,
            'orden' => 20,
        ],
        [
            'codigo' => 'LINEA6',
            'signo' => '-',
            'detalle' => 'Línea 6%',
            'porcentaje' => 6,
            'base_calculo' => 'total_cartones',
            'monto_fijo' => null,
            'orden' => 30,
        ],
        [
            'codigo' => 'PANTALLAS',
            'signo' => '-',
            'detalle' => 'Pantallas acumulativos',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 40,
        ],
        [
            'codigo' => 'PREMEFEC',
            'signo' => '-',
            'detalle' => 'Premios en efectivo',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 50,
        ],
        [
            'codigo' => 'BUB_APE',
            'signo' => '-',
            'detalle' => 'B.U.B 0,50% apertura',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 60,
        ],
        [
            'codigo' => 'BUB_CIE',
            'signo' => '-',
            'detalle' => 'B.U.B 0,50% cierre',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 70,
        ],
        [
            'codigo' => 'PREM2',
            'signo' => '-',
            'detalle' => 'Premio 2%',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 80,
        ],
        [
            'codigo' => 'PREM5',
            'signo' => '-',
            'detalle' => 'Premio 5%',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 90,
        ],
        [
            'codigo' => 'PREM10',
            'signo' => '-',
            'detalle' => 'Premio 10%',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 100,
        ],
        [
            'codigo' => 'PREM15',
            'signo' => '-',
            'detalle' => 'Premio 15%',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 110,
        ],
        [
            'codigo' => 'PREM65',
            'signo' => '-',
            'detalle' => 'Premio 65%',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 120,
        ],
        [
            'codigo' => 'VALES',
            'signo' => '-',
            'detalle' => 'Vales',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 130,
        ],
        [
            'codigo' => 'SOBRANTE',
            'signo' => '+',
            'detalle' => 'Sobrante',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 140,
        ],
        [
            'codigo' => 'REDONDEO',
            'signo' => '-',
            'detalle' => 'Redondeo',
            'porcentaje' => null,
            'base_calculo' => 'manual',
            'monto_fijo' => null,
            'orden' => 150,
        ],
    ];

    public function up(): void
    {
        if (! Schema::hasTable('bingo_carton') || ! Schema::hasTable('bingo_concepto_rendicion')) {
            return;
        }

        $now = now();

        foreach ($this->empresaIds as $empresaId) {
            if (! DB::table('empresa')->where('id', $empresaId)->exists()) {
                continue;
            }

            foreach ($this->cartones as $carton) {
                $existe = DB::table('bingo_carton')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $carton['codigo'])
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('bingo_carton')->insert([
                    'empresa_id' => $empresaId,
                    'codigo' => $carton['codigo'],
                    'nombre' => $carton['nombre'],
                    'precio_unitario' => $carton['precio_unitario'],
                    'lineas' => $carton['lineas'],
                    'es_azar' => $carton['es_azar'],
                    'orden' => $carton['orden'],
                    'estado' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($this->conceptos as $concepto) {
                $existe = DB::table('bingo_concepto_rendicion')
                    ->where('empresa_id', $empresaId)
                    ->where('codigo', $concepto['codigo'])
                    ->exists();

                if ($existe) {
                    continue;
                }

                DB::table('bingo_concepto_rendicion')->insert([
                    'empresa_id' => $empresaId,
                    'codigo' => $concepto['codigo'],
                    'signo' => $concepto['signo'],
                    'detalle' => $concepto['detalle'],
                    'porcentaje' => $concepto['porcentaje'],
                    'base_calculo' => $concepto['base_calculo'],
                    'monto_fijo' => $concepto['monto_fijo'],
                    'orden' => $concepto['orden'],
                    'estado' => 'activo',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bingo_carton') || ! Schema::hasTable('bingo_concepto_rendicion')) {
            return;
        }

        $codigosCarton = array_column($this->cartones, 'codigo');
        $codigosConcepto = array_column($this->conceptos, 'codigo');

        DB::table('bingo_carton')
            ->whereIn('empresa_id', $this->empresaIds)
            ->whereIn('codigo', $codigosCarton)
            ->delete();

        DB::table('bingo_concepto_rendicion')
            ->whereIn('empresa_id', $this->empresaIds)
            ->whereIn('codigo', $codigosConcepto)
            ->delete();
    }
};
