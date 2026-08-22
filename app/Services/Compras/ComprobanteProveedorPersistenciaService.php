<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Models\Compras\Comprobante_Proveedor_Articulo;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Comprobante;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Proveedor;
use App\Repositories\Compras\Comprobante_Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorLineasFacturaSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorMonedaMotor;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorPagoSupport;
use App\Support\Compras\ComprobanteProveedorTipoAutorizacion;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\ComprobanteProveedorAnitaCompraExistenciaSupport;
use App\Support\Compras\OrdencompraComprobanteEstados;
use App\Support\Compras\OrdencompraContratoRutaFacturaSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Contable\PeriodoContableCierreSupport;
use App\Support\Stock\ArticuloSkuMatchSupport;
use DateTimeInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;
use Throwable;

class ComprobanteProveedorPersistenciaService
{
    /** @var list<string> */
    private array $ultimosAvisosControles = [];

    public function __construct(
        private Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
        private Comprobante_Proveedor_ConceptoRepositoryInterface $conceptoRepository,
        private Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
        private ComprobanteProveedorPrefillService $prefillService,
        private ComprobanteProveedorCondicionPagoDesdeOcService $condicionPagoDesdeOc,
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
        private Comprobante_Proveedor_ArchivoRepositoryInterface $archivoRepository,
        private ComprobanteProveedorControlesLegajoService $controlesLegajo,
        private ComprobanteProveedorComplianceValidacionService $complianceValidacion,
        private ComprobanteProveedorContabilizarService $contabilizarService,
    ) {}

    /** @return list<string> */
    public function ultimosAvisosControles(): array
    {
        return $this->ultimosAvisosControles;
    }

    /**
     * Cierre de período: manda la fecha de IVA, que es la de contabilización.
     * La fecha del comprobante puede ser anterior (factura que llega tarde) y no define el período.
     *
     * @param  array<string, mixed>  $payload
     */
    private function assertPeriodoContablePermitido(array $payload): void
    {
        $empresaId = (int) ($payload['empresa_id'] ?? 0);
        if ($empresaId <= 0) {
            return;
        }

        $fecha = $this->fechaIvaYmd($payload);
        if ($fecha === null) {
            return;
        }

        PeriodoContableCierreSupport::assertOperacionPermitida(
            $empresaId,
            $fecha,
            PeriodoContableCierreSupport::ALCANCE_CUENTAS_PAGAR
        );
    }

    /** @param array<string, mixed> $payload */
    private function fechaIvaYmd(array $payload): ?string
    {
        foreach (['fechaiva', 'fechacomprobante'] as $clave) {
            $valor = $payload[$clave] ?? null;
            if ($valor instanceof DateTimeInterface) {
                return $valor->format('Y-m-d');
            }

            $texto = trim((string) $valor);
            if ($texto !== '') {
                return substr($texto, 0, 10);
            }
        }

        return null;
    }

    public function crearDesdeRequest(Request $request): Comprobante_Proveedor
    {
        $payload = $this->armarPayloadCabecera($request);
        $payload['creousuario_id'] = Auth::id();
        $payload['estado'] = ComprobanteProveedorEstados::BORRADOR;

        $this->assertPeriodoContablePermitido($payload);

        $precargaIdRequest = (int) ($request->input('precarga_comprobante_proveedor_id', 0) ?: 0);
        if ($precargaIdRequest > 0) {
            $estadoPrecarga = (string) Precarga_Comprobante_Proveedor::query()
                ->whereKey($precargaIdRequest)
                ->value('estado');
            if ($estadoPrecarga === PrecargaComprobanteEstados::CARGADA_ANITA) {
                throw new RuntimeException(
                    'La precarga #'.$precargaIdRequest.' ya está marcada como cargada en Anita '
                    .'y no se puede generar el comprobante desde el ERP.'
                );
            }
        }

        ComprobanteProveedorUnicidadSupport::assertUnico(
            (int) $payload['empresa_id'],
            (int) $payload['tipotransaccion_compra_id'],
            (string) $payload['letra'],
            (int) $payload['sucursal'],
            (int) $payload['numerocomprobante'],
            (int) $payload['proveedor_id'],
            null,
            null,
            (int) ($request->input('precarga_comprobante_proveedor_id', 0) ?: 0) ?: null,
            $payload['numerocae'] ?? null,
            $payload['tipo_autorizacion'] ?? null,
        );

        ComprobanteProveedorAnitaCompraExistenciaSupport::assertNoDuplicadoEnAnita(
            (int) $payload['empresa_id'],
            (int) $payload['proveedor_id'],
            (int) $payload['tipotransaccion_compra_id'],
            (string) $payload['letra'],
            (int) $payload['sucursal'],
            (int) $payload['numerocomprobante'],
        );

        $this->ejecutarControlesDesdeRequest($request, $payload, null);

        try {
            $comprobante = $this->comprobanteRepository->create($payload);
        } catch (Throwable $e) {
            ComprobanteProveedorUnicidadSupport::relevarViolacionUnicidad(
                $e,
                (int) $payload['empresa_id'],
                (int) $payload['tipotransaccion_compra_id'],
                (string) $payload['letra'],
                (int) $payload['sucursal'],
                (int) $payload['numerocomprobante'],
                (int) $payload['proveedor_id'],
                null,
            );
            throw $e;
        }

        $this->sincronizarConceptos($request, $comprobante);
        $this->sincronizarArticulos($request, $comprobante);
        $this->sincronizarCuotas($request, $comprobante);
        $this->sincronizarRecepciones($request, $comprobante);
        $this->registrarEstadoInicial($comprobante);
        $this->vincularArchivoPrecarga($comprobante);
        $this->marcarPrecargaGenerada(
            isset($payload['precarga_comprobante_proveedor_id'])
                ? (int) $payload['precarga_comprobante_proveedor_id']
                : null
        );
        $this->archivoRepository->sincronizarDesdeRequest($request, (int) $comprobante->id);
        $this->marcarOrdencompraComprobanteCargado($comprobante);

        $comprobante = $comprobante->fresh([
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_archivos',
            'ordencompras',
        ]);
        app(ContratoValidacionAbonoService::class)->asegurarParaComprobante($comprobante);

        return $comprobante;
    }

