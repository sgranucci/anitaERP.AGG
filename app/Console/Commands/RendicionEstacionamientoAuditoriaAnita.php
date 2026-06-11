<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionEstacionamientoAuditoriaAnitaService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RendicionEstacionamientoAuditoriaAnita extends Command
{
    protected $signature = 'rendicion-estacionamiento:auditoria-anita
                            {--fecha=* : Una o más fechas de jornada Y-m-d (default: ayer)}
                            {--empresa= : empresa_id (default: config rendicion_estacionamiento_anita.auditoria_diaria.empresa_id)}
                            {--puntoventa= : Código PV CAE opcional}
                            {--tolerancia= : Override tolerancia en pesos (default config)}
                            {--detalle : Muestra cabeceras rendgastro por PV con diferencias}';

    protected $description = 'Audita rendg_total_z y rendg_tot_nc (rendgastro) vs facturación ERP estacionamiento por PV y fecha de jornada';

    public function handle(RendicionEstacionamientoAuditoriaAnitaService $service): int
    {
        if (! config('rendicion_estacionamiento_anita.sincronizar', true)) {
            $this->warn('RENDICION_ESTACIONAMIENTO_SINCRONIZAR_ANITA está deshabilitado; no hay bridge activo.');

            return self::SUCCESS;
        }

        $empresaId = $this->option('empresa') !== null
            ? (int) $this->option('empresa')
            : (int) config('rendicion_estacionamiento_anita.auditoria_diaria.empresa_id', 1);

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? (float) $toleranciaOpt
            : (float) config('rendicion_estacionamiento_anita.auditoria_diaria.tolerancia', 0.02);

        $fechasOpt = $this->option('fecha');
        $fechas = is_array($fechasOpt) && $fechasOpt !== []
            ? array_values(array_filter(array_map('trim', $fechasOpt)))
            : [Carbon::yesterday()->toDateString()];

        $pvFiltro = $this->option('puntoventa');
        $pvFiltro = is_string($pvFiltro) && trim($pvFiltro) !== '' ? trim($pvFiltro) : null;
        $mostrarDetalle = (bool) $this->option('detalle');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresa %d | tolerancia %.2f | fechas: %s',
            $empresaId,
            $tolerancia,
            implode(', ', $fechas),
        ));

        $hayProblemas = false;

        foreach ($fechas as $fecha) {
            try {
                $informe = $service->auditarFechaJornada($empresaId, $fecha, $tolerancia, $pvFiltro);
            } catch (\Throwable $e) {
                $this->error($fecha.': '.$e->getMessage());
                Log::error('rendicion_estacionamiento.auditoria_anita.fallo', [
                    'fecha' => $fecha,
                    'empresa_id' => $empresaId,
                    'exception' => $e,
                ]);
                $hayProblemas = true;

                continue;
            }

            $this->newLine();
            $this->info('Fecha jornada '.$fecha);
            $conteo = $informe['resumen']['conteo'] ?? [];
            $this->table(
                ['Estado', 'Cantidad'],
                [
                    ['OK', (string) ($conteo['ok'] ?? 0)],
                    ['Diferencia Z/NC', (string) ($conteo['diferencia'] ?? 0)],
                    ['Sin rendgastro (con ventas ERP)', (string) ($conteo['sin_anita'] ?? 0)],
                    ['Sin ventas ni rendgastro', (string) ($conteo['sin_ventas_erp'] ?? 0)],
                ],
            );

            $filasTabla = [];
            foreach ($informe['filas'] as $fila) {
                if (($fila['estado'] ?? '') === 'sin_ventas_erp') {
                    continue;
                }
                $filasTabla[] = [
                    $fila['puntoventa'],
                    $fila['estado'],
                    $fila['cantidad_facturas_erp'] ?? 0,
                    $fila['cantidad_nc_erp'] ?? 0,
                    $this->fmt($fila['erp_z'] ?? null),
                    $this->fmt($fila['anita_z'] ?? null),
                    $this->fmtDiff($fila['diff_z'] ?? null),
                    $this->fmt($fila['erp_nc'] ?? null),
                    $this->fmt($fila['anita_nc'] ?? null),
                    $this->fmtDiff($fila['diff_nc'] ?? null),
                ];

                if ($mostrarDetalle && in_array($fila['estado'] ?? '', ['diferencia', 'sin_anita'], true)) {
                    $this->comment('PV '.$fila['puntoventa'].' — '.($fila['mensaje'] ?? ''));
                    if (! empty($fila['detalle'])) {
                        $this->table(
                            ['nro_oper', 'turno', 'hora', 'Z Anita', 'NC Anita', 'portadora'],
                            array_map(fn (array $d) => [
                                $d['nro_oper'],
                                $d['turno'],
                                $d['hora'],
                                $this->fmt($d['z']),
                                $this->fmt($d['tot_nc']),
                                ! empty($d['portadora']) ? 'sí' : 'no',
                            ], $fila['detalle']),
                        );
                    }
                    if (! empty($fila['cabeceras_huerfanas'])) {
                        foreach ($fila['cabeceras_huerfanas'] as $msg) {
                            $this->warn($msg);
                        }
                    }
                }
            }

            if ($filasTabla !== []) {
                $this->table(
                    ['PV', 'Estado', 'Fac ERP', 'NC ERP', 'Z ERP', 'Z Anita', 'Δ Z', 'NC ERP', 'NC Anita', 'Δ NC'],
                    $filasTabla,
                );
            } else {
                $this->comment('Sin puntos de venta con actividad en esta fecha.');
            }

            if (! empty($informe['resumen']['requiere_alerta'])) {
                $hayProblemas = true;
                $jornada = $service->resolverJornada($empresaId, $fecha);
                if ($jornada !== null) {
                    $this->line('Reparación sugerida: php artisan rendicion-estacionamiento:reparar-jornada-anita --jornada='.$jornada->id.' --dry-run');
                } else {
                    $this->line('Reparación sugerida: php artisan rendicion-estacionamiento:reparar-jornada-anita --fecha='.$fecha.' --empresa='.$empresaId.' --dry-run');
                }
            } else {
                $this->info('Consistente para '.$fecha.'.');
            }

            Log::info('rendicion_estacionamiento.auditoria_anita', [
                'fecha_jornada' => $fecha,
                'empresa_id' => $empresaId,
                'resumen' => $informe['resumen'],
            ]);
        }

        return $hayProblemas ? self::FAILURE : self::SUCCESS;
    }

    private function fmt(mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format((float) $valor, 2, '.', '');
    }

    private function fmtDiff(mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        $n = (float) $valor;
        $s = number_format($n, 2, '.', '');

        return $n > 0 ? '+'.$s : $s;
    }
}
