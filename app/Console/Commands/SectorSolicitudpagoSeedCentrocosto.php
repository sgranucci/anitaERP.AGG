<?php

namespace App\Console\Commands;

use App\Models\Solicitudpago\Sector_Solicitudpago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfill sector_solicitudpago.centrocosto_id con el CC más usado
 * en las solicitudes de pago que tienen ese sector.
 */
class SectorSolicitudpagoSeedCentrocosto extends Command
{
    protected $signature = 'solicitudpago:seed-centrocosto-sector
                            {--dry-run : Solo reportar, no actualizar}
                            {--solo-nulos : Solo sectores con centrocosto_id null}';

    protected $description = 'Seed centrocosto_id de sectores SP desde las solicitudes que usan cada sector';

    public function handle(): int
    {
        if (! Schema::hasColumn('sector_solicitudpago', 'centrocosto_id')) {
            $this->error('Falta columna sector_solicitudpago.centrocosto_id. Correr la migración primero.');

            return self::FAILURE;
        }

        if (! Schema::hasColumn('solicitudpago', 'centrocosto_id')) {
            $this->error('Falta columna solicitudpago.centrocosto_id.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $soloNulos = (bool) $this->option('solo-nulos');

        $frecuencias = DB::table('solicitudpago')
            ->select('sector_solicitudpago_id', 'centrocosto_id', DB::raw('COUNT(*) as n'))
            ->whereNotNull('sector_solicitudpago_id')
            ->whereNotNull('centrocosto_id')
            ->where('centrocosto_id', '>', 0)
            ->groupBy('sector_solicitudpago_id', 'centrocosto_id')
            ->orderByDesc('n')
            ->get();

        $ccEtiquetas = DB::table('centrocosto')
            ->get(['id', 'codigo', 'nombre'])
            ->keyBy('id')
            ->map(fn ($cc) => $cc->codigo.'-'.$cc->nombre)
            ->all();

        /** @var array<int, array{centrocosto_id: int, n: int}> $mejorPorSector */
        $mejorPorSector = [];
        foreach ($frecuencias as $row) {
            $sectorId = (int) $row->sector_solicitudpago_id;
            if (isset($mejorPorSector[$sectorId])) {
                continue;
            }
            $mejorPorSector[$sectorId] = [
                'centrocosto_id' => (int) $row->centrocosto_id,
                'n' => (int) $row->n,
            ];
        }

        $sectores = Sector_Solicitudpago::query()
            ->with('centrocostos')
            ->orderBy('codigo')
            ->get();

        $actualizados = 0;
        $omitidos = 0;
        $sinDatos = 0;
        $filas = [];

        $etiquetaCc = static function (?int $ccId) use ($ccEtiquetas): string {
            if ($ccId === null || $ccId <= 0) {
                return '—';
            }

            return $ccEtiquetas[$ccId] ?? (string) $ccId;
        };

        foreach ($sectores as $sector) {
            $ccActualId = $sector->centrocosto_id ? (int) $sector->centrocosto_id : null;
            $mejor = $mejorPorSector[(int) $sector->id] ?? null;
            if ($mejor === null) {
                $sinDatos++;
                $filas[] = [
                    $sector->codigo,
                    $sector->nombre,
                    $etiquetaCc($ccActualId),
                    'sin SP con CC',
                    'omitido',
                ];

                continue;
            }

            $ccNuevoEtiqueta = $etiquetaCc($mejor['centrocosto_id']).' (n='.$mejor['n'].')';

            if ($soloNulos && $ccActualId) {
                $omitidos++;
                $filas[] = [
                    $sector->codigo,
                    $sector->nombre,
                    $etiquetaCc($ccActualId),
                    $ccNuevoEtiqueta,
                    'ya tenía CC',
                ];

                continue;
            }

            if ($ccActualId === $mejor['centrocosto_id']) {
                $omitidos++;
                $filas[] = [
                    $sector->codigo,
                    $sector->nombre,
                    $etiquetaCc($ccActualId),
                    $ccNuevoEtiqueta,
                    'igual',
                ];

                continue;
            }

            if (! $dryRun) {
                $sector->update(['centrocosto_id' => $mejor['centrocosto_id']]);
            }
            $actualizados++;
            $filas[] = [
                $sector->codigo,
                $sector->nombre,
                $etiquetaCc($ccActualId),
                $ccNuevoEtiqueta,
                $dryRun ? 'previsto' : 'actualizado',
            ];
        }

        $this->table(
            ['Código', 'Sector', 'CC actual', 'CC desde SP', 'Acción'],
            $filas
        );

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Actualizados / previstos', $actualizados],
                ['Omitidos', $omitidos],
                ['Sin SP con CC', $sinDatos],
                ['Quedan null', Sector_Solicitudpago::query()->whereNull('centrocosto_id')->count()],
                ['Dry-run', $dryRun ? 'sí' : 'no'],
            ]
        );

        return self::SUCCESS;
    }
}