    public function actualizarDesdeRequest(Request $request, int $id): Comprobante_Proveedor
    {
        $this->ultimosAvisosControles = [];

        $comprobante = $this->comprobanteRepository->find($id);
        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        ComprobanteProveedorPagoSupport::assertSinPagosAplicados($id, 'actualizar');

        // Período de origen y período destino: mover la fecha de IVA impacta en los dos.
        $this->assertPeriodoContablePermitido([
            'empresa_id' => $comprobante->empresa_id,
            'fechaiva' => ComprobanteProveedorFechaContableSupport::fechaYmd($comprobante),
        ]);
        $this->assertPeriodoContablePermitido($this->armarPayloadCabecera($request));

        $estadosEditables = [
            ComprobanteProveedorEstados::BORRADOR,
            ComprobanteProveedorEstados::PENDIENTE_REVISION,
            ComprobanteProveedorEstados::PENDIENTE_APROBACION,
            ComprobanteProveedorEstados::PENDIENTE_DIFERENCIA,
            ComprobanteProveedorEstados::APROBADO,
            ComprobanteProveedorEstados::CONTABILIZADO,
        ];
        if (! in_array($comprobante->estado, $estadosEditables, true)) {
            throw new RuntimeException('El comprobante no admite edición en estado «'.$comprobante->estado.'».');
        }

        $estabaContabilizado = $comprobante->estado === ComprobanteProveedorEstados::CONTABILIZADO;
        if ($estabaContabilizado) {
            $this->contabilizarService->descontabilizarSinPagos($id);
            $comprobante = $this->comprobanteRepository->find($id);
            if (! $comprobante) {
                throw new RuntimeException('Comprobante de proveedor inexistente tras descontabilizar.');
            }
        }

        $payload = $this->armarPayloadCabecera($request);
        ComprobanteProveedorUnicidadSupport::assertUnico(
            (int) $payload['empresa_id'],
            (int) $payload['tipotransaccion_compra_id'],
            (string) $payload['letra'],
            (int) $payload['sucursal'],
            (int) $payload['numerocomprobante'],
            (int) $payload['proveedor_id'],
            null,
            $id,
            (int) ($request->input('precarga_comprobante_proveedor_id', 0) ?: 0) ?: null,
            $payload['numerocae'] ?? null,
            $payload['tipo_autorizacion'] ?? null,
        );

        ComprobanteProveedorAnitaCompraExistenciaSupport::assertNoDuplicadoEnAnita(
            (int) $payload['empresa_id'],
            (int) $payload['proveedor_id'],
            (int) $payload['tipotransaccion_compra_id'],
            (string) $payload['letra'],
            (int) $payload['sucursal'],
            (int) $payload['numerocomprobante'],
            (int) ($comprobante->anita_nro_interno ?? 0) ?: null,
        );

        $this->ejecutarControlesDesdeRequest($request, $payload, $id);

        try {
            $this->comprobanteRepository->update($payload, $id);
        } catch (Throwable $e) {
            ComprobanteProveedorUnicidadSupport::relevarViolacionUnicidad(
                $e,
                (int) $payload['empresa_id'],
                (int) $payload['tipotransaccion_compra_id'],
                (string) $payload['letra'],
                (int) $payload['sucursal'],
                (int) $payload['numerocomprobante'],
                (int) $payload['proveedor_id'],
                null,
                $id,
            );
            throw $e;
        }

        $comprobante = $this->comprobanteRepository->find($id);
        $this->conceptoRepository->deletePorComprobanteProveedor($id);
        $this->sincronizarConceptos($request, $comprobante);
        $this->sincronizarArticulos($request, $comprobante);
        Comprobante_Proveedor_Cuota::query()->where('comprobante_proveedor_id', $id)->delete();
        $this->sincronizarCuotas($request, $comprobante);
        $this->sincronizarRecepciones($request, $comprobante);
        $this->archivoRepository->sincronizarDesdeRequest($request, $id);
        $this->marcarPrecargaGenerada(
            isset($payload['precarga_comprobante_proveedor_id'])
                ? (int) $payload['precarga_comprobante_proveedor_id']
                : null
        );

        if ($estabaContabilizado) {
            try {
                $this->contabilizarService->contabilizar($id);
                $this->ultimosAvisosControles[] = 'Se regeneró el asiento, la cuenta corriente y el sync Anita.';
            } catch (Throwable $e) {
                $this->ultimosAvisosControles[] = 'Quedó en borrador: no se pudo re-contabilizar ('.$e->getMessage().'). Contabilice manualmente.';
            }
        }

        $this->marcarOrdencompraComprobanteCargado($comprobante);

        $comprobante = $comprobante->fresh([
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_archivos',
            'ordencompras',
        ]);
        app(ContratoValidacionAbonoService::class)->asegurarParaComprobante($comprobante);

        return $comprobante;
    }

