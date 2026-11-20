<?php

declare(strict_types=1);

namespace App\Services\Contable\Sicore;

use App\Models\Contable\Sicore_Solicitudpago_Config;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Contable\Sicore\SicoreLiquidacionTipoSupport;
use App\Support\Contable\Sicore\SicoreListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Genera solicitud de pago a ARCA desde el cuadro de liquidación SICORE (1Q / DJ).
 * Patrón CanonMunicipalSolicitudpagoService + config sicore_solicitudpago_config.
 */
final class SicoreSolicitudpagoService
{
    private const DIAS_DUPLICADO = 45;

    /** @var list<string> */
    private const CODIGOS_ORDEN = ['217', '767', '787'];

    public function __construct(
        private readonly SicoreLiquidacionService $liquidacionService,
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
     * @param  list<UploadedFile>  $adjuntosUsuario
     * @return array{solicitud: Solicitudpago, codigo: int}
     */
    public function confirmar(
        array $filtros,
        string $fechaVencimiento,
        ?string $observacion,
        bool $generarIgual,
        array $adjuntosUsuario = [],
    ): array {
        $ctx = $this->resolverContexto($filtros);
        $preview = $this->armarPreviewDto($ctx);

        if (! empty($preview['error'])) {
            throw new RuntimeException((string) $preview['error']);
        }

        $vto = $this->normalizarFecha($fechaVencimiento);
        if ($vto === null) {
            throw new RuntimeException('El vencimiento es obligatorio.');
        }

        if (! empty($preview['duplicado']) && ! $generarIgual) {
            throw new RuntimeException('Hay una solicitud similar. Marque «Generar igual» para continuar.');
        }

        /** @var Sicore_Solicitudpago_Config $config */
        $config = $ctx['config'];
        $monto = (float) $ctx['monto'];
        $ccId = (int) $config->centrocosto_id;
        $empresaId = (int) $ctx['empresa_id'];
        $saldoPagar = $ctx['saldo_pagar'];

        $empresaIds = [];
        $cuentaIds = [];
        $ccIds = [];
        $dh = [];
        $montos = [];

        foreach (self::CODIGOS_ORDEN as $cod) {
            $importe = round((float) ($saldoPagar[$cod] ?? 0), 2);
            $cuentaId = (int) ($config->mapaCuentasDebe()[$cod] ?? 0);
            if ($importe < 0.01 || $cuentaId <= 0) {
                continue;
            }
            $empresaIds[] = $empresaId;
            $cuentaIds[] = $cuentaId;
            $ccIds[] = $ccId;
            $dh[] = 'D';
            $montos[] = $importe;
        }

        if ($cuentaIds === []) {
            throw new RuntimeException('No hay saldos a pagar para imputar en la solicitud.');
        }

        $bancoId = (int) $config->cuentacontable_banco_id;
        $empresaIds[] = $empresaId;
        $cuentaIds[] = $bancoId;
        $ccIds[] = $ccId;
        $dh[] = 'H';
        $montos[] = $monto;

        $rutaPdf = $this->generarPdfLiquidacion($ctx['filtros']);
        $uploaded = [];
        try {
            if ($rutaPdf !== null) {
                $uploaded[] = new UploadedFile(
                    $rutaPdf,
                    (string) $preview['nombre_pdf'],
                    'application/pdf',
                    null,
                    true
                );
            }
            foreach ($adjuntosUsuario as $file) {
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $uploaded[] = $file;
                }
            }

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
                'empresa_ids' => $empresaIds,
                'cuentacontable_ids' => $cuentaIds,
                'centrocosto_ids' => $ccIds,
                'debe_haberes' => $dh,
                'montos_cuenta' => $montos,
                'archivos_nuevos' => $uploaded,
            ];

            $sp = $this->solicitudpagoRepository->create($payload);
            $codigo = (int) ($sp->codigo ?? 0);
            if ($codigo <= 0) {
                throw new RuntimeException('No se pudo generar la solicitud de pago.');
            }

            return [
                'solicitud' => $sp,
                'codigo' => $codigo,
            ];
        } finally {
            if ($rutaPdf !== null && is_file($rutaPdf)) {
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

        $calc = $this->liquidacionService->calcularCuadro($filtros);
        $monto = round((float) ($calc['total_a_pagar'] ?? 0), 2);
        $saldoPagar = $calc['saldo_pagar'] ?? [];

        /** @var Sicore_Solicitudpago_Config|null $config */
        $config = Sicore_Solicitudpago_Config::query()
            ->with([
                'empresa:id,codigo,nombre',
                'proveedor:id,codigo,nombre',
                'sectorSolicitudpago:id,codigo,nombre',
                'conceptoSolicitudpago:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'moneda:id,codigo,nombre',
                'centrocosto:id,codigo,nombre',
                'cuentacontable217:id,codigo,nombre',
                'cuentacontable767:id,codigo,nombre',
                'cuentacontable787:id,codigo,nombre',
                'cuentacontableBanco:id,codigo,nombre',
            ])
            ->where('empresa_id', $empresaId)
            ->where('activo', true)
            ->first();

        return [
            'filtros' => $filtros,
            'calc' => $calc,
            'config' => $config,
            'empresa_id' => $empresaId,
            'nombre_empresa' => (string) ($calc['empresa_label'] ?? ''),
            'empresa_corta' => (string) ($calc['empresa_corta'] ?? ''),
            'tipo' => (string) ($calc['tipo'] ?? ''),
            'periodo' => (string) ($calc['periodo'] ?? ''),
            'monto' => $monto,
            'saldo_pagar' => $saldoPagar,
            'compras_desde' => (string) ($calc['compras_desde'] ?? ''),
            'compras_hasta' => (string) ($calc['compras_hasta'] ?? ''),
            'sueldos_desde' => (string) ($calc['sueldos_desde'] ?? ''),
            'sueldos_hasta' => (string) ($calc['sueldos_hasta'] ?? ''),
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function armarPreviewDto(array $ctx): array
    {
        /** @var Sicore_Solicitudpago_Config|null $config */
        $config = $ctx['config'];
        $nombreEmpresa = (string) $ctx['nombre_empresa'];

        if ($config === null || ! $config->configCompleta()) {
            return [
                'error' => 'Falta configurar la solicitud de pago SICORE de '.$nombreEmpresa,
                'puede_generar' => false,
            ];
        }

        $monto = (float) $ctx['monto'];
        if ($monto < 0.01) {
            return [
                'error' => 'El total a pagar es cero; no se genera solicitud.',
                'puede_generar' => false,
            ];
        }

        $tipo = (string) $ctx['tipo'];
        $tipoTxt = $tipo === SicoreLiquidacionTipoSupport::TIPO_1Q ? '1Q' : 'DJ';
        $empresaCorta = (string) ($ctx['empresa_corta'] !== '' ? $ctx['empresa_corta'] : $nombreEmpresa);
        $periodoMmYyyy = $this->periodoMmYyyy((string) $ctx['compras_hasta']);
        $detalle = trim($tipoTxt.' SICORE '.$empresaCorta.' '.$periodoMmYyyy);

        $ccLabel = trim(($config->centrocosto->codigo ?? '').' — '.($config->centrocosto->nombre ?? ''));
        $asiento = [];
        $mapaRel = [
            '217' => $config->cuentacontable217,
            '767' => $config->cuentacontable767,
            '787' => $config->cuentacontable787,
        ];
        $etiquetas = [
            '217' => 'Ganancias (217)',
            '767' => 'IVA (767)',
            '787' => 'Sueldos 4ta (787)',
        ];

        foreach (self::CODIGOS_ORDEN as $cod) {
            $importe = round((float) ($ctx['saldo_pagar'][$cod] ?? 0), 2);
            if ($importe < 0.01) {
                continue;
            }
            $cuenta = $mapaRel[$cod] ?? null;
            $asiento[] = [
                'cuenta' => trim(($cuenta->codigo ?? '').' '.($cuenta->nombre ?? '')),
                'centrocosto' => $ccLabel,
                'dh' => 'D',
                'debe' => $importe,
                'haber' => null,
                'etiqueta' => $etiquetas[$cod] ?? $cod,
            ];
        }
        $asiento[] = [
            'cuenta' => trim(($config->cuentacontableBanco->codigo ?? '').' '.($config->cuentacontableBanco->nombre ?? '')),
            'centrocosto' => $ccLabel,
            'dh' => 'H',
            'debe' => null,
            'haber' => $monto,
            'etiqueta' => 'Banco / Interbanking',
        ];

        $duplicado = $this->buscarDuplicado(
            (int) $ctx['empresa_id'],
            (int) $config->concepto_solicitudpago_id,
            $monto,
            $detalle,
        );

        $tituloTipo = $tipo === SicoreLiquidacionTipoSupport::TIPO_1Q ? '1ra quincena' : 'DJ';
        $nombrePdf = 'liquidacion_sicore_'.$tipoTxt.'_'.$empresaCorta.'_'.$periodoMmYyyy.'.pdf';
        $nombrePdf = preg_replace('/[^\w.\-]+/u', '_', $nombrePdf) ?: 'liquidacion_sicore.pdf';

        return [
            'error' => null,
            'puede_generar' => true,
            'titulo' => 'Generar solicitud de pago — SICORE '.$tituloTipo,
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
            'periodo' => (string) $ctx['periodo'],
            'tipo' => $tipo,
            'tipo_label' => $tituloTipo,
            'saldo_pagar' => $ctx['saldo_pagar'],
            'asiento' => $asiento,
            'nombre_pdf' => $nombrePdf,
            'duplicado' => $duplicado,
            'filtros' => $this->filtrosParaForm($ctx['filtros']),
        ];
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function generarPdfLiquidacion(array $filtros): ?string
    {
        try {
            $completa = $this->liquidacionService->generarCompleta($filtros);
            $ruta = (string) ($completa['ruta_pdf'] ?? '');
            if ($ruta === '' || ! is_file($ruta)) {
                return null;
            }

            $dir = storage_path('app/tmp/sicore_solicitudpago');
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            $destino = $dir.'/'.uniqid('sicore_', true).'_liquidacion.pdf';
            if (! @copy($ruta, $destino)) {
                return null;
            }

            // limpiar artefactos del ZIP (no borrar el copy)
            foreach ([$completa['ruta_zip'] ?? '', $completa['ruta_xlsx'] ?? '', $ruta] as $tmp) {
                $tmp = (string) $tmp;
                if ($tmp !== '' && is_file($tmp)) {
                    @unlink($tmp);
                }
            }

            return $destino;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buscarDuplicado(int $empresaId, int $conceptoId, float $monto, string $detalle): ?array
    {
        $desde = now()->subDays(self::DIAS_DUPLICADO)->toDateString();
        $detalleNorm = mb_strtolower(trim($detalle));

        $query = Solicitudpago::query()
            ->where('empresa_id', $empresaId)
            ->where('concepto_solicitudpago_id', $conceptoId)
            ->whereDate('fecha', '>=', $desde)
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        $sp = (clone $query)
            ->whereRaw('ABS(monto - ?) < 0.01', [$monto])
            ->first(['id', 'codigo', 'fecha', 'monto', 'estado', 'detalle']);

        if ($sp === null && $detalleNorm !== '') {
            $sp = $query
                ->whereRaw('LOWER(TRIM(detalle)) = ?', [$detalleNorm])
                ->first(['id', 'codigo', 'fecha', 'monto', 'estado', 'detalle']);
        }

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
     * @param  array<string, mixed>  $filtros
     * @return array<string, mixed>
     */
    private function filtrosParaForm(array $filtros): array
    {
        $base = SicoreListadoFiltros::paraQueryString($filtros);
        foreach ([
            'tipo_liquidacion',
            'compras_fecha_desde', 'compras_fecha_hasta',
            'sueldos_fecha_desde', 'sueldos_fecha_hasta',
        ] as $k) {
            if (isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null) {
                $base[$k] = $filtros[$k];
            }
        }
        if (! empty($filtros['saf']) && is_array($filtros['saf'])) {
            $base['saf'] = $filtros['saf'];
        }
        if (! empty($filtros['ingreso_fecha']) && is_array($filtros['ingreso_fecha'])) {
            $base['ingreso_fecha'] = $filtros['ingreso_fecha'];
        }
        if (! empty($filtros['ingreso_importe']) && is_array($filtros['ingreso_importe'])) {
            $base['ingreso_importe'] = $filtros['ingreso_importe'];
        }

        return $base;
    }

    private function periodoMmYyyy(string $ymd): string
    {
        if (preg_match('/^(\d{4})-(\d{2})/', $ymd, $m)) {
            return $m[2].'-'.$m[1];
        }

        return $ymd;
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
