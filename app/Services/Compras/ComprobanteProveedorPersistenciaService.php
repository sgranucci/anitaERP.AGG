<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Archivo;
use App\Models\Compras\Comprobante_Proveedor_Cuota;
use App\Models\Compras\Comprobante_Proveedor_Estado;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Repositories\Compras\Comprobante_Proveedor_ArchivoRepositoryInterface;
use App\Repositories\Compras\Comprobante_Proveedor_ConceptoRepositoryInterface;
use App\Repositories\Compras\Comprobante_ProveedorRepositoryInterface;
use App\Repositories\Compras\Concepto_IvacompraRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorArchivoTipos;
use App\Support\Compras\ComprobanteProveedorConceptosIvaCoherenciaSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class ComprobanteProveedorPersistenciaService
{
    public function __construct(
        private Comprobante_ProveedorRepositoryInterface $comprobanteRepository,
        private Comprobante_Proveedor_ConceptoRepositoryInterface $conceptoRepository,
        private Concepto_IvacompraRepositoryInterface $conceptoIvacompraRepository,
        private ComprobanteProveedorPrefillService $prefillService,
        private ComprobanteProveedorCondicionPagoDesdeOcService $condicionPagoDesdeOc,
        private ComprobanteProveedorRecepcionesSupport $recepcionesSupport,
        private Comprobante_Proveedor_ArchivoRepositoryInterface $archivoRepository,
    ) {}

    public function crearDesdeRequest(Request $request): Comprobante_Proveedor
    {
        $payload = $this->armarPayloadCabecera($request);
        $payload['creousuario_id'] = Auth::id();
        $payload['estado'] = ComprobanteProveedorEstados::BORRADOR;

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
        );

        $comprobante = $this->comprobanteRepository->create($payload);

        $this->sincronizarConceptos($request, $comprobante);
        $this->sincronizarCuotas($request, $comprobante);
        $this->sincronizarRecepciones($request, $comprobante);
        $this->registrarEstadoInicial($comprobante);
        $this->vincularArchivoPrecarga($comprobante);
        $this->archivoRepository->sincronizarDesdeRequest($request, (int) $comprobante->id);

        return $comprobante->fresh([
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_archivos',
        ]);
    }

    public function actualizarDesdeRequest(Request $request, int $id): Comprobante_Proveedor
    {
        $comprobante = $this->comprobanteRepository->find($id);
        if (! $comprobante) {
            throw new RuntimeException('Comprobante de proveedor inexistente.');
        }

        if (! in_array($comprobante->estado, ComprobanteProveedorEstados::editables(), true)) {
            throw new RuntimeException('El comprobante no admite edición en estado «'.$comprobante->estado.'».');
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
        );

        $this->comprobanteRepository->update($payload, $id);

        $comprobante = $this->comprobanteRepository->find($id);
        $this->conceptoRepository->deletePorComprobanteProveedor($id);
        $this->sincronizarConceptos($request, $comprobante);
        Comprobante_Proveedor_Cuota::query()->where('comprobante_proveedor_id', $id)->delete();
        $this->sincronizarCuotas($request, $comprobante);
        $this->sincronizarRecepciones($request, $comprobante);
        $this->archivoRepository->sincronizarDesdeRequest($request, $id);

        return $comprobante->fresh([
            'comprobante_proveedor_conceptos',
            'comprobante_proveedor_cuotas',
            'comprobante_proveedor_archivos',
        ]);
    }

    public function generarBorradorDesdePrecarga(int $precargaId): Comprobante_Proveedor
    {
        $existente = Comprobante_Proveedor::query()
            ->where('precarga_comprobante_proveedor_id', $precargaId)
            ->whereNull('deleted_at')
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
        );

        $comprobante = $this->comprobanteRepository->create($payload);

        foreach ($prefill['conceptos'] as $i => $concepto) {
            $this->conceptoRepository->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'concepto_ivacompra_id' => $concepto->concepto_ivacompra_id,
                'orden' => $i + 1,
                'monto' => $concepto->monto,
            ]);
        }

        $this->persistirCuotasDesdeArray($comprobante, $prefill['cuotas']);
        $this->vincularRecepcionesDesdePrefill($comprobante, $prefill);
        $this->registrarEstadoInicial($comprobante);
        $this->vincularArchivoPrecarga($comprobante);

        return $comprobante->fresh([
            'comprobante_proveedor_recepciones',
            'comprobante_proveedor_conceptos',
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
        );
        $lineas = ComprobanteProveedorConceptosIvaCoherenciaSupport::normalizarYValidar($lineas);

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
                );
                $cuotas = $meta['cuotas'];
            }
        }

        $this->persistirCuotasDesdeArray($comprobante, $cuotas);
    }

    /** @param list<array<string, mixed>> $cuotas */
    private function persistirCuotasDesdeArray(Comprobante_Proveedor $comprobante, array $cuotas): void
    {
        foreach ($cuotas as $cuota) {
            Comprobante_Proveedor_Cuota::query()->create([
                'comprobante_proveedor_id' => $comprobante->id,
                'numero_cuota' => (int) ($cuota['numero_cuota'] ?? 1),
                'fechavencimiento' => $cuota['fechavencimiento'],
                'monto' => (float) ($cuota['monto'] ?? 0),
                'moneda_id' => (int) ($cuota['moneda_id'] ?? $comprobante->moneda_id ?? 1),
                'cotizacion' => $cuota['cotizacion'] ?? null,
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

        $this->recepcionesSupport->sincronizar(
            (int) $comprobante->id,
            $ordencompraId,
            $request->input('recepcion_proveedor_ids', []),
            $this->contextoLegajoDesdeComprobante($comprobante),
        );
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