    public function generarBorradorDesdePrecarga(int $precargaId): Comprobante_Proveedor
    {
        $existente = Comprobante_Proveedor::query()
            ->where('precarga_comprobante_proveedor_id', $precargaId)
            ->first();

        if ($existente) {
            throw new RuntimeException(
                'Ya existe un comprobante (#'.$existente->id.') vinculado a esta precarga.'
            );
        }

        $prefill = $this->prefillService->desdePrecarga($precargaId);
        $data = $prefill['data'];

        $payload = $this->payloadCabeceraDesdeModelo($data);
        $payload['origen_entrada'] = $this->origenComprobanteDesdePrecarga($precargaId);
        $payload['identificacion_proveedor_cuit'] = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
            isset($payload['proveedor_id']) ? (int) $payload['proveedor_id'] : null,
            $payload['proveedor_documento_eventual'] ?? null,
        );
        $payload['creousuario_id'] = Auth::id();
        $payload['estado'] = ComprobanteProveedorEstados::BORRADOR;

        $this->assertPeriodoContablePermitido($payload);

        ComprobanteProveedorUnicidadSupport::assertUnico(
            (int) $payload['empresa_id'],
            (int) $payload['tipotransaccion_compra_id'],
            (string) $payload['letra'],
            (int) $payload['sucursal'],
            (int) $payload['numerocomprobante'],
            isset($payload['proveedor_id']) ? (int) $payload['proveedor_id'] : null,
            $payload['proveedor_documento_eventual'] ?? null,
            null,
            $precargaId,
            $payload['numerocae'] ?? null,
            $payload['tipo_autorizacion'] ?? null,
        );

        ComprobanteProveedorAnitaCompraExistenciaSupport::assertNoDuplicadoEnAnita(
            (int) $payload['empresa_id'],
            (int) ($payload['proveedor_id'] ?? 0),
            (int) $payload['tipotransaccion_compra_id'],
            (string) $payload['letra'],
            (int) $payload['sucursal'],
            (int) $payload['numerocomprobante'],
        );

        $seleccionCom = $prefill['recepciones_seleccionadas'] ?? [];
        if (! is_array($seleccionCom)) {
            $seleccionCom = [];
        }
        $ordencompra = $data->ordencompras
            ?? (($payload['ordencompra_id'] ?? null)
                ? Ordencompra::query()->find((int) $payload['ordencompra_id'])
                : null);
        $modoCarga = (string) ($payload['modo_carga'] ?? '');
        if ($ordencompra && ($seleccionCom !== [] || $modoCarga === ComprobanteProveedorModoCarga::ASIGNA_RECEPCION)) {
            $condicionivaId = Proveedor::query()->whereKey((int) ($payload['proveedor_id'] ?? 0))->value('condicioniva_id');
            $conceptos = collect($prefill['conceptos'] ?? [])->map(function ($linea) {
                $conceptoId = (int) ($linea->concepto_ivacompra_id ?? 0);
                if ($conceptoId > 0 && empty($linea->concepto_ivacompras)) {
                    $linea->setRelation(
                        'concepto_ivacompras',
                        $this->conceptoIvacompraRepository->find($conceptoId)
                    );
                }

                return $linea;
            });
            $resultadoControles = $this->controlesLegajo->validarYAplicar(
                $ordencompra,
                $modoCarga,
                $seleccionCom,
                (float) ($payload['cotizacion'] ?? 1),
                (int) ($payload['moneda_id'] ?? 1),
                (string) ($payload['fechacomprobante'] ?? now()->format('Y-m-d')),
                (string) ($payload['letra'] ?? ''),
                $condicionivaId !== null ? (int) $condicionivaId : null,
                (float) ($payload['total'] ?? 0),
                (float) ($payload['subtotal'] ?? 0),
                $conceptos,
                null,
                false, // borrador desde precarga: no bloquear por diferencia COM
                $prefill['articulos'] ?? collect(),
            );
            $this->aplicarResultadoControles($resultadoControles);
            $idsEfectivos = $resultadoControles['recepcion_ids_efectivos'] ?? [];
            if (is_array($idsEfectivos) && $idsEfectivos !== []) {
                $prefill['recepciones_seleccionadas'] = $idsEfectivos;
            }
        }

