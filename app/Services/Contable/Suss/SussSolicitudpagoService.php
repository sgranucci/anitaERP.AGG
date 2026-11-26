<?php

declare(strict_types=1);

namespace App\Services\Contable\Suss;

use App\Models\Contable\Suss_Empresa_Config;
use App\Models\Solicitudpago\Solicitudpago;
use App\Repositories\Solicitudpago\SolicitudpagoRepositoryInterface;
use App\Support\Contable\Suss\SussLiquidacionTipoSupport;
use App\Support\Contable\Suss\SussListadoFiltros;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use RuntimeException;

/**
 * Solicitud de pago del VEP SUSS (1Q pago a cuenta o DJ 2Q) desde la pantalla SUSS.
 *
 * Asiento: Debe cuenta de retenciones (214010015) / Haber banco (111050009), por el monto del VEP.
 */
final class SussSolicitudpagoService
{
    private const DIAS_DUPLICADO = 45;

    public function __construct(
        private readonly SussLiquidacionService $liquidacionService,
        private readonly SolicitudpagoRepositoryInterface $solicitudpagoRepository,
    ) {}

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
     * @param  array{vep: ?UploadedFile, f996: ?UploadedFile, sire: list<UploadedFile>}  $adjuntos
     * @return array{solicitud: Solicitudpago, codigo: int}
     */
    public function confirmar(
        array $filtros,
        float $vepIngresado,
        string $fechaVencimiento,
        ?string $observacion,
        bool $generarIgual,
        bool $confirmoDiferencia,
        array $adjuntos,
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

        $monto = round($vepIngresado, 2);
        if ($monto < 0.01) {
            throw new RuntimeException('El monto del VEP tiene que ser mayor que cero.');
        }

        $esperado = (float) $preview['vep_esperado'];
        if (abs($monto - $esperado) > 0.005 && ! $confirmoDiferencia) {
            throw new RuntimeException(
                'El VEP ingresado ($ '.number_format($monto, 2, ',', '.').') no coincide con el saldo calculado ($ '
                .number_format($esperado, 2, ',', '.').'). Marque «Confirmo» para continuar.'
            );
        }

        $detalle = (string) $preview['detalle'];

        /** @var Suss_Empresa_Config $config */
        $config = $ctx['config'];
        $empresaId = (int) $ctx['empresa_id'];
        $ccId = (int) $config->centrocosto_id;

        $duplicado = $this->buscarDuplicado($empresaId, (int) $config->concepto_solicitudpago_id, $monto, $detalle);
        if ($duplicado !== null && ! $generarIgual) {
            throw new RuntimeException('Hay una solicitud similar. Marque «Generar igual» para continuar.');
        }

        if (empty($adjuntos['vep']) || ! $adjuntos['vep'] instanceof UploadedFile || ! $adjuntos['vep']->isValid()) {
            throw new RuntimeException('Adjunte el VEP.');
        }
        if ($ctx['es_dj'] && (empty($adjuntos['f996']) || ! $adjuntos['f996'] instanceof UploadedFile || ! $adjuntos['f996']->isValid())) {
            throw new RuntimeException('En la DJ (2Q) adjunte el F.996.');
        }

        $empresaIds = [$empresaId, $empresaId];
        $cuentaIds = [(int) $config->cuentacontable_suss_id, (int) $config->cuentacontable_banco_id];
        $ccIds = [$ccId, $ccId];
        $dh = ['D', 'H'];
        $montos = [$monto, $monto];

        $rutaPdf = $this->liquidacionService->generarPdfLiquidacion($ctx['calc']);
        $uploaded = [];
        try {
            $uploaded[] = new UploadedFile(
                $rutaPdf,
                (string) $preview['nombre_pdf'],
                'application/pdf',
                null,
                true,
            );
            $uploaded[] = $adjuntos['vep'];
            if ($ctx['es_dj'] && $adjuntos['f996'] instanceof UploadedFile) {
                $uploaded[] = $adjuntos['f996'];
            }
            foreach ($adjuntos['sire'] ?? [] as $file) {
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
                'detalle' => $detalle,
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

            return ['solicitud' => $sp, 'codigo' => $codigo];
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

        $calc = $this->liquidacionService->calcularCuadro($filtros);

        /** @var Suss_Empresa_Config|null $config */
        $config = Suss_Empresa_Config::query()
            ->with([
                'proveedor:id,codigo,nombre',
                'sectorSolicitudpago:id,codigo,nombre',
                'conceptoSolicitudpago:id,codigo,nombre',
                'formapagosol:id,codigo,nombre',
                'moneda:id,codigo,nombre',
                'centrocosto:id,codigo,nombre',
                'cuentacontableSuss:id,codigo,nombre',
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
            'es_dj' => (bool) $calc['es_dj'],
        ];
    }

    /**
     * Vista previa + resumen del cálculo (aunque falte configuración, el modal de liquidación muestra el cuadro).
     *
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function armarPreviewDto(array $ctx): array
    {
        $dto = $this->armarPreviewBase($ctx);
        $dto['calculo'] = $this->resumenCalculo($ctx['calc']);

        return $dto;
    }

    /**
     * @param  array<string, mixed>  $calc
     * @return array<string, mixed>
     */
    private function resumenCalculo(array $calc): array
    {
        $items = [];
        foreach ($calc['conciliacion']['items'] ?? [] as $item) {
            $items[] = [
                'nombre' => $item['nombre'] ?? '',
                'total_suss' => (float) ($item['total_suss'] ?? 0),
                'total_mayor' => (float) ($item['total_mayor'] ?? 0),
                'pago_posterior' => (float) ($item['pago_posterior'] ?? 0),
                'diferencia' => (float) ($item['diferencia'] ?? 0),
                'cuadra' => (bool) ($item['cuadra'] ?? false),
            ];
        }

        return [
            'tipo' => $calc['tipo'],
            'es_dj' => (bool) $calc['es_dj'],
            'periodo_label' => $calc['periodo_label'],
            'sigla' => $calc['sigla'],
            'cantidad_q1' => (int) $calc['cantidad_q1'],
            'cantidad_q2' => (int) $calc['cantidad_q2'],
            'total_q1' => (float) $calc['total_q1'],
            'total_q2' => (float) $calc['total_q2'],
            'cuadro' => $calc['cuadro'],
            'vep_esperado' => (float) $calc['vep_esperado'],
            'controles' => $calc['controles'],
            'conciliacion' => $items,
        ];
    }

    /**
     * @param  array<string, mixed>  $ctx
     * @return array<string, mixed>
     */
    private function armarPreviewBase(array $ctx): array
    {
        /** @var Suss_Empresa_Config|null $config */
        $config = $ctx['config'];
        $calc = $ctx['calc'];
        $esDj = (bool) $ctx['es_dj'];
        $tipo = (string) $calc['tipo'];
        $tipoTxt = SussLiquidacionTipoSupport::etiquetaTipo($tipo);
        $nombreEmpresa = (string) ($calc['empresa_label'] ?? '');

        if ($config === null || ! $config->configSolicitudCompleta()) {
            return ['error' => 'Falta configurar la solicitud de pago SUSS de '.$nombreEmpresa.' (Configuración SUSS → Solicitud de pago).', 'puede_generar' => false];
        }

        $controles = $calc['controles'] ?? ['bloquea' => false, 'mensaje' => null, 'avisos' => []];
        if (! empty($controles['bloquea'])) {
            return ['error' => (string) $controles['mensaje'], 'puede_generar' => false];
        }

        $cantidadQuincena = $esDj ? (int) $calc['cantidad_q2'] : (int) $calc['cantidad_q1'];
        if ($cantidadQuincena === 0) {
            return ['error' => 'Sin retenciones SUSS en la quincena.', 'puede_generar' => false];
        }

        $vep = round((float) $calc['vep_esperado'], 2);
        if ($vep < 0.01) {
            return ['error' => 'El saldo a pagar es cero; no se genera solicitud.', 'puede_generar' => false];
        }

        $sigla = (string) $calc['sigla'];
        $detalle = SussLiquidacionTipoSupport::detalleSolicitud($tipo, (string) $calc['periodo'], $sigla);
        $ccLabel = trim(($config->centrocosto->codigo ?? '').' — '.($config->centrocosto->nombre ?? ''));

        $asiento = [
            [
                'cuenta' => trim(($config->cuentacontableSuss->codigo ?? '').' '.($config->cuentacontableSuss->nombre ?? '')),
                'centrocosto' => $ccLabel,
                'dh' => 'D',
                'debe' => $vep,
                'haber' => null,
            ],
            [
                'cuenta' => trim(($config->cuentacontableBanco->codigo ?? '').' '.($config->cuentacontableBanco->nombre ?? '')),
                'centrocosto' => $ccLabel,
                'dh' => 'H',
                'debe' => null,
                'haber' => $vep,
            ],
        ];

        $duplicado = $this->buscarDuplicado(
            (int) $ctx['empresa_id'],
            (int) $config->concepto_solicitudpago_id,
            $vep,
            $detalle,
        );

        $nombrePdf = preg_replace('/[^\w.\-]+/u', '_', (string) $calc['nombre_pdf_zip']) ?: 'liquidacion_suss.pdf';

        return [
            'error' => null,
            'puede_generar' => true,
            'titulo' => 'Generar solicitud de pago — Retenciones SUSS '.($esDj ? '2Q' : '1Q').' '.$calc['periodo_mmyyyy'],
            'tipo' => $tipo,
            'tipo_label' => $tipoTxt,
            'es_dj' => $esDj,
            'empresa_label' => trim(($config->empresa->codigo ?? '').' '.$nombreEmpresa),
            'fecha' => now()->format('d/m/Y'),
            'fecha_iso' => now()->toDateString(),
            'proveedor_label' => trim(($config->proveedor->codigo ?? '').' '.($config->proveedor->nombre ?? '')),
            'sector_label' => trim(($config->sectorSolicitudpago->codigo ?? '').' — '.($config->sectorSolicitudpago->nombre ?? '')),
            'concepto_label' => trim(($config->conceptoSolicitudpago->codigo ?? '').' '.($config->conceptoSolicitudpago->nombre ?? '')),
            'formapago_label' => trim(($config->formapagosol->codigo ?? '').' — '.($config->formapagosol->nombre ?? '')),
            'moneda_label' => (string) ($config->moneda->nombre ?? 'PESOS'),
            'detalle' => $detalle,
            'periodo' => (string) $calc['periodo'],
            'vep_esperado' => $vep,
            'vep_esperado_fmt' => number_format($vep, 2, ',', '.'),
            'asiento' => $asiento,
            'nombre_pdf' => $nombrePdf,
            'duplicado' => $duplicado,
            'avisos' => $controles['avisos'] ?? [],
            'filtros' => $this->filtrosParaForm($ctx['filtros']),
        ];
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
            $sp = (clone $query)
                ->whereRaw('LOWER(TRIM(detalle)) = ?', [$detalleNorm])
                ->first(['id', 'codigo', 'fecha', 'monto', 'estado', 'detalle']);
        }

        if ($sp === null) {
            return null;
        }

        $fecha = $sp->fecha ? Carbon::parse($sp->fecha)->format('d/m/Y') : '';

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
        $base = SussListadoFiltros::paraQueryString($filtros);
        foreach (['tipo_liquidacion', 'saldo_favor_anterior', 'pago_cuenta', 'pago_cuenta_fecha'] as $k) {
            if (isset($filtros[$k]) && $filtros[$k] !== '' && $filtros[$k] !== null) {
                $base[$k] = $filtros[$k];
            }
        }

        return $base;
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
