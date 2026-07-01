<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Configuracion\Empresa;
use App\Services\Caja\RendicionGastronomiaAuditoriaAnitaNotificacionService;
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
                            {--detalle : Muestra cabeceras rendgastro por PV con diferencias}
                            {--sin-mail : No envía correo}';

    protected $description = 'Audita rendgastro neto (Z−NC) vs facturación ERP neta por PC (gastronomía) y por PV (estacionamiento)';

    public function handle(
        RendicionGastronomiaAuditoriaAnitaService $service,
        RendicionGastronomiaAuditoriaAnitaNotificacionService $notificacionService,
    ): int {
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
        $enviarMail = ! (bool) $this->option('sin-mail');

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Empresas %s | tolerancia %.2f | fechas: %s',
            implode(', ', $empresas),
            $tolerancia,
            implode(', ', $fechas),
        ));
        $this->comment('Criterio: neto = facturas − NC (ERP, rendg Z−NC, asientos ya netos). Incluye estacionamiento por PV.');

        $hayProblemas = false;

        foreach ($fechas as $fecha) {
            $informeMail = [
                'fecha_jornada' => $fecha,
                'tolerancia' => $tolerancia,
                'empresas' => [],
                'requiere_alerta' => false,
            ];

            foreach ($empresas as $empresaId) {
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

                $empresa = Empresa::query()->find($empresaId, ['id', 'nombre']);
                $informeMail['empresas'][] = [
                    'empresa_id' => $empresaId,
                    'empresa_nombre' => (string) ($empresa->nombre ?? ('Empresa '.$empresaId)),
                    'informe' => $informe,
                ];
                if (! empty($informe['resumen']['requiere_alerta'])) {
                    $informeMail['requiere_alerta'] = true;
                }

                $this->newLine();
                $this->info('Empresa '.$empresaId.' — fecha jornada '.$fecha);
                $conteo = $informe['resumen']['conteo'] ?? [];
                $this->table(
                    ['Estado', 'Cantidad'],
                    [
                        ['OK', (string) ($conteo['ok'] ?? 0)],
                        ['DIF venta (cabecera Anita)', (string) ($conteo['dif_venta'] ?? 0)],
                        ['DIF rendg (rendgastro neto)', (string) ($conteo['dif_rendg'] ?? 0)],
                        ['DIF ambos', (string) ($conteo['dif_ambos'] ?? 0)],
                        ['Sin rendgastro', (string) ($conteo['sin_rendg'] ?? 0)],
                    ],
                );

                $totalDia = $informe['total_dia'] ?? null;
                if ($totalDia !== null) {
                    $this->line(sprintf(
                        'Total día: ERP neto $ %s | Anita $ %s | Δ venta $ %s (%s) | rendg neto $ %s | Δ rendg $ %s (%s) | %s',
                        $this->fmt($totalDia['erp_z'] ?? null),
                        $this->fmt($totalDia['ventas_anita'] ?? null),
                        $this->fmtDiff($totalDia['diff_anita'] ?? null),
                        $totalDia['estado_anita'] ?? '—',
                        $this->fmt($totalDia['anita_z'] ?? null),
                        $this->fmtDiff($totalDia['diff_z'] ?? null),
                        $totalDia['estado_rendg'] ?? '—',
                        $totalDia['estado'] ?? '—',
                    ));
                }

                $filasTabla = [];
                foreach ($informe['filas'] as $fila) {
                    $filasTabla[] = [
                        $fila['tipo_fila'] ?? '—',
                        $fila['puntoventa'],
                        $fila['estado'],
                        $fila['estado_anita'] ?? '—',
                        $fila['estado_rendg'] ?? '—',
                        $fila['cantidad_facturas_erp'] ?? 0,
                        $this->fmt($fila['erp_z'] ?? null),
                        $this->fmt($fila['ventas_anita'] ?? null),
                        $this->fmtDiff($fila['diff_anita'] ?? null),
                        $this->fmt($fila['anita_z'] ?? null),
                        $this->fmtDiff($fila['diff_z'] ?? null),
                    ];
                }

                if ($filasTabla !== []) {
                    $this->table(
                        ['Tipo', 'Clave', 'Estado', 'Venta', 'Rendg', 'Fac', 'ERP neto', 'Anita venta', 'Δ venta', 'Rendg neto', 'Δ rendg'],
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

            if ($enviarMail && $informeMail['empresas'] !== []) {
                $mail = $notificacionService->enviarCorreo($informeMail);
                if ($mail['enviado'] ?? false) {
                    $this->info('Correo enviado a '.($mail['destino'] ?? ''));
                } elseif (($mail['error'] ?? '') !== 'Sin alertas y email_si_ok deshabilitado') {
                    $this->error('No se pudo enviar correo: '.($mail['error'] ?? 'error desconocido'));
                    $hayProblemas = true;
                }
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