        $lineasCompliance = ComprobanteProveedorConceptosIvaCoherenciaSupport::lineasDesdeArrays(
            collect($prefill['conceptos'] ?? [])->pluck('concepto_ivacompra_id')->all(),
            collect($prefill['conceptos'] ?? [])->pluck('monto')->all(),
        );
        $resultadoCompliance = $this->complianceValidacion->validarAlGrabar($payload, $lineasCompliance);
        // Borrador desde precarga/PDF+IA: no bloquear por ARCA; marcar para revisar.
        if (! ($resultadoCompliance['ok'] ?? true)) {
            $payload['pararevisar'] = true;
            $this->ultimosAvisosControles = array_values(array_unique(array_merge(
                $this->ultimosAvisosControles,
                $resultadoCompliance['errores'] ?? [],
                $resultadoCompliance['avisos'] ?? [],
                ['Comprobante dejado en borrador para revisar (constatación ARCA u otros controles).'],
            )));
        } else {
            $this->aplicarResultadoCompliance($resultadoCompliance);
        }

        try {
            $comprobante = $this->comprobanteRepository->create($payload);
        } catch (Throwable $e) {
            ComprobanteProveedorUnicidadSupport::relevarViolacionUnicidad(
                $e,
                (int) $payload['empresa_id'],
                (int) $payload['tipotransaccion_compra_id'],
                (string) $payload['letra'],
                (int) $payload['sucursal'],
                (int) $payload['numerocomprobante'],
                isset($payload['proveedor_id']) ? (int) $payload['proveedor_id'] : null,
                $payload['proveedor_documento_eventual'] ?? null,
            );
            throw $e;
        }

