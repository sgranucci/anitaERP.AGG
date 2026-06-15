<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Ventas\JornadaGastronomia;
use App\Services\Ventas\Gastronomia\GastronomiaCierreJornadaProcesoRendicionReparacionService;
use Illuminate\Console\Command;

class GastronomiaRepararRendicionPostCierreAnita extends Command
{
    protected $signature = 'gastronomia:reparar-rendicion-post-cierre-anita
                            {--empresa=2 : empresa_id}
                            {--fecha-desde= : Y-m-d}
                            {--fecha-hasta= : Y-m-d}
                            {--jornada= : ID jornada_gastronomia}
                            {--dry-run : Solo verificar / simular regrabado}
                            {--regrabar : Aplicar regrabado cuando falte CIERRE-WAITRY}';

    protected $description = 'Verifica facturas post-cierre Waitry vs rendgastro CIERRE-WAITRY; opcionalmente re-graba con numeración dedicada';

    public function handle(GastronomiaCierreJornadaProcesoRendicionReparacionService $service): int
    {
        $this->line('Bridge: '.ApiAnita::urlBridge());

        $jornadas = $this->resolverJornadas();
        if ($jornadas === []) {
            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $regrabar = (bool) $this->option('regrabar');
        $filas = [];
        $pendientes = 0;

        foreach ($jornadas as $jornada) {
            if ($regrabar) {
                $r = $service->regrabarJornada($jornada, $dryRun);
            } else {
                $r = $service->verificarJornada($jornada);
            }

            $filas[] = [
                $jornada->id,
                $r['fecha_jornada'] ?? '—',
                number_format((float) ($r['ventas_erp_post_cierre'] ?? 0), 2, '.', ''),
                isset($r['rendgastro_post_cierre']) ? number_format((float) $r['rendgastro_post_cierre'], 2, '.', '') : '—',
                $r['cabeceras_waitry'] ?? 0,
                $r['snapshot_nro_oper'] ?? '—',
                ($r['snapshot_valido'] ?? false) ? 'sí' : 'no',
                $r['estado'] ?? '—',
                $r['accion'] ?? ($r['requiere_regrabado'] ?? false ? 'pendiente' : '—'),
            ];

            if ($r['requiere_regrabado'] ?? false) {
                $pendientes++;
            }
        }

        $this->table(
            ['Jornada', 'Fecha', 'ERP post-cierre', 'Rendg CIERRE-WAITRY', 'Cabeceras', 'Snap nro_oper', 'Snap OK', 'Estado', 'Acción'],
            $filas,
        );

        if ($pendientes > 0 && ! $regrabar) {
            $this->warn($pendientes.' jornada(s) requieren regrabado. Use --regrabar (sin --dry-run para aplicar).');
        }

        return $pendientes > 0 && ! $regrabar ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return list<JornadaGastronomia>
     */
    private function resolverJornadas(): array
    {
        $jornadaId = (int) $this->option('jornada');
        if ($jornadaId > 0) {
            $j = JornadaGastronomia::query()->find($jornadaId);
            if ($j === null) {
                $this->error('No existe jornada #'.$jornadaId);

                return [];
            }

            return [$j];
        }

        $empresaId = (int) $this->option('empresa');
        $desde = trim((string) ($this->option('fecha-desde') ?? ''));
        $hasta = trim((string) ($this->option('fecha-hasta') ?? $desde));

        if ($desde === '') {
            $this->error('Indique --jornada=ID o --fecha-desde=Y-m-d');

            return [];
        }

        return JornadaGastronomia::query()
            ->where('empresa_id', $empresaId)
            ->whereDate('fecha_jornada', '>=', $desde)
            ->whereDate('fecha_jornada', '<=', $hasta)
            ->orderBy('fecha_jornada')
            ->get()
            ->all();
    }
}
