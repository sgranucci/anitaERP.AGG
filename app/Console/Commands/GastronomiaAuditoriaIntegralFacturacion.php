<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Services\Ventas\Gastronomia\GastronomiaFacturacionAuditoriaIntegralService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GastronomiaAuditoriaIntegralFacturacion extends Command
{
    protected $signature = 'gastronomia:auditoria-integral-facturacion
                            {--fecha-desde= : Fecha jornada inicial Y-m-d (default: ayer)}
                            {--fecha-hasta= : Fecha jornada final Y-m-d (default: fecha-desde)}
                            {--empresas= : IDs empresa separados por coma (default: config conciliación)}
                            {--puntoventa= : Código PV CAE opcional}
                            {--tolerancia= : Tolerancia en pesos (default: config)}
                            {--csv= : Ruta opcional para exportar CSV}';

    protected $description = 'Auditoría integral por día: ERP neto vs Anita vs rendgastro neto vs asientos vs ctamov';

    public function handle(GastronomiaFacturacionAuditoriaIntegralService $service): int
    {
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

        $toleranciaOpt = $this->option('tolerancia');
        $tolerancia = $toleranciaOpt !== null && $toleranciaOpt !== ''
            ? max(0.0, (float) $toleranciaOpt)
            : max(0.0, (float) ($config['tolerancia'] ?? 0.02));

        $pvFiltro = $this->option('puntoventa');
        $pvFiltro = is_string($pvFiltro) && trim($pvFiltro) !== '' ? trim($pvFiltro) : null;

        $this->line('Bridge: '.ApiAnita::urlBridge());
        $this->line(sprintf(
            'Auditoría integral | empresas %s | jornada %s → %s | tolerancia %.2f',
            implode(', ', $empresas),
            $fechaDesde,
            $fechaHasta,
            $tolerancia,
        ));
        $this->comment('Rendgastro: neto por PC (Z−NC) + post-cierre | Contabilidad: cierre Waitry ↔ ctamov (neto)');
        $this->comment('Criterio único: neto = facturas − NC en ERP, rendg y asientos.');

        try {
            $informe = $service->generar($fechaDesde, $fechaHasta, $empresas, $tolerancia, $pvFiltro);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $modoContable = ! \App\Support\Ventas\Gastronomia\GastronomiaFacturacionAuditoriaCtamovSupport::contabilidadPorFacturaHabilitada()
            ? 'cierre agrupado (sin asiento por factura)'
            : 'por factura';
        $this->line('Modo contable gastronomía: '.$modoContable);

        $hayDiferencias = $service->hayDiferencias($informe);
        $hayHuecos = (bool) ($informe['hay_huecos_numeracion'] ?? false);
        $filasTabla = [];

        foreach ($informe['empresas'] as $empresa) {
            $this->newLine();
            $this->info(sprintf('Empresa %d — %s', $empresa['empresa_id'], $empresa['empresa_nombre']));

            foreach ($empresa['dias'] as $dia) {
                if (($dia['filas'] ?? []) === []) {
                    continue;
                }

                $this->comment('Jornada '.$dia['fecha_jornada'].($dia['jornada_cerrada'] ? '' : ' (jornada abierta)'));

                $montosCabecera = $dia['montos_cabecera'] ?? [];
                if (($montosCabecera['estado'] ?? '') === 'DIF') {
                    $this->warn(sprintf(
                        '  Cabeceras Anita: %d dif importes (total/gravado/IVA/exento) | %d solo ERP | Δ gravado $ %s',
                        (int) ($montosCabecera['conteo']['diferencia'] ?? 0),
                        (int) ($montosCabecera['conteo']['solo_erp'] ?? 0),
                        $this->fmt($montosCabecera['delta_totales']['gravado'] ?? 0),
                    ));
                }

                foreach ($dia['filas'] as $fila) {
                    $tipo = (string) ($fila['tipo_fila'] ?? 'pc');
                    if ($tipo === 'contable_empresa') {
                        $filasTabla[] = [
                            $empresa['empresa_id'],
                            $dia['fecha_jornada'],
                            'CIERRE',
                            (int) ($fila['asientos_cierre'] ?? 0),
                            '—',
                            '—',
                            '—',
                            $this->fmt($fila['asientos_erp'] ?? 0),
                            $this->fmt($fila['ctamov_anita'] ?? 0),
                            $fila['estado_contable'] ?? '—',
                            $this->fmtDiff($fila['diff_asiento_ctamov'] ?? null),
                            $fila['estado'] ?? '—',
                        ];
                        if (($fila['estado'] ?? '') !== 'OK') {
                            $this->warn(sprintf(
                                '  Cierre Waitry: asientos ERP $ %s | ctamov $ %s | dif %d | sin ctamov %d',
                                $this->fmt($fila['asientos_erp'] ?? 0),
                                $this->fmt($fila['ctamov_anita'] ?? 0),
                                (int) ($fila['ctamov_dif'] ?? 0),
                                (int) ($fila['sin_ctamov'] ?? 0),
                            ));
                        } else {
                            $this->line(sprintf(
                                '  Cierre Waitry: %d asientos | ERP $ %s | ctamov $ %s | OK',
                                (int) ($fila['asientos_cierre'] ?? 0),
                                $this->fmt($fila['asientos_erp'] ?? 0),
                                $this->fmt($fila['ctamov_anita'] ?? 0),
                            ));
                        }
                        continue;
                    }

                    $filasTabla[] = [
                        $empresa['empresa_id'],
                        $dia['fecha_jornada'],
                        $fila['puntoventa'] ?? '—',
                        (int) ($fila['cant_facturas'] ?? 0),
                        $this->fmt($fila['ventas_erp'] ?? 0),
                        $this->fmt($fila['ventas_anita'] ?? 0),
                        $this->fmt($fila['rendg_z'] ?? null),
                        '—',
                        '—',
                        $fila['estado_contable'] ?? '—',
                        $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                        ($fila['estado_operativo'] ?? $fila['estado'] ?? '—'),
                    ];

                    if (($fila['estado_operativo'] ?? '') === 'DIF') {
                        $this->warn(sprintf(
                            '  %s [%s]: ERP $ %s | Anita $ %s | rendg $ %s | Δ Anita $ %s | Δ rendg $ %s',
                            $fila['puntoventa'] ?? '—',
                            $tipo,
                            $this->fmt($fila['ventas_erp'] ?? 0),
                            $this->fmt($fila['ventas_anita'] ?? 0),
                            $this->fmt($fila['rendg_z'] ?? null),
                            $this->fmtDiff($fila['diff_erp_anita'] ?? 0),
                            $this->fmtDiff($fila['diff_erp_rendg'] ?? null),
                        ));
                    }
                }

                $huecos = $dia['huecos_numeracion'] ?? null;
                if (is_array($huecos) && (int) ($huecos['huecos_corr_erp'] ?? 0) > 0) {
                    $this->warn(sprintf(
                        '  Huecos numeración jornada: %d tramo(s) ERP',
                        (int) ($huecos['huecos_corr_erp'] ?? 0),
                    ));
                }
            }

            $huecosRango = $empresa['huecos_rango'] ?? null;
            if (is_array($huecosRango) && ($huecosRango['hay_huecos'] ?? false) === true) {
                $resHuecos = $huecosRango['resumen'] ?? [];
                $this->warn(sprintf(
                    '  Huecos rango: ERP %d tramo(s) / %d faltantes en %d jornada(s)',
                    (int) ($resHuecos['huecos_erp'] ?? 0),
                    (int) ($resHuecos['numeros_faltantes_erp'] ?? 0),
                    (int) ($resHuecos['jornadas_con_huecos_erp'] ?? 0),
                ));
            }
        }

        if ($filasTabla !== []) {
            $this->newLine();
            $this->table(
                ['Emp', 'Jornada', 'Clave', 'Fac', 'ERP neto', 'Anita', 'Rendg neto', 'Asiento ERP', 'ctamov', 'Contable', 'Δ rendg', 'Estado'],
                $filasTabla,
            );
        } else {
            $this->comment('Sin actividad en el rango indicado.');
        }

        $csvPath = trim((string) ($this->option('csv') ?? ''));
        if ($csvPath !== '') {
            $service->guardarCsv($csvPath, $informe);
            $this->info('CSV: '.$csvPath);
        }

        return ($hayDiferencias || $hayHuecos) ? self::FAILURE : self::SUCCESS;
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

    private function fmt(mixed $valor): string
    {
        if ($valor === null) {
            return '—';
        }

        return number_format((float) $valor, 2, '.', '');
    }
}