        foreach ($prefill['conceptos'] as $i => $concepto) {
            $this->conceptoRepository->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'concepto_ivacompra_id' => $concepto->concepto_ivacompra_id,
                'orden' => $i + 1,
                'monto' => $concepto->monto,
            ]);
        }

        foreach ($prefill['articulos'] ?? [] as $i => $articulo) {
            Comprobante_Proveedor_Articulo::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'orden' => $i + 1,
                'articulo_id' => $articulo->articulo_id ?: null,
                'sku' => $articulo->sku ?: null,
                'codigo_proveedor' => $articulo->codigo_proveedor ?: null,
                'descripcion' => $articulo->descripcion ?: null,
                'cantidad' => (float) ($articulo->cantidad ?? 0),
                'precio_unitario' => (float) ($articulo->precio_unitario ?? 0),
            ]);
        }

        $this->persistirCuotasDesdeArray($comprobante, $prefill['cuotas']);
        $this->vincularRecepcionesDesdePrefill($comprobante, $prefill);
        $this->registrarEstadoInicial($comprobante);
        $this->vincularArchivoPrecarga($comprobante);
        $this->marcarPrecargaGenerada($precargaId);
        $this->marcarOrdencompraComprobanteCargado($comprobante);

        return $comprobante->fresh([
            'comprobante_proveedor_recepciones',
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_articulos',
        ]);
    }

    /** @param array<string, mixed> $prefill */
    private function vincularRecepcionesDesdePrefill(Comprobante_Proveedor $comprobante, array $prefill): void
    {
        $seleccionadas = $prefill['recepciones_seleccionadas'] ?? [];
        if ($seleccionadas === [] || $comprobante->modo_carga !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            return;
        }

        $ordencompraId = (int) ($comprobante->ordencompra_id ?? 0);
        if ($ordencompraId <= 0) {
            return;
        }

        $this->recepcionesSupport->sincronizar(
            (int) $comprobante->id,
            $ordencompraId,
            $seleccionadas,
            $this->contextoLegajoDesdeComprobante($comprobante),
        );
    }

    /** @return array{proveedor_id: int, empresa_id: int, sector_legajocompra_id: int|null}|null */
    private function contextoLegajoDesdeComprobante(Comprobante_Proveedor $comprobante): ?array
    {
        $proveedorId = (int) ($comprobante->proveedor_id ?? 0);
        $empresaId = (int) ($comprobante->empresa_id ?? 0);
        if ($proveedorId <= 0 || $empresaId <= 0) {
            return null;
        }

        $comprobante->loadMissing('ordencompras');
        $sectorId = $comprobante->ordencompras?->sector_legajocompra_id;

        return [
            'proveedor_id' => $proveedorId,
            'empresa_id' => $empresaId,
            'sector_legajocompra_id' => $sectorId ? (int) $sectorId : null,
        ];
    }

    /** @return array<string, mixed> */
    private function payloadCabeceraDesdeModelo(Comprobante_Proveedor $data): array
    {
        $payload = $data->only($data->getFillable());
        $payload['es_fce'] = (bool) ($payload['es_fce'] ?? false);
        $payload['pararevisar'] = (bool) ($payload['pararevisar'] ?? false);

        return $payload;
    }

    /** @return array<string, mixed> */
    private function armarPayloadCabecera(Request $request): array
    {
        $precargaId = (int) $request->input('precarga_comprobante_proveedor_id', 0);
        $ordencompraId = (int) $request->input('ordencompra_id', 0);

        $origen = (string) $request->input(
            'origen_entrada',
            ComprobanteProveedorOrigenEntrada::resolver(
                $precargaId ?: null,
                $ordencompraId ?: null,
            ),
        );

        $modoCarga = (string) $request->input('modo_carga', ComprobanteProveedorModoCarga::SIN_RECEPCION);
        if (! in_array($modoCarga, ComprobanteProveedorModoCarga::todos(), true)) {
            $modoCarga = ComprobanteProveedorModoCarga::SIN_RECEPCION;
        }

        return [
            'empresa_id' => (int) $request->input('empresa_id'),
            'proveedor_id' => (int) $request->input('proveedor_id'),
            'identificacion_proveedor_cuit' => ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
                (int) $request->input('proveedor_id'),
                null,
            ),
            'tipotransaccion_compra_id' => (int) $request->input('tipotransaccion_compra_id'),
            'ordencompra_id' => $ordencompraId > 0 ? $ordencompraId : null,
            'ordencompra_comprobante_id' => (int) $request->input('ordencompra_comprobante_id', 0) ?: null,
            'precarga_comprobante_proveedor_id' => $precargaId > 0 ? $precargaId : null,
            'condicionpago_id' => (int) $request->input('condicionpago_id', 0) ?: null,
            'letra' => strtoupper(substr((string) $request->input('letra'), 0, 1)),
            'sucursal' => (int) $request->input('sucursal'),
            'numerocomprobante' => (int) $request->input('numerocomprobante'),
            'fechacomprobante' => $request->input('fechacomprobante'),
            'fechaiva' => $request->input('fechaiva'),
            'fechavencimiento' => $request->input('fechavencimiento'),
            'fecharecepcion' => $request->input('fecharecepcion'),
            'subtotal' => (float) $request->input('subtotal', 0),
            'total' => (float) $request->input('total', 0),
            'moneda_id' => (int) $request->input('moneda_id', 1),
            'cotizacion' => (float) $request->input('cotizacion', 1),
            'numerocae' => $request->input('numerocae'),
            'tipo_autorizacion' => ComprobanteProveedorTipoAutorizacion::normalizar(
                $request->input('tipo_autorizacion')
            ) ?? (filled($request->input('numerocae')) ? ComprobanteProveedorTipoAutorizacion::CAE : null),
            'fechavencimientocae' => $request->input('fechavencimientocae'),
            'es_fce' => $request->boolean('es_fce'),
            'leyenda' => $request->input('leyenda'),
            'modo_carga' => $modoCarga,
            'origen_entrada' => $origen,
            'pararevisar' => $request->boolean('pararevisar'),
        ];
    }

    private function sincronizarConceptos(Request $request, Comprobante_Proveedor $comprobante): void
    {
        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::lineasDesdeArrays(
            $request->input('concepto_ivacompra_ids', []),
            $request->input('montos', []),
            $request->input('cuentacontabledebe_ids', []),
        );
        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineas);
        $fechaYmd = null;
        if ($comprobante->fechacomprobante instanceof \DateTimeInterface) {
            $fechaYmd = $comprobante->fechacomprobante->format('Y-m-d');
        } elseif (filled($comprobante->fechacomprobante ?? null)) {
            $fechaYmd = substr((string) $comprobante->fechacomprobante, 0, 10);
        }
        $oc = $comprobante->ordencompras
            ?? ((int) ($comprobante->ordencompra_id ?? 0) > 0
                ? Ordencompra::query()->find((int) $comprobante->ordencompra_id)
                : null);
        $lineas = OrdencompraContratoRutaFacturaSupport::rellenarCuentaManualEnLineas($oc, $lineas, $fechaYmd);

            foreach ($lineas as $i => $linea) {
            $conceptoId = (int) ($linea['concepto_ivacompra_id'] ?? 0);
            if ($conceptoId <= 0) {
                continue;
            }

            $concepto = $this->conceptoIvacompraRepository->find($conceptoId);
            if (! $concepto) {
                throw new RuntimeException('Concepto IVA compra id «'.$conceptoId.'» inexistente.');
            }

            $this->conceptoRepository->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'concepto_ivacompra_id' => $concepto->id,
                'orden' => $i + 1,
                'monto' => $linea['monto'] ?? 0,
                'cuentacontabledebe_id' => ! empty($linea['cuentacontabledebe_id'])
                    ? (int) $linea['cuentacontabledebe_id']
                    : null,
            ]);
        }
    }

    private function sincronizarArticulos(Request $request, Comprobante_Proveedor $comprobante): void
    {
        $input = $request->all();
        if (! ComprobanteProveedorLineasFacturaSupport::requestTraeLineas($input)) {
            return;
        }

        Comprobante_Proveedor_Articulo::query()
            ->where('comprobante_proveedor_id', $comprobante->id)
            ->delete();

        $lineas = ComprobanteProveedorLineasFacturaSupport::desdeArraysRequest($input);
        foreach ($lineas as $i => $linea) {
            $sku = (string) ($linea['sku'] ?? '');
            $articuloId = (int) ($linea['articulo_id'] ?? 0) ?: null;
            if ($articuloId === null && $sku !== '') {
                $articuloId = ArticuloSkuMatchSupport::resolverCanonico($sku)?->id;
            }

            Comprobante_Proveedor_Articulo::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'orden' => $i + 1,
                'articulo_id' => $articuloId,
                'sku' => $sku !== '' ? $sku : null,
                'codigo_proveedor' => filled($linea['codigo_proveedor'] ?? null) ? $linea['codigo_proveedor'] : null,
                'descripcion' => filled($linea['descripcion'] ?? null) ? $linea['descripcion'] : null,
                'cantidad' => (float) ($linea['cantidad'] ?? 0),
                'precio_unitario' => (float) ($linea['precio_unitario'] ?? 0),
            ]);
        }
    }

    private function sincronizarCuotas(Request $request, Comprobante_Proveedor $comprobante): void
    {
        $numeros = $request->input('cuota_numero', []);
        $vencimientos = $request->input('cuota_fechavencimiento', []);
        $montos = $request->input('cuota_monto', []);
        $monedaIds = $request->input('cuota_moneda_id', []);
        $cotizaciones = $request->input('cuota_cotizacion', []);
        $formapagoIds = $request->input('cuota_formapago_id', []);
        $detalles = $request->input('cuota_detalle', []);
        $ocCuotaIds = $request->input('cuota_ordencompra_comprobante_cuota_id', []);

        $cuotas = [];
        for ($i = 0; $i < count($numeros); $i++) {
            if (trim((string) ($vencimientos[$i] ?? '')) === '') {
                continue;
            }
            $cuotas[] = [
                'numero_cuota' => (int) ($numeros[$i] ?? ($i + 1)),
                'fechavencimiento' => $vencimientos[$i],
                'monto' => (float) ($montos[$i] ?? 0),
                'moneda_id' => (int) ($monedaIds[$i] ?? $comprobante->moneda_id ?? 1),
                'cotizacion' => isset($cotizaciones[$i]) ? (float) $cotizaciones[$i] : null,
                'formapago_id' => (int) ($formapagoIds[$i] ?? 1),
                'detalle' => $detalles[$i] ?? null,
                'ordencompra_comprobante_cuota_id' => (int) ($ocCuotaIds[$i] ?? 0) ?: null,
            ];
        }

        if ($cuotas === [] && $comprobante->ordencompra_id) {
            $ordencompra = $comprobante->ordencompras ?? $comprobante->ordencompras()->first();
            if ($ordencompra) {
                $meta = $this->condicionPagoDesdeOc->resolverDesdeOrdencompra(
                    $ordencompra,
                    $comprobante->ordencompra_comprobante_id,
                    (float) $comprobante->total,
                    $comprobante->fechacomprobante?->format('Y-m-d') ?? now()->format('Y-m-d'),
                    (int) ($comprobante->moneda_id ?: 1),
                    (float) ($comprobante->cotizacion ?: 1),
                );
                $cuotas = $meta['cuotas'];
            }
        }

        $this->persistirCuotasDesdeArray($comprobante, $cuotas);
    }

    /** @param list<array<string, mixed>> $cuotas */
    private function persistirCuotasDesdeArray(Comprobante_Proveedor $comprobante, array $cuotas): void
    {
        $monedaFacturaId = (int) ($comprobante->moneda_id ?: 1);
        $fechaFactura = $comprobante->fechacomprobante?->format('Y-m-d') ?? now()->format('Y-m-d');
        $cotizacionFactura = ComprobanteProveedorMonedaMotor::cotizacionValida(
            $monedaFacturaId,
            $comprobante->cotizacion,
            $fechaFactura,
            'la factura del proveedor',
        );

        foreach ($cuotas as $cuota) {
            $monedaCuotaId = (int) ($cuota['moneda_id'] ?? $monedaFacturaId);
            $cotizacionCuota = isset($cuota['cotizacion']) ? (float) $cuota['cotizacion'] : $cotizacionFactura;
            $monto = (float) ($cuota['monto'] ?? 0);
            if ($monto > 0 && $monedaCuotaId !== $monedaFacturaId) {
                $monto = ComprobanteProveedorImporteComparacionComSupport::desdeRecepcionAFactura(
                    $monto,
                    $monedaCuotaId,
                    $cotizacionCuota,
                    $monedaFacturaId,
                    $cotizacionFactura,
                    $fechaFactura,
                    $fechaFactura,
                );
            }

            Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'numero_cuota' => (int) ($cuota['numero_cuota'] ?? 1),
                'fechavencimiento' => $cuota['fechavencimiento'],
                'monto' => $monto,
                'moneda_id' => $monedaFacturaId,
                'cotizacion' => $cotizacionFactura,
                'formapago_id' => (int) ($cuota['formapago_id'] ?? 1),
                'detalle' => $cuota['detalle'] ?? null,
                'ordencompra_comprobante_cuota_id' => $cuota['ordencompra_comprobante_cuota_id'] ?? null,
            ]);
        }
    }

    private function registrarEstadoInicial(Comprobante_Proveedor $comprobante): void
    {
        Comprobante_Proveedor_Estado::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'fecha' => now()->format('Y-m-d'),
            'estado' => $comprobante->estado,
            'usuario_id' => Auth::id(),
            'observacion' => 'Alta en ERP',
        ]);
    }

    private function sincronizarRecepciones(Request $request, Comprobante_Proveedor $comprobante): void
    {
        $modoCarga = (string) $request->input('modo_carga', $comprobante->modo_carga ?? '');
        if ($modoCarga !== ComprobanteProveedorModoCarga::ASIGNA_RECEPCION) {
            Comprobante_Proveedor_Recepcion::query()
                ->where('comprobante_proveedor_id', $comprobante->id)
                ->delete();

            return;
        }

        $ordencompraId = (int) ($comprobante->ordencompra_id ?? 0);
        if ($ordencompraId <= 0) {
            throw new RuntimeException('Modo factura contra recepción requiere una orden de compra vinculada.');
        }

        $ids = $request->input('recepcion_proveedor_ids', []);
        if (! is_array($ids) || $ids === []) {
            throw new RuntimeException('Debe seleccionar al menos una recepción COM para asociar a la factura del legajo.');
        }

        $this->recepcionesSupport->sincronizar(
            (int) $comprobante->id,
            $ordencompraId,
            $ids,
            $this->contextoLegajoDesdeComprobante($comprobante),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function ejecutarControlesDesdeRequest(Request $request, array &$payload, ?int $excluirComprobanteId): void
    {
        $ordencompraId = (int) ($payload['ordencompra_id'] ?? 0);
        $ordencompra = $ordencompraId > 0 ? Ordencompra::query()->find($ordencompraId) : null;

        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::lineasDesdeArrays(
            $request->input('concepto_ivacompra_ids', []),
            $request->input('montos', []),
            $request->input('cuentacontabledebe_ids', []),
        );
        $fechaYmd = substr((string) ($payload['fechacomprobante'] ?? ''), 0, 10) ?: null;
        $lineas = OrdencompraContratoRutaFacturaSupport::rellenarCuentaManualEnLineas($ordencompra, $lineas, $fechaYmd);
        $conceptos = collect($lineas)->map(function (array $linea) {
            $concepto = $this->conceptoIvacompraRepository->find((int) ($linea['concepto_ivacompra_id'] ?? 0));

            return (object) [
                'concepto_ivacompra_id' => (int) ($linea['concepto_ivacompra_id'] ?? 0),
                'monto' => $linea['monto'] ?? 0,
                'cuentacontabledebe_id' => (int) ($linea['cuentacontabledebe_id'] ?? 0) ?: null,
                'concepto_ivacompras' => $concepto,
            ];
        });

        $condicionivaId = Proveedor::query()
            ->whereKey((int) ($payload['proveedor_id'] ?? 0))
            ->value('condicioniva_id');

        $modo = (string) ($payload['modo_carga'] ?? '');
        if ($ordencompra) {
            $tieneCom = $this->recepcionesSupport
                ->listarDisponibles($ordencompraId, $excluirComprobanteId)
                ->isNotEmpty();
            if (! $tieneCom) {
                $tieneCom = $this->recepcionesSupport->listarSinFacturarEnLegajo(
                    (int) $ordencompra->proveedor_id,
                    (int) $ordencompra->empresa_id,
                    $ordencompra->sector_legajocompra_id ? (int) $ordencompra->sector_legajocompra_id : null,
                    $excluirComprobanteId,
                )->isNotEmpty();
            }
            $politica = ComprobanteProveedorFlujoOcComFacSupport::resolverPolitica(
                $ordencompra,
                $tieneCom,
                (string) ($payload['fechacomprobante'] ?? now()->format('Y-m-d'))
            );
            $modo = ComprobanteProveedorFlujoOcComFacSupport::modoCargaSugerido($politica, $modo);
            $payload['modo_carga'] = $modo;
        }

        $resultadoControles = $this->controlesLegajo->validarYAplicar(
            $ordencompra,
            $modo,
            $request->input('recepcion_proveedor_ids', []),
            (float) ($payload['cotizacion'] ?? 1),
            (int) ($payload['moneda_id'] ?? 1),
            (string) ($payload['fechacomprobante'] ?? now()->format('Y-m-d')),
            (string) ($payload['letra'] ?? ''),
            $condicionivaId !== null ? (int) $condicionivaId : null,
            (float) ($payload['total'] ?? 0),
            (float) ($payload['subtotal'] ?? 0),
            $conceptos,
            $excluirComprobanteId,
            true,
            ComprobanteProveedorLineasFacturaSupport::requestTraeLineas($request->all())
                ? ComprobanteProveedorLineasFacturaSupport::desdeArraysRequest($request->all())
                : ($excluirComprobanteId
                    ? ComprobanteProveedorLineasFacturaSupport::desdeComprobante(
                        $this->comprobanteRepository->find($excluirComprobanteId)
                    )
                    : collect()),
        );
        $this->aplicarResultadoControles($resultadoControles);

        $idsEfectivos = $resultadoControles['recepcion_ids_efectivos'] ?? [];
        if (is_array($idsEfectivos) && $idsEfectivos !== []) {
            $request->merge(['recepcion_proveedor_ids' => $idsEfectivos]);
        }

        $this->aplicarResultadoCompliance(
            $this->complianceValidacion->validarAlGrabar($payload, $lineas)
        );
    }

    /** @param array{ok: bool, avisos: list<string>, errores: list<string>} $resultado */
    private function aplicarResultadoControles(array $resultado): void
    {
        $this->ultimosAvisosControles = array_values(array_filter(
            array_map('strval', $resultado['avisos'] ?? [])
        ));
        if (! ($resultado['ok'] ?? false)) {
            throw new RuntimeException(implode(' ', $resultado['errores'] ?? ['Control de legajo rechazado.']));
        }
    }

    /** @param array{ok: bool, avisos: list<string>, errores: list<string>} $resultado */
    private function aplicarResultadoCompliance(array $resultado): void
    {
        $avisos = array_values(array_filter(array_map('strval', $resultado['avisos'] ?? [])));
        if ($avisos !== []) {
            $this->ultimosAvisosControles = array_values(array_unique(array_merge(
                $this->ultimosAvisosControles,
                $avisos
            )));
        }

        if (! ($resultado['ok'] ?? false)) {
            throw new RuntimeException(implode(' ', $resultado['errores'] ?? ['Validación de compliance rechazada.']));
        }
    }

    private function vincularArchivoPrecarga(Comprobante_Proveedor $comprobante): void
    {
        if (! $comprobante->precarga_comprobante_proveedor_id) {
            return;
        }

        $precarga = $comprobante->precarga_comprobante_proveedores
            ?? $comprobante->precarga_comprobante_proveedores()->first();

        if (! $precarga || ! filled($precarga->rutaalmacenamiento)) {
            return;
        }

        $yaExiste = Comprobante_Proveedor_Archivo::query()
            ->where('comprobante_proveedor_id', $comprobante->id)
            ->where('tipo', ComprobanteProveedorArchivoTipos::ORIGEN_IA)
            ->exists();

        if ($yaExiste) {
            return;
        }

        Comprobante_Proveedor_Archivo::query()->create([
            'comprobante_proveedor_id' => $comprobante->id,
            'tipo' => ComprobanteProveedorArchivoTipos::ORIGEN_IA,
            'nombrearchivo' => basename($precarga->rutaalmacenamiento),
            'origen_externo' => true,
            'ruta_externa' => $precarga->rutaalmacenamiento,
            'precarga_comprobante_proveedor_id' => $precarga->id,
        ]);
    }

    /**
     * Al vincular una factura a un comprobante a venir de la OC, lo marca como ya cargado
     * para que no se vuelva a ofrecer en el prefill.
     */
    private function marcarOrdencompraComprobanteCargado(Comprobante_Proveedor $comprobante): void
    {
        $ocCompId = (int) ($comprobante->ordencompra_comprobante_id ?? 0);
        if ($ocCompId <= 0) {
            return;
        }

        Ordencompra_Comprobante::query()
            ->whereKey($ocCompId)
            ->where('estado', '!=', OrdencompraComprobanteEstados::CARGADO)
            ->update(['estado' => OrdencompraComprobanteEstados::CARGADO]);
    }

    private function marcarPrecargaGenerada(?int $precargaId): void
    {
        if (! $precargaId || $precargaId <= 0) {
            return;
        }

        Precarga_Comprobante_Proveedor::query()
            ->whereKey($precargaId)
            ->where('estado', PrecargaComprobanteEstados::PENDIENTE)
            ->update(['estado' => PrecargaComprobanteEstados::GENERADA]);
    }

    private function origenComprobanteDesdePrecarga(int $precargaId): string
    {
        $origen = Precarga_Comprobante_Proveedor::query()
            ->where('id', $precargaId)
            ->value('origen_entrada');

        return PrecargaComprobanteOrigenEntrada::origenComprobanteDesdePrecarga(
            is_string($origen) ? $origen : null
        );
    }
}
