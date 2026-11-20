<?php

declare(strict_types=1);

namespace App\Services\Contable\CanonMunicipal;

use App\Models\Contable\Canon_Municipal_Config;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Contable\CanonMunicipal\CanonMunicipalCalendarioSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalDetallePlantillaSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalFichaSupport;
use App\Support\Contable\CanonMunicipal\CanonMunicipalListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\View;
use RuntimeException;

/**
 * Genera solicitud de pago desde el canon municipal (nota PDF adjunta, estado EMITIDA).
 */
final class CanonMunicipalSolicitudpagoService
{
    private const DIAS_DUPLICADO = 45;

    public function __construct(
        private readonly CanonMunicipalReporteService $reporteService,
        private readonly SolicitudpagoRepositoryInterface $solicitudpagoRepository,
    ) {
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    public function preview(array $filtros): array
    {
        $ctx = $this->resolverContexto($filtros);

        return $this->armarPreviewDto($ctx);
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array{solicitud: Solicitudpago, codigo: int}
     */
    public function confirmar(
        array $filtros,
        string $fechaVencimiento,
        ?string $observacion,
        bool $generarIgual,
        bool $confirmoDias,
    ): array {
        $ctx = $this->resolverContexto($filtros);
        $preview = $this->armarPreviewDto($ctx);

        if (! empty($preview['error'])) {
            throw new RuntimeException((string) $preview['error']);
        }

        if (! $ctx['cuadra']) {
            throw new RuntimeException('El cruce no cuadra.');
        }

        $vto = $this->normalizarFecha($fechaVencimiento);
        if ($vto === null) {
            throw new RuntimeException('El vencimiento es obligatorio.');
        }

        if (! empty($preview['duplicado']) && ! $generarIgual) {
            throw new RuntimeException('Hay una solicitud similar. Marque «Generar igual» para continuar.');
        }

        if (! empty($preview['dias_sin_venta']) && ! $confirmoDias) {
            throw new RuntimeException('Confirme que los días sin ventas son correctos.');
        }

        $nombrePdf = (string) $preview['nombre_pdf'];
        $rutaPdf = $this->generarPdfNota($ctx, $nombrePdf);

        try {
            $uploaded = new UploadedFile(
                $rutaPdf,
                $nombrePdf,
                'application/pdf',
                null,
                true
            );

            $empresaId = (int) $ctx['empresa_id'];
            $config = $ctx['config'];
            $monto = (float) $ctx['monto'];
            $ccId = (int) $config->centrocosto_id;
            $debeId = (int) $config->cuentacontable_debe_id;
            $haberId = (int) $config->cuentacontable_haber_id;

            $payload = [
                'empresa_id' => $empresaId,
                'fecha' => now()->toDateString(),
                'tratamiento' => SolicitudpagoTratamientos::NORMAL,
                'proveedor_id' => (int) $config->proveedor_id,
                'concepto_solicitudpago_id' => (int) $config->concepto_solicitudpago_id,
                'formapagosol_id' => (int) $config->formapagosol_id,
                'moneda_id' => (int) $config->moneda_id,
                'fecha_vencimiento' => $vto,
                'fecha_entrega' => now()->toDateString(),
                'monto' => $monto,
                'estado' => SolicitudpagoEstados::EMITIDA,
                'sector_solicitudpago_id' => (int) $config->sector_solicitudpago_id,
                'centrocosto_id' => $ccId,
                'detalle' => (string) $preview['detalle'],
                'observacion' => $observacion !== null && trim($observacion) !== ''
                    ? mb_substr(trim($observacion), 0, 160)
                    : null,
                'empresa_ids' => [$empresaId, $empresaId],
                'cuentacontable_ids' => [$debeId, $haberId],
                'centrocosto_ids' => [$ccId, $ccId],
                'debe_haberes' => ['D', 'H'],
                'montos_cuenta' => [$monto, $monto],
                'archivos_nuevos' => [$uploaded],
            ];

            $sp = $this->solicitudpagoRepository->create($payload);
            $codigo = (int) ($sp->codigo ?? 0);
            if ($codigo <= 0) {
                throw new RuntimeException('No se pudo generar la solicitud de pago.');
            }

            // Verificar que quedó el adjunto; si no, no dejar SP huérfana.
            $sp->load('archivos');
            if ($sp->archivos->isEmpty()) {
                try {
                    $this->solicitudpagoRepository->delete((int) $sp->id);
                } catch (\Throwable) {
                    // best-effort
                }
                throw new RuntimeException('La solicitud no quedó con la nota adjunta. No se generó.');
            }

            return [
                'solicitud' => $sp,
                'codigo' => $codigo,
            ];
        } finally {
            if (is_file($rutaPdf)) {
                @unlink($rutaPdf);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function resolverContexto(array $filtros): array
    {
        $empresaId = (int) ($filtros['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            throw new RuntimeException('Falta la empresa.');
        }

        $mapaConfig = CanonMunicipalFichaSupport::mapaEmpresasActivas();
        if (! isset($mapaConfig[$empresaId])) {
            throw new RuntimeException('La empresa no tiene configuración de canon municipal activa.');
        }

        $periodicidad = $mapaConfig[$empresaId]['periodicidad'] ?? 'semanal';
        $filtros['periodicidad'] = $periodicidad;
        [$desde, $hasta] = CanonMunicipalCalendarioSupport::resolverRango(
            $periodicidad,
            (string) ($filtros['periodo'] ?? date('Ym')),
            (int) ($filtros['liquidacion'] ?? 1),
        );
        $filtros['fecha_desde'] = $desde;
        $filtros['fecha_hasta'] = $hasta;

        if (! CanonMunicipalListadoFiltros::tieneCriteriosAplicados($filtros)) {
            throw new RuntimeException('Faltan criterios de liquidación.');
        }

        $resultado = $this->reporteService->generar($filtros);
        $cuadra = ! empty($resultado['puede_emitir_nota']);
        $monto = round((float) ($resultado['resumen']['canon_4'] ?? 0), 2);

        /** @var Canon_Municipal_Config|null $config */
        $config = Canon_Municipal_Config::query()
            ->with([
                'empresa:id,codigo,nombre',
                'proveedor:id,codigo,nombre',
                'sectorSolicitudpago:id,codigo,nombre',
                'conceptoSolicitudpago:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'moneda:id,codigo,nombre',
                'centrocosto:id,codigo,nombre',
                'cuentacontableDebe:id,codigo,nombre',
                'cuentacontableHaber:id,codigo,nombre',
            ])
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        $nombreEmpresa = (string) ($resultado['ficha']['nombre'] ?? $config?->empresa?->nombre ?? '');

        return [
            'filtros' => $filtros,
            'resultado' => $resultado,
            'config' => $config,
            'empresa_id' => $empresaId,
            'nombre_empresa' => $nombreEmpresa,
            'cuadra' => $cuadra,
            'monto' => $monto,
            'fecha_desde' => $desde,
            'fecha_hasta' => $hasta,
            'liquidacion' => (int) ($filtros['liquidacion'] ?? 1),
            'periodo' => (string) ($filtros['periodo'] ?? date('Ym')),
            'periodicidad' => $periodicidad,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function armarPreviewDto(array $ctx): array
    {
        /** @var Canon_Municipal_Config|null $config */
        $config = $ctx['config'];
        $nombreEmpresa = (string) $ctx['nombre_empresa'];

        if ($config === null || ! $config->configCompletaParaSolicitud()) {
            return [
                'error' => 'Falta configurar el canon de '.$nombreEmpresa,
                'puede_generar' => false,
                'cuadra' => (bool) $ctx['cuadra'],
            ];
        }

        if (! $ctx['cuadra']) {
            return [
                'error' => 'El cruce no cuadra',
                'puede_generar' => false,
                'cuadra' => false,
            ];
        }

        $monto = (float) $ctx['monto'];
        if ($monto < 0.01) {
            return [
                'error' => 'El canon es cero; no se puede generar la solicitud.',
                'puede_generar' => false,
                'cuadra' => true,
            ];
        }

        $detalle = CanonMunicipalDetallePlantillaSupport::armar(
            $config->plantilla_detalle,
            (int) $ctx['liquidacion'],
            (string) $ctx['periodo'],
        );

        $abrev = CanonMunicipalDetallePlantillaSupport::abreviaturaEmpresa(
            (string) $config->plantilla,
            $nombreEmpresa
        );
        $nombrePdf = CanonMunicipalDetallePlantillaSupport::nombreArchivoNota(
            $abrev,
            (int) $ctx['liquidacion'],
            (string) $ctx['periodo']
        );

        $ccLabel = trim(($config->centrocosto->codigo ?? '').' — '.($config->centrocosto->nombre ?? ''));
        $asiento = [
            [
                'cuenta' => trim(($config->cuentacontableDebe->codigo ?? '').' '.($config->cuentacontableDebe->nombre ?? '')),
                'centrocosto' => $ccLabel,
                'dh' => 'D',
                'debe' => $monto,
                'haber' => null,
            ],
            [
                'cuenta' => trim(($config->cuentacontableHaber->codigo ?? '').' '.($config->cuentacontableHaber->nombre ?? '')),
                'centrocosto' => $ccLabel,
                'dh' => 'H',
                'debe' => null,
                'haber' => $monto,
            ],
        ];

        $duplicado = $this->buscarDuplicado(
            (int) $ctx['empresa_id'],
            (int) $config->concepto_solicitudpago_id,
            $monto
        );

        $diasSinVenta = $this->diasSinVenta($ctx['resultado']['filas'] ?? []);

        $tituloLiq = $this->tituloLiquidacion($ctx);

        return [
            'error' => null,
            'puede_generar' => true,
            'cuadra' => true,
            'titulo' => 'Generar solicitud de pago — Canon bingo '.$tituloLiq,
            'empresa_id' => (int) $ctx['empresa_id'],
            'empresa_label' => trim(($config->empresa->codigo ?? '').' '.$nombreEmpresa),
            'fecha' => now()->format('d/m/Y'),
            'fecha_iso' => now()->toDateString(),
            'proveedor_label' => trim(($config->proveedor->codigo ?? '').' '.($config->proveedor->nombre ?? '')),
            'sector_label' => trim(($config->sectorSolicitudpago->codigo ?? '').' — '.($config->sectorSolicitudpago->nombre ?? '')),
            'concepto_label' => trim(($config->conceptoSolicitudpago->codigo ?? '').' '.($config->conceptoSolicitudpago->nombre ?? '')),
            'formapago_label' => trim(($config->formapagosol->codigo ?? '').' — '.($config->formapagosol->nombre ?? '')),
            'moneda_label' => (string) ($config->moneda->nombre ?? 'PESOS'),
            'monto' => $monto,
            'monto_fmt' => number_format($monto, 2, ',', '.'),
            'detalle' => $detalle,
            'asiento' => $asiento,
            'nombre_pdf' => $nombrePdf,
            'url_nota' => route('exportar_canon_municipal_nota', CanonMunicipalListadoFiltros::paraQueryString($ctx['filtros'])),
            'duplicado' => $duplicado,
            'dias_sin_venta' => $diasSinVenta,
            'fecha_desde' => $ctx['fecha_desde'],
            'fecha_hasta' => $ctx['fecha_hasta'],
            'liquidacion' => (int) $ctx['liquidacion'],
            'periodo' => (string) $ctx['periodo'],
            'filtros' => CanonMunicipalListadoFiltros::paraQueryString($ctx['filtros']),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function generarPdfNota(array $ctx, string $nombreArchivo): string
    {
        $resultado = $ctx['resultado'];
        $ficha = $resultado['ficha'] ?? [];
        $html = View::make('contable.canon_municipal.nota', [
            'resultado' => $resultado,
            'ficha' => $ficha,
            'fecha_nota' => now(),
            'filtros' => $ctx['filtros'],
        ])->render();

        $dir = storage_path('app/tmp/canon_solicitudpago');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $safe = preg_replace('/[^\w.\- ]+/u', '_', $nombreArchivo) ?: 'nota_canon.pdf';
        $ruta = $dir.'/'.uniqid('canon_', true).'_'.$safe;

        $pdf = app('dompdf.wrapper');
        $pdf->setPaper('A4', 'portrait');
        $pdf->loadHTML($html)->save($ruta);

        if (! is_file($ruta) || filesize($ruta) < 100) {
            throw new RuntimeException('No se pudo generar el PDF de la nota municipal.');
        }

        return $ruta;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarDuplicado(int $empresaId, int $conceptoId, float $monto): ?array
    {
        $desde = now()->subDays(self::DIAS_DUPLICADO)->toDateString();
        $sp = Solicitudpago::query()
            ->where('empresa_id', $empresaId)
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->whereRaw('ABS(monto - ?) < 0.01', [$monto])
            ->whereDate('fecha', '>=', $desde)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->first(['id', 'codigo', 'fecha', 'monto', 'estado']);

        if ($sp === null) {
            return null;
        }

        $fecha = $sp->fecha
            ? Carbon::parse($sp->fecha)->format('d/m/Y')
            : '';

        return [
            'id' => (int) $sp->id,
            'codigo' => (int) $sp->codigo,
            'fecha' => $fecha,
            'monto' => (float) $sp->monto,
            'monto_fmt' => number_format((float) $sp->monto, 2, ',', '.'),
            'estado' => (string) $sp->estado,
            'estado_label' => SolicitudpagoEstados::label((string) $sp->estado),
            'url' => route('editar_solicitudpago', [
                'id' => (int) $sp->id,
                'origen' => 'modal_consulta',
                'vista' => 'consulta',
            ]),
            'mensaje' => sprintf(
                'Posible duplicado: ya existe la solicitud #%d del %s por %s (%s).',
                (int) $sp->codigo,
                $fecha,
                number_format((float) $sp->monto, 2, ',', '.'),
                SolicitudpagoEstados::label((string) $sp->estado)
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     * @return array<string, mixed>|null
     */
    private function diasSinVenta(array $filas): ?array
    {
        $sinVenta = [];
        foreach ($filas as $fila) {
            $flash = abs((float) ($fila['venta_flash'] ?? $fila['venta'] ?? 0));
            if ($flash < 0.01) {
                $ymd = (string) ($fila['fecha'] ?? '');
                if ($ymd !== '') {
                    $sinVenta[] = Carbon::parse($ymd)->format('d/m');
                }
            }
        }

        $diasRango = count($filas);
        $diasConVenta = $diasRango - count($sinVenta);
        if ($sinVenta === [] || $diasRango <= 0) {
            return null;
        }

        return [
            'dias_con_venta' => $diasConVenta,
            'dias_rango' => $diasRango,
            'fechas' => $sinVenta,
            'mensaje' => sprintf(
                'Hubo ventas solo %d de %d días (%s sin ventas).',
                $diasConVenta,
                $diasRango,
                implode(', ', $sinVenta)
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     */
    private function tituloLiquidacion(array $ctx): string
    {
        $liq = (int) $ctx['liquidacion'];
        $desde = Carbon::parse((string) $ctx['fecha_desde'])->format('d/m');
        $hasta = Carbon::parse((string) $ctx['fecha_hasta'])->format('d/m/Y');
        if (($ctx['periodicidad'] ?? '') === 'quincenal') {
            return sprintf('quincena %d (%s al %s)', $liq, $desde, $hasta);
        }

        return sprintf('semana %d (%s al %s)', $liq, $desde, $hasta);
    }

    private function normalizarFecha(string $valor): ?string
    {
        $valor = trim($valor);
        if ($valor === '') {
            return null;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return $valor;
        }
        if (preg_match('/^(\d{1,2})\/(\d{1,2})\/(\d{4})$/', $valor, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        try {
            return Carbon::parse($valor)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
