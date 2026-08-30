<?php

use App\Support\Configuracion\EntornoEmpresaSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El Bierzo: nominación de agente (ibrxprov) + alinear tasas/mínimos de provincia.
 * Las tasas son del fisco (catálogo provincia); el tilde de agente es por empresa.
 */
return new class extends Migration
{
    /** @var list<array{jur:int, percibe:bool, retiene:bool}> */
    private const AGENTES_EMPRESA_1 = [
        ['jur' => 901, 'percibe' => true, 'retiene' => false],
        ['jur' => 902, 'percibe' => true, 'retiene' => true],
        ['jur' => 904, 'percibe' => true, 'retiene' => false],
        ['jur' => 908, 'percibe' => true, 'retiene' => false],
        ['jur' => 911, 'percibe' => true, 'retiene' => false],
        ['jur' => 914, 'percibe' => true, 'retiene' => false],
        ['jur' => 915, 'percibe' => true, 'retiene' => false],
        ['jur' => 917, 'percibe' => true, 'retiene' => false],
        ['jur' => 919, 'percibe' => true, 'retiene' => false],
        ['jur' => 921, 'percibe' => true, 'retiene' => false],
        ['jur' => 924, 'percibe' => true, 'retiene' => false],
    ];

    /**
     * Snapshot Anita ibrxprov 2026-08-29.
     * minimoneto si el mínimo Anita >= 500; si no, minimopercepcion.
     * Córdoba 1000 se deja como mínima percepción (así estaba y Anita no distingue).
     *
     * @var array<int, array<string, array{tasa: float, minimoneto: float, minimopercepcion: float}>>
     */
    private const TASAS = [
        901 => [
            'Convenio' => ['tasa' => 6.0, 'minimoneto' => 3000, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 6.0, 'minimoneto' => 3000, 'minimopercepcion' => 0],
            'Exento' => ['tasa' => 0.0, 'minimoneto' => 3000, 'minimopercepcion' => 0],
        ],
        902 => [
            'Convenio' => ['tasa' => 8.0, 'minimoneto' => 3500, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 8.0, 'minimoneto' => 3500, 'minimopercepcion' => 0],
            'Exento' => ['tasa' => 0.0, 'minimoneto' => 0, 'minimopercepcion' => 0],
        ],
        904 => [
            'Convenio' => ['tasa' => 1.0, 'minimoneto' => 0, 'minimopercepcion' => 1000],
            'Contribuyente Local' => ['tasa' => 1.0, 'minimoneto' => 0, 'minimopercepcion' => 1000],
        ],
        908 => [
            'Convenio' => ['tasa' => 3.0, 'minimoneto' => 0, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 3.0, 'minimoneto' => 0, 'minimopercepcion' => 0],
        ],
        911 => [
            'Convenio' => ['tasa' => 2.5, 'minimoneto' => 0, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 2.5, 'minimoneto' => 0, 'minimopercepcion' => 0],
        ],
        914 => [
            'Convenio' => ['tasa' => 3.31, 'minimoneto' => 0, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 3.31, 'minimoneto' => 0, 'minimopercepcion' => 300],
        ],
        915 => [
            'Convenio' => ['tasa' => 2.0, 'minimoneto' => 0, 'minimopercepcion' => 50],
            'Contribuyente Local' => ['tasa' => 2.0, 'minimoneto' => 0, 'minimopercepcion' => 50],
        ],
        917 => [
            'Convenio' => ['tasa' => 3.6, 'minimoneto' => 0, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 3.6, 'minimoneto' => 0, 'minimopercepcion' => 0],
        ],
        919 => [
            'Convenio' => ['tasa' => 3.5, 'minimoneto' => 0, 'minimopercepcion' => 50],
            'Contribuyente Local' => ['tasa' => 3.5, 'minimoneto' => 0, 'minimopercepcion' => 50],
        ],
        921 => [
            'Convenio' => ['tasa' => 1.25, 'minimoneto' => 360000, 'minimopercepcion' => 0],
            'Contribuyente Local' => ['tasa' => 2.5, 'minimoneto' => 360000, 'minimopercepcion' => 0],
        ],
        924 => [
            'Convenio' => ['tasa' => 2.5, 'minimoneto' => 0, 'minimopercepcion' => 50],
            'Contribuyente Local' => ['tasa' => 5.0, 'minimoneto' => 0, 'minimopercepcion' => 50],
        ],
    ];

    /** @var array<int, float> */
    private const MIN_COEF = [
        915 => 0.1,
        921 => 0.1,
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $now = now();
        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 1);
        $empresaId = (int) (DB::table('empresa')->where('codigo', '1')->orderBy('id')->value('id')
            ?? DB::table('empresa')->orderBy('id')->value('id') ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $condiciones = DB::table('condicionIIBB')->pluck('id', 'nombre');
        $provincias = DB::table('provincia')->whereNotNull('jurisdiccion')->get(['id', 'jurisdiccion']);

        foreach (self::AGENTES_EMPRESA_1 as $agente) {
            $provincia = $provincias->firstWhere('jurisdiccion', (string) $agente['jur'])
                ?? $provincias->firstWhere('jurisdiccion', $agente['jur']);
            if ($provincia === null) {
                continue;
            }
            $existe = DB::table('empresa_jurisdiccion_iibb')
                ->where('empresa_id', $empresaId)
                ->where('provincia_id', $provincia->id)
                ->exists();
            if ($existe) {
                DB::table('empresa_jurisdiccion_iibb')
                    ->where('empresa_id', $empresaId)
                    ->where('provincia_id', $provincia->id)
                    ->update([
                        'es_agente_percepcion' => $agente['percibe'],
                        'es_agente_retencion' => $agente['retiene'],
                        'updated_at' => $now,
                    ]);
            } else {
                DB::table('empresa_jurisdiccion_iibb')->insert([
                    'empresa_id' => $empresaId,
                    'provincia_id' => $provincia->id,
                    'es_agente_percepcion' => $agente['percibe'],
                    'es_agente_retencion' => $agente['retiene'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        foreach (self::TASAS as $jur => $porCondicion) {
            $provincia = $provincias->firstWhere('jurisdiccion', (string) $jur)
                ?? $provincias->firstWhere('jurisdiccion', $jur);
            if ($provincia === null) {
                continue;
            }
            foreach ($porCondicion as $nombreCond => $vals) {
                $condicionId = (int) ($condiciones[$nombreCond] ?? 0);
                if ($condicionId <= 0) {
                    continue;
                }
                $fila = DB::table('provincia_tasaiibb')
                    ->where('provincia_id', $provincia->id)
                    ->where('condicioniibb_id', $condicionId)
                    ->first();
                $payload = [
                    'tasa' => $vals['tasa'],
                    'minimoneto' => $vals['minimoneto'],
                    'minimopercepcion' => $vals['minimopercepcion'],
                    'updated_at' => $now,
                ];
                if ($fila) {
                    DB::table('provincia_tasaiibb')->where('id', $fila->id)->update($payload);
                } else {
                    DB::table('provincia_tasaiibb')->insert(array_merge($payload, [
                        'provincia_id' => $provincia->id,
                        'condicioniibb_id' => $condicionId,
                        'creousuario_id' => $usuarioId,
                        'created_at' => $now,
                    ]));
                }
            }
        }

        foreach (self::MIN_COEF as $jur => $coef) {
            DB::table('provincia')
                ->where('jurisdiccion', $jur)
                ->update(['minimocoeficientecm05' => $coef, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        DB::table('empresa_jurisdiccion_iibb')->delete();
    }
};
