<?php

namespace App\Console\Commands;

use App\Models\Configuracion\Empresa;
use App\Models\Seguridad\Usuario;
use App\Services\Ventas\Gastronomia\GastronomiaReplicarVentasAnitaErpService;
use App\Support\Ventas\Gastronomia\GastronomiaAnitaMesCacheSupport;
use App\Support\Ventas\GastronomiaAnitaImportEmpresaSupport;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

class GastronomiaExportarVentasAnitaUnl extends Command
{
    protected $signature = 'gastronomia:exportar-ventas-anita-unl
                            {--empresa=2 : empresa_id ERP}
                            {--fecha-desde= : Y-m-d (default: primer día del mes en curso)}
                            {--fecha-hasta= : Y-m-d (default: último día del mes en curso)}
                            {--puntoventa= : Código PV opcional}
                            {--output-dir= : Directorio destino (default storage/app/anita_unl/empresa_ID_AAAAmm)}
                            {--usuario= : usuario_id para ven_usuario (default primer usuario)}
                            {--usar-cache-anita : Compara contra cache bulk (venta/vengrav con ven_empresa) sin bridge por factura}
                            {--descargar-cache : Descarga cache Anita del rango antes de exportar}';

    protected $description = 'Exporta venta.unl, vengrav.unl y vencae.unl (pipe) para ventas gastronomía ERP sin cabecera en Informix';

    public function handle(
        GastronomiaReplicarVentasAnitaErpService $service,
        GastronomiaAnitaMesCacheSupport $cacheSupport,
    ): int {
        $usuarioId = (int) ($this->option('usuario') ?: Usuario::query()->orderBy('id')->value('id') ?? 1);
        if ($usuarioId <= 0 || ! Auth::loginUsingId($usuarioId)) {
            $this->error('Usuario inválido.');

            return self::FAILURE;
        }

        $empresaId = (int) $this->option('empresa');
        $empresa = Empresa::query()->find($empresaId);
        if (! $empresa) {
            $this->error("Empresa {$empresaId} inexistente.");

            return self::FAILURE;
        }

        $hoy = Carbon::now();
        $fechaDesde = trim((string) ($this->option('fecha-desde') ?: $hoy->copy()->startOfMonth()->format('Y-m-d')));
        $fechaHasta = trim((string) ($this->option('fecha-hasta') ?: $hoy->copy()->endOfMonth()->format('Y-m-d')));
        $pv = $this->option('puntoventa');
        $pv = is_string($pv) && trim($pv) !== '' ? trim($pv) : null;

        $outputDir = trim((string) ($this->option('output-dir') ?? ''));
        if ($outputDir === '') {
            $outputDir = storage_path('app/anita_unl/empresa_'.$empresaId.'_'.$hoy->format('Ym'));
        } elseif (! str_starts_with($outputDir, '/')) {
            $outputDir = base_path($outputDir);
        }

        $this->line(sprintf(
            'Empresa %d (%s, ven_empresa=%s) | jornada %s → %s | PV %s',
            $empresaId,
            $empresa->nombre ?? '',
            GastronomiaAnitaImportEmpresaSupport::codigoEmpresa($empresaId),
            $fechaDesde,
            $fechaHasta,
            $pv ?? 'todos',
        ));
        $this->line('Salida: '.$outputDir);

        $cacheAnita = null;
        if ($this->option('usar-cache-anita') || $this->option('descargar-cache')) {
            $forzar = (bool) $this->option('descargar-cache');
            $this->line('Cache Anita export PK (tipo/letra/sucursal/nro, ven_empresa='.GastronomiaAnitaImportEmpresaSupport::codigoEmpresa($empresaId).')...');
            $cacheAnita = $cacheSupport->descargarParaExportUnl($empresaId, $fechaDesde, $fechaHasta, $forzar);
            $manifest = $cacheAnita['manifest'];
            $this->line(sprintf(
                'Cache PK: %d venta, %d vengrav, %d vencae (%s)',
                count($cacheAnita['venta']),
                count($cacheAnita['vengrav']),
                count($cacheAnita['vencae']),
                $manifest['directorio'] ?? $cacheSupport->directorioCacheExportPk($empresaId, $fechaDesde, $fechaHasta),
            ));
        }

        try {
            $resultado = $service->exportarFaltantesUnl(
                $fechaDesde,
                $fechaHasta,
                $empresaId,
                $outputDir,
                $pv,
                $cacheAnita,
            );
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Archivos generados');
        $this->table(
            ['Archivo', 'Líneas', 'Ruta'],
            [
                ['venta.unl', (string) $resultado['venta_lineas'], $resultado['archivos']['venta']],
                ['vengrav.unl', (string) $resultado['vengrav_lineas'], $resultado['archivos']['vengrav']],
                ['vencae.unl', (string) $resultado['vencae_lineas'], $resultado['archivos']['vencae']],
            ],
        );

        $this->table(
            ['Concepto', 'Valor'],
            [
                ['Faltantes detectados', (string) ($resultado['faltantes'] ?? 0)],
                ['Sin fila vencae (sin CAE en ERP)', (string) ($resultado['omitidas_sin_cae'] ?? 0)],
                ['Ventas omitidas (cabecera ya en Informix)', (string) ($resultado['omitidas_venta_ya_existe'] ?? 0)],
                ['Líneas vengrav omitidas (ya en Informix)', (string) ($resultado['omitidas_vengrav_ya_existe'] ?? 0)],
                ['Filas vencae omitidas (ya en Informix)', (string) ($resultado['omitidas_vencae_ya_existe'] ?? 0)],
                ['Errores al armar filas', (string) count($resultado['errores'] ?? [])],
            ],
        );

        if (($resultado['errores'] ?? []) !== []) {
            $this->warn('Errores (primeras 20 filas)');
            $this->table(
                ['venta_id', 'codigo', 'mensaje'],
                array_map(static fn (array $f) => [
                    $f['venta_id'] ?? '',
                    $f['codigo'] ?? '',
                    $f['mensaje'] ?? '',
                ], array_slice($resultado['errores'], 0, 20)),
            );

            return self::FAILURE;
        }

        if (($resultado['venta_lineas'] ?? 0) === 0) {
            $this->comment('No hay ventas ERP sin cabecera en Informix en el rango indicado.');

            return self::SUCCESS;
        }

        $this->info('Listo. Subí los .unl al servidor Informix y ejecutá LOAD FROM en orden: venta → vengrav → vencae.');

        return self::SUCCESS;
    }
}
