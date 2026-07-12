<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaConciliacionDiariaReporteService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class GastronomiaConciliacionDiariaReporte extends Command
{
    protected $signature = 'gastronomia:conciliacion-diaria-reporte
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (obligatorio salvo corrida nocturna implícita)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: fecha-desde)}
                            {--empresas= : IDs empresa separados por coma (default: config)}
                            {--tolerancia= : Tolerancia en pesos (default: config)}
                            {--csv= : Ruta opcional para exportar CSV}
                            {--enviar-mail : Envía el CSV por correo}
                            {--sin-mail : No envía correo (solo consola/CSV)}
                            {--requiere-jornada-cerrada : Solo empresas con jornada cerrada en el rango (schedule diario)}
                            {--forzar-cache-anita : Alias explícito de refresco (default: sí)}
                            {--reutilizar-cache-anita : No re-descargar; usa cache local si existe}
                            {--sin-cache-anita : Consulta Anita en vivo por PV (menos confiable bajo carga)}';

    protected $description = 'Auditoría día a día: ERP/Anita/rendgastro por PC, PV (CAE/CAEA), post-cierre y total general';

    public function handle(GastronomiaConciliacionDiariaReporteService $service): int
    {
        @ini_set('memory_limit', '2048M');
        @set_time_limit(0);

        $config = config('gastronomia.conciliacion_diaria_reporte', []);

        $fechaDesde = trim((string) ($this->option('fecha-desde') ?? ''));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?? ''));
        if ($fechaDesde === '') {
            $fechaDesde = Carbon::yesterday()->toDateString();
        }
        $fechaHasta = $fechaHasta !== '' ? $fechaHasta : $fechaDesde;

        $empresasOpt = trim((string) ($this->option('empresas') ?? ''));
        $empresas = $empresasOpt !== ''
            ? array_values(array_filter(array_map('intval', array_map('trim', explode(',', $empresasOpt)))))
            : array_values(array_filter(array_map('intval', $config['empresas_ids'] ?? [1, 2, 3])));
        if ($empresas === []) {
            $this->error('Indique al menos una empresa en --empresas o config');

            return self::FAILURE;
        }

        if ((bool) $this->option('requiere-jornada-cerrada')) {
            $empresasAntes = $empresas;
            $empresas = $service->filtrarEmpresasJornadaCerrada($empresas, $fechaDesde, $fechaHasta);
            $omitidas = array_values(array_diff($empresasAntes, $empresas));
            if ($omitidas !== []) {
                $this->comment(sprintf(
                    'Omitidas (jornada no cerrada en %s → %s): %s',
                    $fechaDesde,
                    $fechaHasta,
                    implode(', ', $omitidas),
                ));
            }
            if ($empresas === []) {
                $this->comment('Ninguna empresa con jornada cerrada en el rango; no se genera reporte ni mail.');

                return self::SUCCESS;
            }
        }

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? max(0.0, (float) $toleranciaOpt)
            : max(0.0, (float) ($config['tolerancia'] ?? 0.02));

        $enviarMail = (bool) $this->option('enviar-mail');
        if ($enviarMail && (bool) $this->option('sin-mail')) {
            $this->error('Use --enviar-mail o --sin-mail, no ambos');

            return self::FAILURE;
        }

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Auditoría conciliación | empresas %s | jornada %s → %s | tolerancia %.2f',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
            $tolerancia,
        ));

        Log::info('gastronomia.conciliacion_diaria_reporte.inicio', [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'empresas' => $empresas,
            'enviar_mail' => $enviarMail,
        ]);

        $this->comment('Detalle: PV CAE + PV CAEA (salón) → total PC vs rendg Z → post-cierre → control día empresa.');

        $usarCacheAnita = (bool) $this->option('sin-cache-anita') ? false : null;
        $reutilizarCacheAnita = (bool) $this->option('reutilizar-cache-anita');
        $refrescarCacheConfig = (bool) ($config['refrescar_cache_anita'] ?? true);
        $forzarCacheAnita = ! $reutilizarCacheAnita && ($refrescarCacheConfig || (bool) $this->option('forzar-cache-anita'));
        if ($usarCacheAnita !== false) {
            $this->comment($forzarCacheAnita
                ? 'Anita: cache bulk con refresco al inicio (1 descarga por empresa/rango). --reutilizar-cache-anita omite bridge.'
                : 'Anita: cache bulk existente (--reutilizar-cache-anita). Use --sin-cache-anita para consulta live.');
        } else {
            $this->warn('Anita: consulta live por PV (puede marcar DIF falsos si el bridge responde vacío).');
        }

        try {
            $informe = $service->generar(
                $fechaDesde,
                $fechaHasta,
                $empresas,
                $tolerancia,
                $forzarCacheAnita,
                $usarCacheAnita,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $csvFilas = $service->construirFilasCsv($informe);
        $hayDiferencias = $service->hayDiferencias($informe);

        foreach ($informe['empresas'] as $empresa) {
            $this->newLine();
            $this->info(sprintf(
                'Empresa %d — %s',
                $empresa['empresa_id'],
                $empresa['empresa_nombre'],
            ));
            $cacheInfo = $empresa['anita_cache'] ?? null;
            if (is_array($cacheInfo)) {
                $this->comment(sprintf(
                    '  Cache Anita: %d cabeceras | generado %s',
                    (int) (($cacheInfo['counts']['venta'] ?? 0)),
                    (string) ($cacheInfo['generado_at'] ?? '—'),
                ));
            }

            foreach ($empresa['dias'] as $dia) {
                if (($dia['filas'] ?? []) === []) {
                    continue;
                }

                $this->newLine();
                $this->comment('Jornada '.$dia['fecha_jornada']);

                $pcActual = null;
                foreach ($dia['filas'] as $fila) {
                    $tipo = (string) ($fila['tipo_fila'] ?? '');

                    if ($tipo === 'pv_cae' || $tipo === 'pv_caea') {
                        if ($pcActual !== ($fila['identificador_pc'] ?? null)) {
                            $pcActual = $fila['identificador_pc'] ?? null;
                            $this->line('');
                            $this->line('  PC <info>'.$pcActual.'</info> (PV CAE '.($fila['pv_cae'] ?? '').' / CAEA '.($fila['pv_caea'] ?? '').')');
                        }
                        $this->line(sprintf(
                            '    PV %s %s: ERP $ %s | Anita $ %s | %d fc | %s',
                            $fila['pv_codigo'] ?? '—',
                            $fila['tipo_pv'] ?? '',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            (int) ($fila['cantidad_facturas_erp'] ?? 0),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'pc_total') {
                        $this->line(sprintf(
                            '    → Total PC: ERP neto $ %s (CAE $ %s + CAEA $ %s) | Anita $ %s | Rendg neto $ %s | Δ rendg $ %s | %s',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_erp_cae'] ?? 0),
                            $this->fmt($fila['ventas_erp_caea'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'total_salon' || $tipo === 'total_gastro' || $tipo === 'total_dia') {
                        $this->line('');
                        $etiqueta = match ($tipo) {
                            'total_gastro' => '<comment>TOTAL GASTRO</comment> (salón + post-cierre + agregados CAEA)',
                            'total_dia' => '<comment>TOTAL DÍA</comment> (legacy)',
                            default => '<comment>TOTAL SALÓN</comment> (PCs, sin post-cierre)',
                        };
                        $this->line(sprintf(
                            '  %s: ERP neto $ %s | Anita $ %s | Rendg neto $ %s | Δ $ %s | %s',
                            $etiqueta,
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'post_cierre_caea') {
                        $this->line(sprintf(
                            '  Post-cierre CAEA (PV %s): ERP $ %s | Anita $ %s | Rendg CIERRE-WAITRY $ %s | %d fc | %s',
                            $fila['pv_caea'] ?? '—',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            (int) ($fila['cantidad_facturas_erp'] ?? 0),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'caea_agregados_migrados') {
                        $this->line(sprintf(
                            '  Agregados CAEA migrados (PV %s): ERP $ %s | Anita $ %s | Rendg %s $ %s | %d fc | %s',
                            $fila['pv_caea'] ?? '—',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $fila['identificador_pc'] ?? '—',
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            (int) ($fila['cantidad_facturas_erp'] ?? 0),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'vending_pv' || $tipo === 'vending_rendg') {
                        $this->line(sprintf(
                            '  Vending (PV %s): ERP $ %s | Rendg Z $ %s | Δ $ %s | %s',
                            $fila['pv_codigo'] ?? '—',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'estacionamiento_pv') {
                        $this->line(sprintf(
                            '  Estacionamiento (PV %s): ERP neto $ %s | Rendg neto $ %s | Δ $ %s | %s',
                            $fila['pv_codigo'] ?? '—',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['rendgastro_neto'] ?? $fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'total_estacionamiento') {
                        $this->line(sprintf(
                            '  <comment>TOTAL ESTACIONAMIENTO</comment>: ERP $ %s | Rendg $ %s | Δ $ %s | %s',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                        continue;
                    }

                    if ($tipo === 'total_vending') {
                        $this->line(sprintf(
                            '  <comment>TOTAL VENDING</comment>: ERP $ %s | Rendg Z $ %s | Δ $ %s | %s',
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['rendgastro_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                            $fila['estado'] ?? '—',
                        ));
                    }
                }

                $ctrl = $dia['control_gastro_total'] ?? null;
                if (is_array($ctrl)) {
                    $lineaCtrl = sprintf(
                        '  <comment>CONTROL DÍA EMPRESA</comment>: ERP neto $ %s (bruto $ %s − NC $ %s) | rendg neto $ %s (Z $ %s − NC $ %s) | Δ $ %s | %s',
                        $this->fmt($ctrl['ventas_erp'] ?? 0),
                        $this->fmt($ctrl['ventas_erp_bruto'] ?? 0),
                        $this->fmt($ctrl['notas_credito_erp'] ?? 0),
                        $this->fmt($ctrl['rendgastro_neto'] ?? null),
                        $this->fmt($ctrl['rendgastro_z'] ?? null),
                        $this->fmt($ctrl['notas_credito_rendg'] ?? null),
                        $this->fmtDiff($ctrl['diff_erp_rendg'] ?? null),
                        $ctrl['estado'] ?? '—',
                    );
                    if (($ctrl['rendg_legacy_z'] ?? null) !== null && (float) ($ctrl['rendg_legacy_z'] ?? 0) > 0.02) {
                        $lineaCtrl .= sprintf(' | <error>legacy Z $ %s</error>', $this->fmt($ctrl['rendg_legacy_z']));
                    }
                    if (($ctrl['fc_caea_duplicado'] ?? null) !== null && (float) ($ctrl['fc_caea_duplicado'] ?? 0) > 0.02) {
                        $lineaCtrl .= sprintf(' | <error>fc_caea dup $ %s</error>', $this->fmt($ctrl['fc_caea_duplicado']));
                    }
                    $this->line($lineaCtrl);
                }

                $ctrlAsientos = $dia['control_rendg_asientos'] ?? null;
                if (is_array($ctrlAsientos)) {
                    $totemAs = (float) ($ctrlAsientos['asientos_totem'] ?? 0);
                    $totemPuenteAs = (float) ($ctrlAsientos['asientos_totem_puente'] ?? 0);
                    $this->line(sprintf(
                        '  <comment>CONTROL RENDG ↔ ASIENTOS</comment>: rendg $ %s (salón neto $ %s + post-cierre $ %s%s) | asientos $ %s (factura día $ %s + post-cierre $ %s%s%s) | Δ $ %s | %s',
                        $this->fmt($ctrlAsientos['rendg_total'] ?? null),
                        $this->fmt($ctrlAsientos['rendg_salon'] ?? null),
                        $this->fmt($ctrlAsientos['rendg_post_cierre'] ?? null),
                        ($ctrlAsientos['rendg_agregados_caea'] ?? null) !== null
                            ? ' + agregados $ '.$this->fmt($ctrlAsientos['rendg_agregados_caea'])
                            : '',
                        $this->fmt($ctrlAsientos['asientos_total'] ?? null),
                        $this->fmt($ctrlAsientos['asientos_factura_dia'] ?? null),
                        $this->fmt($ctrlAsientos['asientos_post_cierre'] ?? null),
                        ($ctrlAsientos['asientos_agregados_caea'] ?? null) !== null && (float) ($ctrlAsientos['asientos_agregados_caea'] ?? 0) > 0.02
                            ? ' + agregados $ '.$this->fmt($ctrlAsientos['asientos_agregados_caea'])
                            : '',
                        $totemAs > 0.02
                            ? ' + TOTEM $ '.$this->fmt($totemAs).($totemPuenteAs > 0.02 ? ' (puente $ '.$this->fmt($totemPuenteAs).')' : '')
                            : '',
                        $this->fmtDiff($ctrlAsientos['diff_rendg_asientos'] ?? null),
                        $ctrlAsientos['estado'] ?? '—',
                    ));
                }

                foreach ($service->filasControlFlashDesdeDia($dia) as $ctrlFlash) {
                    $segmento = (string) ($ctrlFlash['segmento_flash'] ?? '');
                    $etiqueta = match ($segmento) {
                        'gastro' => 'CONTROL FLASH GASTRO (AyB)',
                        'estacionamiento' => 'CONTROL FLASH ESTAC.',
                        default => 'CONTROL FLASH (caja)',
                    };
                    $this->line(sprintf(
                        '  <comment>%s</comment>: ERP $ %s | rendg $ %s | flash $ %s | Δ rendg↔flash $ %s | Δ ERP↔flash $ %s | %s',
                        $etiqueta,
                        $this->fmt($ctrlFlash['ventas_erp'] ?? 0),
                        $this->fmt($ctrlFlash['rendgastro_neto'] ?? null),
                        $this->fmt($ctrlFlash['total_flash'] ?? 0),
                        $this->fmtDiff($ctrlFlash['diff_rendg_flash'] ?? null),
                        $this->fmtDiff($ctrlFlash['diff_erp_flash'] ?? null),
                        $ctrlFlash['estado'] ?? '—',
                    ));
                }

                $huecos = $dia['huecos_numeracion'] ?? null;
                if (is_array($huecos) && (int) ($huecos['huecos_corr_erp'] ?? 0) > 0) {
                    $this->warn(sprintf(
                        '  <comment>HUECOS NUMERACIÓN (jornada)</comment>: %d tramo(s) ERP | solo ERP %d | solo Anita %d',
                        (int) ($huecos['huecos_corr_erp'] ?? 0),
                        (int) ($huecos['solo_erp'] ?? 0),
                        (int) ($huecos['solo_anita'] ?? 0),
                    ));
                    foreach (array_slice($huecos['huecos'] ?? [], 0, 5) as $h) {
                        $this->line(sprintf(
                            '    PV %s: faltan %s (después de %s → %s)',
                            $h['pv_codigo'] ?? '—',
                            $h['faltantes'] ?? '',
                            $h['desde'] ?? '',
                            $h['hasta'] ?? '',
                        ));
                    }
                }
            }

            $huecosRango = $empresa['huecos_rango'] ?? null;
            if (is_array($huecosRango) && ($huecosRango['hay_huecos'] ?? false) === true) {
                $resHuecos = $huecosRango['resumen'] ?? [];
                $this->newLine();
                $this->warn(sprintf(
                    '  <comment>HUECOS RANGO %s → %s</comment>: ERP %d tramo(s) / %d faltantes en %d jornada(s)',
                    $informe['fecha_desde'] ?? '',
                    $informe['fecha_hasta'] ?? '',
                    (int) ($resHuecos['huecos_erp'] ?? 0),
                    (int) ($resHuecos['numeros_faltantes_erp'] ?? 0),
                    (int) ($resHuecos['jornadas_con_huecos_erp'] ?? 0),
                ));
            }
        }

        $csvPath = trim((string) ($this->option('csv') ?? ''));
        if ($csvPath !== '') {
            $service->guardarCsv($csvPath, $informe);
            $this->info('CSV: '.$csvPath);
        }

        if ($enviarMail) {
            $mail = $service->enviarCorreo($informe);
            if ($mail['enviado'] ?? false) {
                $this->info('Correo enviado a '.($mail['destino'] ?? ''));
            } else {
                $this->error('No se pudo enviar correo: '.($mail['error'] ?? 'error desconocido'));
                Log::error('gastronomia.conciliacion_diaria_reporte.fin_sin_mail', [
                    'fecha_desde' => $fechaDesde,
                    'fecha_hasta' => $fechaHasta,
                    'error' => $mail['error'] ?? null,
                ]);

                return self::FAILURE;
            }
        }

        Log::info('gastronomia.conciliacion_diaria_reporte.fin', [
            'fecha_desde' => $fechaDesde,
            'fecha_hasta' => $fechaHasta,
            'hay_diferencias' => $hayDiferencias,
            'hay_huecos_numeracion' => (bool) ($informe['hay_huecos_numeracion'] ?? false),
            'filas_csv' => count($csvFilas),
        ]);

        if ($csvFilas === []) {
            $this->comment('Sin actividad en el rango indicado.');

            return self::SUCCESS;
        }

        return ($hayDiferencias || (bool) ($informe['hay_huecos_numeracion'] ?? false)) ? self::FAILURE : self::SUCCESS;
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
        if ($valor === null || $valor === '') {
            return '—';
        }

        $n = (float) $valor;
        $s = number_format($n, 2, '.', '');

        return $n > 0 ? '+'.$s : $s;
    }
}
