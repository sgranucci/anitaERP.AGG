<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Caja\RendicionGastronomiaAuditoriaAnitaService;
use App\Support\Caja\RendicionGastronomiaAuditoriaEmpresasSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class RendicionGastronomiaAuditoriaAnita extends Command
{
    protected $signature = 'rendicion-gastronomia:auditoria-anita
                            {--fecha=* : Una o más fechas de jornada Y-m-d (default: ayer)}
                            {--empresa= : empresa_id (default: config rendicion_gastronomia_anita.auditoria_diaria.empresa_id)}
                            {--puntoventa= : Código PV CAE opcional}
                            {--tolerancia= : Override tolerancia en pesos (default config)}
                            {--detalle : Muestra cabeceras rendgastro por PV con diferencias}';

    protected $description = 'Audita rendg_total_z (rendgastro) vs facturación ERP por PC (CAE+CAEA) y total día';

    public function handle(RendicionGastronomiaAuditoriaAnitaService $service): int
    {
        if (! config('rendicion_gastronomia_anita.sincronizar', true)) {
            $this->warn('RENDICION_GASTRONOMIA_SINCRONIZAR_ANITA está deshabilitado; no hay bridge activo.');

            return self::SUCCESS;
        }

        $empresaOverride = $this->option('empresa') !== null
            ? (int) $this->option('empresa')
            : null;
        $empresas = RendicionGastronomiaAuditoriaEmpresasSupport::empresasParaAuditoria($empresaOverride);

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? (float) $toleranciaOpt
            : (float) config('rendicion_gastronomia_anita.auditoria_diaria.tolerancia', 0.02);

        $fechasOpt = $this->option('fecha');
        $fechas = is_array($fechasOpt) && $fechasOpt !== []
            ? array_values(array_filter(array_map('trim', $fechasOpt)))
            : [Carbon::yesterday()->toDateString()];

        $pvFiltro = $this->option('puntoventa');
        $pvFiltro = is_string($pvFiltro) && trim($pvFiltro) !== '' ? trim($pvFiltro) : null;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresas %s | tolerancia %.2f | fechas: %s',
            implode(', ', $empresas),
            $tolerancia,
            implode(', ', $fechas),
        ));

        $hayProblemas = false;

        foreach ($empresas as $empresaId) {
            foreach ($fechas as $fecha) {
                try {
                    $informe = $service->auditarFechaJornada($empresaId, $fecha, $tolerancia, $pvFiltro);
                } catch (\Throwable $e) {
                    $this->error('Empresa '.$empresaId.' '.$fecha.': '.$e->getMessage());
                    Log::error('rendicion_gastronomia.auditoria_anita.fallo', [
                        'fecha' => $fecha,
                        'empresa_id' => $empresaId,
                        'exception' => $e,
                    ]);
                    $hayProblemas = true;

                    continue;
                }

                $this->newLine();
                $this->info('Empresa '.$empresaId.' — fecha jornada '.$fecha);
                $conteo = $informe['resumen']['conteo'] ?? [];
                $this->table(
                    ['Estado', 'Cantidad'],
                    [
                        ['OK', (string) ($conteo['ok'] ?? 0)],
                        ['Diferencia', (string) ($conteo['diferencia'] ?? 0)],
                        ['Sin rendgastro', (string) ($conteo['sin_anita'] ?? 0)],
                    ],
                );

                $totalDia = $informe['total_dia'] ?? null;
                if ($totalDia !== null) {
                    $this->line(sprintf(
                        'Total día: ERP $ %s | rendg $ %s | Δ $ %s | %s',
                        $this->fmt($totalDia['erp_z'] ?? null),
                        $this->fmt($totalDia['anita_z'] ?? null),
                        $this->fmtDiff($totalDia['diff_z'] ?? null),
                        $totalDia['estado'] ?? '—',
                    ));
                }

                $filasTabla = [];
                foreach ($informe['filas'] as $fila) {
                    $filasTabla[] = [
                        $fila['tipo_fila'] ?? '—',
                        $fila['puntoventa'],
                        $fila['estado'],
                        $fila['cantidad_facturas_erp'] ?? 0,
                        $this->fmt($fila['erp_cae'] ?? null),
                        $this->fmt($fila['erp_caea'] ?? null),
                        $this->fmt($fila['erp_z'] ?? null),
                        $this->fmt($fila['anita_z'] ?? null),
                        $this->fmtDiff($fila['diff_z'] ?? null),
                    ];
                }

                if ($filasTabla !== []) {
                    $this->table(
                        ['Tipo', 'Clave', 'Estado', 'Fac', 'ERP CAE', 'ERP CAEA', 'ERP total', 'Rendg Z', 'Δ Z'],
                        $filasTabla,
                    );
                } else {
                    $this->comment('Sin actividad en esta fecha.');
                }

                if (! empty($informe['resumen']['requiere_alerta'])) {
                    $hayProblemas = true;
                    $jornada = $service->resolverJornada($empresaId, $fecha);
                    if ($jornada !== null) {
                        $this->line('Reparación sugerida: php artisan rendicion-gastronomia:reparar-jornada-anita --jornada='.$jornada->id.' --dry-run');
                    } else {
                        $this->line('Reparación sugerida: php artisan rendicion-gastronomia:reparar-jornada-anita --fecha='.$fecha.' --empresa='.$empresaId.' --dry-run');
                    }
                } else {
                    $this->info('Consistente para '.$fecha.'.');
                }

                Log::info('rendicion_gastronomia.auditoria_anita', [
                    'fecha_jornada' => $fecha,
                    'empresa_id' => $empresaId,
                    'resumen' => $informe['resumen'],
                ]);
            }
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
