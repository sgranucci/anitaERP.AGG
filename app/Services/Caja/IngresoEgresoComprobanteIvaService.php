<?php

namespace App\Services\Caja;

use App\Models\Ai\AiDecision;
use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Concepto;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Concepto_Ivacompra;
use App\Repositories\Compras\Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Contable\CuentacontableRepositoryInterface;
use App\Services\Ai\AiDecisionLogger;
use App\Support\Caja\IngresoEgresoComprobanteIvaAiHashSupport;
use App\Support\Caja\IngresoEgresoComprobanteIvaAsientoSupport;
use App\Support\Caja\IngresoEgresoComprobanteIvaValidacionSupport;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorTipoTesoreria;
use App\Support\Compras\ComprobanteProveedorTipoAutorizacion;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Database\EloquentAuditDeleteSupport;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class IngresoEgresoComprobanteIvaService
{
    public function __construct(
        private Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
        private Comprobante_Proveedor_ConceptoRepositoryInterface $conceptoRepository,
        private CuentacontableRepositoryInterface $cuentacontableRepository,
        private IngresoEgresoComprobanteIvaArchivoService $archivoService,
        private IngresoEgresoComprobanteIvaAsientoVinculoService $asientoVinculoService,
        private AiDecisionLogger $aiDecisionLogger,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function listarPorCajaMovimiento(int $cajaMovimientoId): array
    {
        return Comprobante_Proveedor::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->where('origen_entrada', ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO)
            ->with([
                'proveedores',
                'tipotransaccion_compras',
                'comprobante_proveedor_conceptos.concepto_ivacompras',
                'comprobante_proveedor_archivos',
            ])
            ->orderBy('id')
            ->get()
            ->map(fn (Comprobante_Proveedor $cp) => $this->serializarComprobante($cp))
            ->all();
    }

    /**
     * @param  list<array<string, mixed>>  $comprobantesJson
     * @param  list<object|array<string, mixed>>  $lineasCaja
     */
    public function validarTotalesContraCaja(array $comprobantesJson, array $lineasCaja, int $monedaReferenciaId = 1): void
    {
        IngresoEgresoComprobanteIvaValidacionSupport::validarTotales(
            $comprobantesJson,
            $lineasCaja,
            $monedaReferenciaId,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $comprobantesJson
     */
    public function sincronizarDesdeJson(int $cajaMovimientoId, int $empresaId, array $comprobantesJson, bool $vincularAsiento = true): void
    {
        $existentes = Comprobante_Proveedor::query()
            ->where('caja_movimiento_id', $cajaMovimientoId)
            ->where('origen_entrada', ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO)
            ->pluck('id')
            ->all();

        $conservados = [];

        DB::transaction(function () use ($comprobantesJson, $cajaMovimientoId, $empresaId, &$conservados): void {
            foreach ($comprobantesJson as $payload) {
                if (! is_array($payload)) {
                    continue;
                }

                $id = (int) ($payload['id'] ?? 0);
                if ($id > 0) {
                    $comprobante = Comprobante_Proveedor::query()->find($id);
                    if (! $comprobante || (int) $comprobante->caja_movimiento_id !== $cajaMovimientoId) {
                        throw new RuntimeException('Comprobante IVA #'.$id.' no pertenece al movimiento de caja.');
                    }
                    $this->actualizarComprobante($comprobante, $payload, $empresaId);
                    $this->archivoService->persistirDesdePayload($comprobante->fresh(), $payload);
                    $this->resolverDecisionIa($payload, (int) $comprobante->id);
                    $conservados[] = $id;

                    continue;
                }

                $nuevo = $this->crearComprobante($payload, $cajaMovimientoId, $empresaId);
                $this->archivoService->persistirDesdePayload($nuevo, $payload);
                $this->resolverDecisionIa($payload, (int) $nuevo->id);
                $conservados[] = (int) $nuevo->id;
            }
        });

        $aEliminar = array_diff($existentes, $conservados);
        if ($aEliminar !== []) {
            Comprobante_Proveedor::query()->whereIn('id', $aEliminar)->update([
                'asiento_id' => null,
            ]);
            EloquentAuditDeleteSupport::each(
                Comprobante_Proveedor::query()->whereIn('id', $aEliminar)
            );
        }

        if ($vincularAsiento) {
            $this->asientoVinculoService->vincularPorCajaMovimiento($cajaMovimientoId);
        }
    }

    /**
     * Marca confirmada o editada la sugerencia que originó el comprobante.
     *
     * @param  array<string, mixed>  $payload
     */
    private function resolverDecisionIa(array $payload, int $comprobanteId): void
    {
        $decisionId = $payload['ai_decision_id'] ?? null;
        if (! is_numeric($decisionId)) {
            return;
        }

        $hashSugerencia = trim((string) ($payload['ai_sugerencia_hash'] ?? ''));
        $hashFinal = IngresoEgresoComprobanteIvaAiHashSupport::calcular($payload);
        $accion = $hashSugerencia !== '' && hash_equals($hashSugerencia, $hashFinal)
            ? AiDecision::ACCION_CONFIRMADA
            : AiDecision::ACCION_EDITADA;

        $this->aiDecisionLogger->resolver((int) $decisionId, $accion, null, [
            'entidad_id' => $comprobanteId,
            'entidad_tipo' => 'comprobante_proveedor',
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function previewAsientoComprobante(array $payload, int $empresaId): array
    {
        $conceptos = $payload['conceptos'] ?? [];
        if (! is_array($conceptos)) {
            $conceptos = [];
        }

        $avisos = IngresoEgresoComprobanteIvaAsientoSupport::avisosCuentasFaltantes($conceptos);
        $totalComprobante = round(abs((float) ($payload['total'] ?? 0)), 2);

        try {
            $conceptos = ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($conceptos);
            $lineasDebe = IngresoEgresoComprobanteIvaAsientoSupport::lineasDebeDesdeConceptos($conceptos);
            $totalDebe = round(array_sum(array_column($lineasDebe, 'importe')), 2);

            $lineas = [];
            foreach ($lineasDebe as $linea) {
                $cuenta = $this->cuentacontableRepository->find($linea['cuentacontable_id']);
                $lineas[] = [
                    'cuentacontable_id' => $linea['cuentacontable_id'],
                    'codigo' => $cuenta->codigo ?? '',
                    'nombre' => $cuenta->nombre ?? '',
                    'debe' => $linea['importe'],
                    'haber' => 0,
                    'observacion' => $linea['observacion'],
                    'automatico' => false,
                ];
            }

            $error = null;
            if ($totalComprobante > 0 && abs($totalDebe - $totalComprobante) > 0.05) {
                $error = 'Los conceptos ('.number_format($totalDebe, 2).') no coinciden con el total ('.number_format($totalComprobante, 2).').';
            }

            return [
                'activo' => true,
                'es_preview' => true,
                'error' => $error,
                'avisos' => $avisos,
                'total_comprobante' => $totalComprobante,
                'total_debe' => $totalDebe,
                'total_haber' => $totalComprobante,
                'lineas' => $lineas,
                'nota' => 'El haber (disponibilidades) se imputa automáticamente desde las cuentas de caja del movimiento.',
            ];
        } catch (RuntimeException $e) {
            return [
                'activo' => true,
                'es_preview' => true,
                'error' => $e->getMessage(),
                'avisos' => $avisos,
                'total_comprobante' => $totalComprobante,
                'lineas' => [],
            ];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function crearComprobante(array $payload, int $cajaMovimientoId, int $empresaId): Comprobante_Proveedor
    {
        $cabecera = $this->armarCabecera($payload, $cajaMovimientoId, $empresaId);
        $this->assertUnicoComprobante($cabecera, null);

        $cabecera['creousuario_id'] = Auth::id();
        $cabecera['estado'] = ComprobanteProveedorEstados::CONTABILIZADO;

        $comprobante = $this->comprobanteRepository->create($cabecera);
        $this->sincronizarConceptos($comprobante, $payload);
        $this->registrarEstado($comprobante, 'Alta desde ingresos y egresos');

        return $comprobante;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function actualizarComprobante(Comprobante_Proveedor $comprobante, array $payload, int $empresaId): void
    {
        $cabecera = $this->armarCabecera($payload, (int) $comprobante->caja_movimiento_id, $empresaId);
        $this->assertUnicoComprobante($cabecera, (int) $comprobante->id);
        unset($cabecera['creousuario_id'], $cabecera['estado']);

        $this->comprobanteRepository->update($cabecera, (int) $comprobante->id);
        $this->conceptoRepository->deletePorComprobanteProveedor((int) $comprobante->id);
        $this->sincronizarConceptos($comprobante->fresh(), $payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function armarCabecera(array $payload, int $cajaMovimientoId, int $empresaId): array
    {
        $proveedorId = (int) ($payload['proveedor_id'] ?? 0);
        $tipoTesoreria = (string) ($payload['tipo_tesoreria'] ?? ComprobanteProveedorTipoTesoreria::FONDO_FIJO);
        if (! in_array($tipoTesoreria, ComprobanteProveedorTipoTesoreria::todos(), true)) {
            $tipoTesoreria = ComprobanteProveedorTipoTesoreria::FONDO_FIJO;
        }

        if ($proveedorId <= 0 && trim((string) ($payload['proveedor_nombre_eventual'] ?? '')) === '') {
            throw new RuntimeException('Indique proveedor del maestro o datos de proveedor eventual.');
        }

        $documentoEventual = $proveedorId > 0
            ? null
            : ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos((string) ($payload['proveedor_documento_eventual'] ?? ''));

        if ($proveedorId <= 0 && $documentoEventual === '') {
            throw new RuntimeException('El proveedor eventual debe tener un CUIT válido (11 dígitos).');
        }

        $cuitNormalizado = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
            $proveedorId > 0 ? $proveedorId : null,
            $documentoEventual,
        );

        return [
            'empresa_id' => $empresaId,
            'proveedor_id' => $proveedorId > 0 ? $proveedorId : null,
            'proveedor_nombre_eventual' => $proveedorId > 0 ? null : trim((string) ($payload['proveedor_nombre_eventual'] ?? '')),
            'proveedor_documento_eventual' => $proveedorId > 0 ? null : $documentoEventual,
            'identificacion_proveedor_cuit' => $cuitNormalizado,
            'proveedor_condicioniva_id_eventual' => $proveedorId > 0 ? null : ((int) ($payload['proveedor_condicioniva_id_eventual'] ?? 0) ?: null),
            'tipotransaccion_compra_id' => (int) ($payload['tipotransaccion_compra_id'] ?? 0),
            'letra' => strtoupper(substr((string) ($payload['letra'] ?? 'B'), 0, 1)),
            'sucursal' => (int) ($payload['sucursal'] ?? 0),
            'numerocomprobante' => (int) ($payload['numerocomprobante'] ?? 0),
            'fechacomprobante' => $payload['fechacomprobante'] ?? now()->format('Y-m-d'),
            'fechaiva' => $payload['fechaiva'] ?? ($payload['fechacomprobante'] ?? now()->format('Y-m-d')),
            'subtotal' => (float) ($payload['subtotal'] ?? $payload['total'] ?? 0),
            'total' => (float) ($payload['total'] ?? 0),
            'moneda_id' => (int) ($payload['moneda_id'] ?? 1),
            'cotizacion' => (float) ($payload['cotizacion'] ?? 1),
            'numerocae' => $payload['numerocae'] ?? null,
            'tipo_autorizacion' => ComprobanteProveedorTipoAutorizacion::normalizar(
                $payload['tipo_autorizacion'] ?? null
            ) ?? (filled($payload['numerocae'] ?? null)
                ? ComprobanteProveedorTipoAutorizacion::CAE
                : null),
            'fechavencimientocae' => $payload['fechavencimientocae'] ?? null,
            'modo_carga' => ComprobanteProveedorModoCarga::SIN_RECEPCION,
            'origen_entrada' => ComprobanteProveedorOrigenEntrada::INGRESO_EGRESO,
            'tipo_tesoreria' => $tipoTesoreria,
            'caja_movimiento_id' => $cajaMovimientoId,
            'leyenda' => $payload['leyenda'] ?? null,
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function sincronizarConceptos(Comprobante_Proveedor $comprobante, array $payload): void
    {
        $conceptos = $payload['conceptos'] ?? [];
        if (! is_array($conceptos)) {
            return;
        }

        $conceptos = array_values(array_filter($conceptos, 'is_array'));
        if ($conceptos === []) {
            return;
        }

        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($conceptos);

        $orden = 1;
        foreach ($lineas as $concepto) {
            $conceptoId = (int) ($concepto['concepto_ivacompra_id'] ?? 0);
            $monto = (float) ($concepto['monto'] ?? 0);
            if ($conceptoId <= 0 || abs($monto) < 0.0001) {
                continue;
            }

            if (! Concepto_Ivacompra::query()->whereKey($conceptoId)->exists()) {
                throw new RuntimeException('Concepto IVA compra id «'.$conceptoId.'» inexistente.');
            }

            $cuentaOverride = (int) ($concepto['cuentacontabledebe_id'] ?? 0) ?: null;

            $this->conceptoRepository->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'concepto_ivacompra_id' => $conceptoId,
                'orden' => $orden++,
                'monto' => $monto,
                'cuentacontabledebe_id' => $cuentaOverride,
            ]);
        }
    }

    /** @param  array<string, mixed>  $payload */
    public function verificarDuplicadoDesdePayload(array $payload, int $empresaId, ?int $excluirId = null): ?string
    {
        $proveedorId = (int) ($payload['proveedor_id'] ?? 0);
        $documentoEventual = $proveedorId > 0
            ? null
            : ComprobanteProveedorUnicidadSupport::normalizarCuitDigitos((string) ($payload['proveedor_documento_eventual'] ?? ''));
        $tipoId = (int) ($payload['tipotransaccion_compra_id'] ?? 0);
        $numero = (int) ($payload['numerocomprobante'] ?? 0);

        if ($empresaId <= 0 || $tipoId <= 0 || $numero <= 0) {
            return null;
        }

        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
            $proveedorId > 0 ? $proveedorId : null,
            $documentoEventual,
        );
        if ($cuit === '') {
            return null;
        }

        $tipoAutorizacion = ComprobanteProveedorTipoAutorizacion::normalizar(
            $payload['tipo_autorizacion'] ?? null
        ) ?? (filled($payload['numerocae'] ?? null)
            ? ComprobanteProveedorTipoAutorizacion::CAE
            : null);

        try {
            ComprobanteProveedorUnicidadSupport::assertUnico(
                $empresaId,
                $tipoId,
                (string) ($payload['letra'] ?? 'B'),
                (int) ($payload['sucursal'] ?? 0),
                $numero,
                $proveedorId > 0 ? $proveedorId : null,
                $documentoEventual,
                $excluirId,
                null,
                $payload['numerocae'] ?? null,
                $tipoAutorizacion,
            );
        } catch (RuntimeException $e) {
            return $e->getMessage();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $cabecera
     */
    private function assertUnicoComprobante(array $cabecera, ?int $excluirId): void
    {
        ComprobanteProveedorUnicidadSupport::assertUnico(
            (int) $cabecera['empresa_id'],
            (int) $cabecera['tipotransaccion_compra_id'],
            (string) $cabecera['letra'],
            (int) $cabecera['sucursal'],
            (int) $cabecera['numerocomprobante'],
            isset($cabecera['proveedor_id']) ? (int) $cabecera['proveedor_id'] : null,
            $cabecera['proveedor_documento_eventual'] ?? null,
            $excluirId,
            null,
            $cabecera['numerocae'] ?? null,
            $cabecera['tipo_autorizacion'] ?? null,
        );
    }

    private function registrarEstado(Comprobante_Proveedor $comprobante, string $observacion): void
    {
        Comprobante_Proveedor_Estado::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => $comprobante->estado,
            'usuario_id' => Auth::id(),
            'observacion' => $observacion,
        ]);
    }

    /** @return array<string, mixed> */
    private function serializarComprobante(Comprobante_Proveedor $cp): array
    {
        return [
            'id' => (int) $cp->id,
            'proveedor_id' => (int) ($cp->proveedor_id ?? 0),
            'proveedor_nombre' => $cp->proveedores?->nombre ?? $cp->proveedor_nombre_eventual,
            'proveedor_nombre_eventual' => $cp->proveedor_nombre_eventual,
            'proveedor_documento_eventual' => $cp->proveedor_documento_eventual,
            'proveedor_condicioniva_id_eventual' => $cp->proveedor_condicioniva_id_eventual,
            'tipotransaccion_compra_id' => (int) $cp->tipotransaccion_compra_id,
            'tipo_tesoreria' => $cp->tipo_tesoreria,
            'letra' => $cp->letra,
            'sucursal' => (int) $cp->sucursal,
            'numerocomprobante' => (int) $cp->numerocomprobante,
            'fechacomprobante' => $cp->fechacomprobante?->format('Y-m-d'),
            'fechaiva' => $cp->fechaiva?->format('Y-m-d'),
            'moneda_id' => (int) $cp->moneda_id,
            'cotizacion' => (float) $cp->cotizacion,
            'total' => (float) $cp->total,
            'numerocae' => $cp->numerocae,
            'tipo_autorizacion' => $cp->tipo_autorizacion,
            'fechavencimientocae' => $cp->fechavencimientocae?->format('Y-m-d'),
            'leyenda' => $cp->leyenda,
            'tiene_pdf' => $cp->comprobante_proveedor_archivos
                ->whereIn('tipo', [ComprobanteProveedorArchivoTipos::ORIGEN_IA, ComprobanteProveedorArchivoTipos::ADJUNTO])
                ->isNotEmpty(),
            'pdf_temp_id' => null,
            'conceptos' => $cp->comprobante_proveedor_conceptos->map(function (Comprobante_Proveedor_Concepto $linea) use ($cp) {
                $empresaId = (int) ($cp->empresa_id ?? 0);
                $cuentaFallback = $linea->concepto_ivacompras
                    ? $linea->concepto_ivacompras->cuentacontableDebeIdParaEmpresa($empresaId)
                    : 0;

                return [
                    'concepto_ivacompra_id' => (int) $linea->concepto_ivacompra_id,
                    'concepto_nombre' => $linea->concepto_ivacompras?->nombre,
                    'monto' => (float) $linea->monto,
                    'cuentacontabledebe_id' => $linea->cuentacontabledebe_id,
                    'cuenta_debe_id' => (int) ($linea->cuentacontabledebe_id ?? $cuentaFallback),
                ];
            })->values()->all(),
        ];
    }
}
