<?php

use App\Models\Configuracion\Provincia_Tasaiibb;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Completa “No inscripto” (ibrxp_p_no_inscr / ibrxp_porc_exento) vs Anita ibrxprov.
 * CABA quedó en 0 por el seed; el resto de jurisdicciones no tenían fila.
 */
return new class extends Migration
{
    private const CONDICION = 'No inscripto';

    private const CONDICION_LEGACY = 'Exento';

    /**
     * Snapshot Anita ibrxprov 2026-08-29 (ibrxp_p_no_inscr + ibrxp_min_exento).
     * Mínimo >= 500 → minimoneto; si no → minimopercepcion.
     * Córdoba 1000 se deja como mínima percepción (mismo criterio que Local/Convenio).
     *
     * @var array<int, array{tasa: float, minimoneto: float, minimopercepcion: float, existia: bool}>
     */
    private const TASAS = [
        901 => ['tasa' => 6.0, 'minimoneto' => 3000, 'minimopercepcion' => 0, 'existia' => true],
        904 => ['tasa' => 1.0, 'minimoneto' => 0, 'minimopercepcion' => 1000, 'existia' => false],
        908 => ['tasa' => 3.0, 'minimoneto' => 0, 'minimopercepcion' => 0, 'existia' => false],
        911 => ['tasa' => 2.5, 'minimoneto' => 0, 'minimopercepcion' => 0, 'existia' => false],
        914 => ['tasa' => 3.31, 'minimoneto' => 0, 'minimopercepcion' => 0, 'existia' => false],
        917 => ['tasa' => 3.6, 'minimoneto' => 0, 'minimopercepcion' => 50, 'existia' => false],
        919 => ['tasa' => 3.5, 'minimoneto' => 0, 'minimopercepcion' => 50, 'existia' => false],
        921 => ['tasa' => 2.5, 'minimoneto' => 360000, 'minimopercepcion' => 0, 'existia' => false],
        924 => ['tasa' => 5.0, 'minimoneto' => 0, 'minimopercepcion' => 0, 'existia' => false],
    ];

    public function up(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $now = now();
        $condicionId = $this->condicionId();
        if ($condicionId <= 0) {
            return;
        }

        $usuarioId = (int) (DB::table('usuario')->orderBy('id')->value('id') ?? 1);
        $provincias = $this->provinciasPorJurisdiccion();

        foreach (self::TASAS as $jur => $vals) {
            $provinciaId = $provincias[$jur] ?? 0;
            if ($provinciaId <= 0) {
                continue;
            }
            $fila = DB::table('provincia_tasaiibb')
                ->where('provincia_id', $provinciaId)
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
                    'provincia_id' => $provinciaId,
                    'condicioniibb_id' => $condicionId,
                    'creousuario_id' => $usuarioId,
                    'created_at' => $now,
                ]));
            }
        }
    }

    public function down(): void
    {
        if (! EntornoEmpresaSupport::esElBierzo()) {
            return;
        }

        $condicionId = $this->condicionId();
        if ($condicionId <= 0) {
            return;
        }

        $provincias = $this->provinciasPorJurisdiccion();
        $cabaId = $provincias[901] ?? 0;
        if ($cabaId > 0) {
            DB::table('provincia_tasaiibb')
                ->where('provincia_id', $cabaId)
                ->where('condicioniibb_id', $condicionId)
                ->update([
                    'tasa' => 0.0,
                    'minimoneto' => 3000,
                    'minimopercepcion' => 0,
                    'updated_at' => now(),
                ]);
        }

        $idsNuevos = [];
        foreach (self::TASAS as $jur => $vals) {
            if ($vals['existia']) {
                continue;
            }
            $provinciaId = $provincias[$jur] ?? 0;
            if ($provinciaId > 0) {
                $idsNuevos[] = $provinciaId;
            }
        }
        if ($idsNuevos === []) {
            return;
        }

        EloquentAuditDeleteSupport::each(
            Provincia_Tasaiibb::query()
                ->where('condicioniibb_id', $condicionId)
                ->whereIn('provincia_id', $idsNuevos)
        );
    }

    private function condicionId(): int
    {
        return (int) (DB::table('condicionIIBB')
            ->whereIn('nombre', [self::CONDICION, self::CONDICION_LEGACY])
            ->orderByRaw('CASE WHEN nombre = ? THEN 0 ELSE 1 END', [self::CONDICION])
            ->value('id') ?? 0);
    }

    /** @return array<int, int> */
    private function provinciasPorJurisdiccion(): array
    {
        $out = [];
        $filas = DB::table('provincia')
            ->whereNotNull('jurisdiccion')
            ->get(['id', 'jurisdiccion']);
        foreach ($filas as $fila) {
            $jur = (int) $fila->jurisdiccion;
            if ($jur > 0) {
                $out[$jur] = (int) $fila->id;
            }
        }

        return $out;
    }
};
