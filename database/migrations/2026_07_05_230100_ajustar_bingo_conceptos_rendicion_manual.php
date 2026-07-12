<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajusta conceptos precargados: solo Bingo 47% y Línea 6% se calculan solos.
 * El resto (B.U.B, premios, vales, etc.) se cargan manual en la rendición cuando aplican.
 */
return new class extends Migration
{
    /** @var list<int> */
    private array $empresaIds = [1, 2, 3];

    /** @var list<string> */
    private array $codigosManuales = [
        'REFUERZO',
        'PANTALLAS',
        'PREMEFEC',
        'BUB_APE',
        'BUB_CIE',
        'PREM2',
        'PREM5',
        'PREM10',
        'PREM15',
        'PREM65',
        'VALES',
        'SOBRANTE',
        'REDONDEO',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('bingo_concepto_rendicion')) {
            return;
        }

        DB::table('bingo_concepto_rendicion')
            ->whereIn('empresa_id', $this->empresaIds)
            ->whereIn('codigo', $this->codigosManuales)
            ->update([
                'base_calculo' => 'manual',
                'porcentaje' => null,
                'monto_fijo' => null,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('bingo_concepto_rendicion')) {
            return;
        }

        $revertir = [
            'BUB_APE' => ['porcentaje' => 0.5, 'base_calculo' => 'total_cartones'],
            'BUB_CIE' => ['porcentaje' => 0.5, 'base_calculo' => 'total_cartones'],
            'PREM2' => ['porcentaje' => 2, 'base_calculo' => 'total_cartones'],
            'PREM5' => ['porcentaje' => 5, 'base_calculo' => 'total_cartones'],
            'PREM10' => ['porcentaje' => 10, 'base_calculo' => 'total_cartones'],
            'PREM15' => ['porcentaje' => 15, 'base_calculo' => 'total_cartones'],
            'PREM65' => ['porcentaje' => 65, 'base_calculo' => 'total_cartones'],
        ];

        foreach ($revertir as $codigo => $datos) {
            DB::table('bingo_concepto_rendicion')
                ->whereIn('empresa_id', $this->empresaIds)
                ->where('codigo', $codigo)
                ->update(array_merge($datos, ['updated_at' => now()]));
        }
    }
};
