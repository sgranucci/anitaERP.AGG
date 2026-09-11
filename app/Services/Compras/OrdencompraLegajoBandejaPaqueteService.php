<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraLegajoDocumentoTipoSupport;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Factura + COM del legajo (la OC) y asignación persistida para que CxP cargue.
 */
class OrdencompraLegajoBandejaPaqueteService
{
    public function __construct(
        private PrecargaFacturaScanPathResolver $scanPathResolver,
        private OrdencompraLegajoFacturaPdfService $facturaPdfService,
    ) {
    }

    public function encontrarOcVisible(int $id): Ordencompra
    {
        return $this->encontrarOc($id, false);
    }

    /**
     * Lectura global (seguimiento): empresas asignadas, sin recorte por sector de legajo.
     */
    public function encontrarOcConsulta(int $id): Ordencompra
    {
        return $this->encontrarOc($id, true);
    }

    private function encontrarOc(int $id, bool $consultaGlobal): Ordencompra
    {
        $query = Ordencompra::query()->whereKey($id);
        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        if (! $consultaGlobal) {
            OrdencompraSectorVisibilidadSupport::aplicarFiltro($query);
        }
        $oc = $query->with('empresas:id,codigo,nombre')->first();
        if (! $oc) {
            abort(404, 'Orden de compra no encontrada.');
        }

        return $oc;
    }

    /**
     * @return array<string, mixed>
     */
    public function paquete(Ordencompra $oc): array
    {
        $oc->loadMissing('empresas:id,codigo,nombre');
        $this->materializarPdfsScanAnita($oc);
        $facturas = $this->facturasDelLegajo($oc);
        $facturas = array_merge($facturas, $this->scansAnitaSinPrecarga($oc, $facturas));
        $tiposOpciones = $this->tiposOpcionesCorreccion($oc);
        $coms = $this->comsDelLegajo($oc);
        $devoluciones = $this->devolucionesDelLegajo($oc);
        $precargaIds = [];
        foreach ($facturas as $f) {
            if (($f['origen'] ?? 'precarga') === 'precarga') {
                $precargaIds[] = (int) $f['id'];
            }
        }
        $asignadas = $this->asignacionesPorPrecarga($precargaIds);
        $comprobantes = $this->comprobantesDelLegajo($oc, $precargaIds);
        $facturas = $this->marcarFacturasCargadasEnCxp($facturas, $comprobantes);
        $facturas = $this->fusionarComprobantesEnFacturas($facturas, $comprobantes);
        $pagos = $this->pagosDeComprobantes(array_map(static fn (array $c) => (int) $c['id'], $comprobantes));
        $pendientes = OrdencompraEnvioCuentasAPagarGateSupport::documentosPendientesCarga($oc);
        $siguiente = $pendientes[0] ?? null;
        $enCxp = OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar((int) ($oc->sector_legajocompra_id ?? 0));

        return [
            'ordencompra_id' => (int) $oc->id,
            'numero' => (string) $oc->numeroordencompra,
            'es_anticipada' => \App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc),
            'tratamiento' => (string) ($oc->tratamiento ?? ''),
            'facturas' => $facturas,
            'tipos_opciones' => $tiposOpciones,
            'coms' => $coms,
            'devoluciones' => $devoluciones,
            'asignadas' => $asignadas,
            'comprobantes' => $comprobantes,
            'pagos' => $pagos,
            'pendientes_carga' => count($pendientes),
            'siguiente_pendiente' => $siguiente,
            'url_cargar_cxp' => ($enCxp && $siguiente !== null)
                ? route('crear_comprobante_proveedor', array_filter([
                    'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
                    'ordencompra_id' => (int) $oc->id,
                    'precarga_id' => ($siguiente['precarga_id'] ?? null) ?: null,
                ]))
                : null,
            'url_oc' => can('editar-ordencompra', false)
                ? route('editar_ordencompra', ['id' => (int) $oc->id])
                : route('solo_consulta_ordencompra', ['id' => (int) $oc->id]),
        ];
    }

    /**
     * @param  list<int>  $recepcionIds
     */
    public function asignar(Ordencompra $oc, int|string $facturaRef, array $recepcionIds): void
    {
        $this->asignarMultiples($oc, [[
            'precarga_id' => $facturaRef,
            'recepcion_ids' => $recepcionIds,
        ]]);
    }

    /**
     * @param  list<array{precarga_id: int|string, recepcion_ids?: list<mixed>}>  $asignaciones
     */
    public function asignarMultiples(Ordencompra $oc, array $asignaciones): void
    {
        if ($asignaciones === []) {
            throw ValidationException::withMessages([
                'asignaciones' => 'Indique al menos un comprobante para asignar COM.',
            ]);
        }

        $normalizadas = [];
        foreach ($asignaciones as $item) {
            $ref = $item['precarga_id'] ?? null;
            if ($ref === null || $ref === '') {
                continue;
            }
            $ids = array_values(array_unique(array_filter(
                array_map(static fn ($id) => (int) $id, (array) ($item['recepcion_ids'] ?? [])),
                static fn (int $id) => $id > 0
            )));
            $precargaId = (int) $this->resolverPrecargaParaAsignacion($oc, $ref)->id;
            $normalizadas[$precargaId] = $ids;
        }
        if ($normalizadas === []) {
            throw ValidationException::withMessages([
                'asignaciones' => 'No se pudo resolver ningún comprobante del legajo.',
            ]);
        }

        $todosIds = [];
        foreach ($normalizadas as $ids) {
            foreach ($ids as $id) {
                $todosIds[] = $id;
            }
        }
        $todosIds = array_values(array_unique($todosIds));
        if ($todosIds !== []) {
            $validas = Recepcion_Proveedor::query()
                ->where('ordencompra_id', $oc->id)
                ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
                ->whereIn('id', $todosIds)
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->all();
            $faltan = array_values(array_diff($todosIds, $validas));
            if ($faltan !== []) {
                throw ValidationException::withMessages([
                    'recepcion_ids' => 'Hay COM que no pertenecen a esta OC o no están confirmadas.',
                ]);
            }
        }

        DB::transaction(function () use ($normalizadas) {
            foreach ($normalizadas as $precargaId => $ids) {
                Precarga_Comprobante_Proveedor_Recepcion::query()
                    ->where('precarga_comprobante_proveedor_id', $precargaId)
                    ->delete();
                foreach ($ids as $orden => $recepcionId) {
                    Precarga_Comprobante_Proveedor_Recepcion::query()->create([
                        'precarga_comprobante_proveedor_id' => $precargaId,
                        'recepcion_proveedor_id' => $recepcionId,
                        'orden' => $orden + 1,
                    ]);
                }
            }
        });
    }

    public function assertPrecargaDelLegajo(Ordencompra $oc, int $precargaId): Precarga_Comprobante_Proveedor
    {
        if ($precargaId <= 0) {
            abort(404, 'La factura no pertenece a este legajo.');
        }
        $precarga = Precarga_Comprobante_Proveedor::query()->find($precargaId);
        if (! $precarga || ! $this->precargaPerteneceAlLegajo($oc, $precarga)) {
            abort(404, 'La factura no pertenece a este legajo.');
        }

        return $precarga;
    }

    public function assertComDelLegajo(Ordencompra $oc, int $recepcionId): Recepcion_Proveedor
    {
        $com = Recepcion_Proveedor::query()
            ->whereKey($recepcionId)
            ->where('ordencompra_id', $oc->id)
            ->first();
        if (! $com) {
            abort(404, 'La COM no pertenece a este legajo.');
        }

        return $com;
    }

    public function rutaFacturaPdf(Precarga_Comprobante_Proveedor $precarga): ?string
    {
        $ruta = trim((string) $precarga->rutaalmacenamiento);
        if ($ruta === '') {
            return null;
        }

        return $this->scanPathResolver->resolve($ruta);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function facturasDelLegajo(Ordencompra $oc): array
    {
        $numero = trim((string) $oc->numeroordencompra);
        $empresaId = (int) $oc->empresa_id;
        if ($numero === '' || $empresaId <= 0) {
            return [];
        }

        $rows = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->get([
                'id', 'letra', 'sucursal', 'numerocomprobante', 'fechafactura',
                'total', 'rutaalmacenamiento', 'estado', 'origen_entrada',
                'tipotransaccion_compra_id',
            ]);

        $out = [];
        foreach ($rows as $pre) {
            $id = (int) $pre->id;
            $pre->loadMissing('tipotransaccion_compras:id,abreviatura,codigoafip');
            $tipo = OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($pre);
            $abrev = strtoupper(trim((string) ($pre->tipotransaccion_compras->abreviatura ?? '')));
            $numero = trim(sprintf(
                '%s %04d-%08d',
                $pre->letra ?: 'FC',
                (int) $pre->sucursal,
                (int) $pre->numerocomprobante
            ));
            $base = $abrev !== '' ? $abrev.' '.$numero : $numero;
            $out[] = [
                'id' => $id,
                'origen' => 'precarga',
                'origen_label' => PrecargaComprobanteOrigenEntrada::etiqueta($pre->origen_entrada ?? null),
                'tipo' => $tipo,
                'tipo_abrev' => $abrev !== '' ? $abrev : $tipo,
                'tipo_label' => $abrev !== '' ? $abrev : OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo),
                'exige_com' => OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipo),
                'etiqueta' => OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $base),
                'letra' => (string) ($pre->letra ?? ''),
                'sucursal' => (int) ($pre->sucursal ?? 0),
                'numerocomprobante' => (int) ($pre->numerocomprobante ?? 0),
                'fecha' => $pre->fechafactura ? $pre->fechafactura->format('d/m/Y') : '',
                'total' => $pre->total !== null ? (float) $pre->total : null,
                'estado' => (string) ($pre->estado ?? ''),
                'url_pdf' => route('ordencompra_legajo_bandeja_factura_pdf', [
                    'id' => (int) $oc->id,
                    'precarga' => $id,
                    'inline' => 1,
                ]),
                'url_cargar_cxp' => route('crear_comprobante_proveedor', [
                    'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
                    'ordencompra_id' => (int) $oc->id,
                    'precarga_id' => $id,
                ]),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function comsDelLegajo(Ordencompra $oc): array
    {
        $rows = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'numerorecepcion', 'fecha', 'estado', 'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro']);

        $out = [];
        foreach ($rows as $rec) {
            $id = (int) $rec->id;
            $out[] = [
                'id' => $id,
                'documento' => $this->documentoCom($rec),
                'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '',
                'estado' => (string) $rec->estado,
                'confirmada' => $rec->estado === Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'url_pdf' => route('ordencompra_legajo_bandeja_com_pdf', [
                    'id' => (int) $oc->id,
                    'recepcion' => $id,
                    'inline' => 1,
                ]),
                'url_editar' => route('editar_recepcion_proveedor', ['id' => $id]),
            ];
        }

        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function devolucionesDelLegajo(Ordencompra $oc): array
    {
        $rows = Recepcion_Proveedor::query()
            ->where('ordencompra_id', $oc->id)
            ->where('tipo', Recepcion_Proveedor::TIPO_DEVOLUCION)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'numerorecepcion', 'fecha', 'estado', 'recepcion_referencia_id']);

        $out = [];
        foreach ($rows as $rec) {
            $id = (int) $rec->id;
            $out[] = [
                'id' => $id,
                'documento' => filled($rec->numerorecepcion)
                    ? 'DEV Nº '.$rec->numerorecepcion
                    : 'DEV #'.$id,
                'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '',
                'estado' => (string) $rec->estado,
                'recepcion_referencia_id' => (int) ($rec->recepcion_referencia_id ?? 0) ?: null,
                'url_editar' => route('editar_recepcion_proveedor', ['id' => $id]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return array<int, list<int>>
     */
    public function asignacionesPorPrecarga(array $precargaIds): array
    {
        if ($precargaIds === [] || ! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return [];
        }

        $out = [];
        $rows = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->whereIn('precarga_comprobante_proveedor_id', $precargaIds)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['precarga_comprobante_proveedor_id', 'recepcion_proveedor_id']);
        foreach ($rows as $row) {
            $preId = (int) $row->precarga_comprobante_proveedor_id;
            $out[$preId] ??= [];
            $out[$preId][] = (int) $row->recepcion_proveedor_id;
        }

        return $out;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return list<array<string, mixed>>
     */
    private function comprobantesDelLegajo(Ordencompra $oc, array $precargaIds): array
    {
        $query = Comprobante_Proveedor::query()
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($oc, $precargaIds) {
                $q->where('ordencompra_id', $oc->id);
                if ($precargaIds !== []) {
                    $q->orWhereIn('precarga_comprobante_proveedor_id', $precargaIds);
                }
            })
            ->orderByDesc('id');

        $out = [];
        foreach ($query->with('tipotransaccion_compras:id,abreviatura,codigoafip')->get([
            'id', 'letra', 'sucursal', 'numerocomprobante', 'total', 'estado',
            'precarga_comprobante_proveedor_id', 'tipotransaccion_compra_id',
            'fechacomprobante', 'origen_entrada',
        ]) as $cp) {
            $tipo = OrdencompraLegajoDocumentoTipoSupport::desdeAbreviatura(
                $cp->tipotransaccion_compras->abreviatura ?? null,
                $cp->tipotransaccion_compras->codigoafip !== null
                    ? (string) $cp->tipotransaccion_compras->codigoafip
                    : null
            );
            $numero = trim(sprintf(
                '%s %04d-%08d',
                $cp->letra ?: 'FC',
                (int) $cp->sucursal,
                (int) $cp->numerocomprobante
            ));
            $etiqueta = OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $numero);
            $out[] = [
                'id' => (int) $cp->id,
                'precarga_id' => (int) ($cp->precarga_comprobante_proveedor_id ?? 0) ?: null,
                'letra' => (string) ($cp->letra ?? ''),
                'sucursal' => (int) ($cp->sucursal ?? 0),
                'numerocomprobante' => (int) ($cp->numerocomprobante ?? 0),
                'tipo' => $tipo,
                'tipo_label' => OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo),
                'etiqueta' => $etiqueta,
                'fecha' => $cp->fechacomprobante ? $cp->fechacomprobante->format('d/m/Y') : '',
                'total' => $cp->total !== null ? (float) $cp->total : null,
                'estado' => (string) ($cp->estado ?? ''),
                'origen_entrada' => (string) ($cp->origen_entrada ?? ''),
                'origen_label' => ComprobanteProveedorOrigenEntrada::etiqueta(
                    (string) ($cp->origen_entrada ?? ComprobanteProveedorOrigenEntrada::PRECARGA)
                ),
                'url' => route('editar_comprobante_proveedor', ['id' => (int) $cp->id]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $comprobanteIds
     * @return list<array<string, mixed>>
     */
    private function pagosDeComprobantes(array $comprobanteIds): array
    {
        if ($comprobanteIds === []) {
            return [];
        }
        $rows = Proveedor_Cuentacorriente::query()
            ->with(['pagoproveedores:id,tipocomprobante,letra,sucursal,numerotransaccion'])
            ->whereIn('comprobante_proveedor_id', $comprobanteIds)
            ->where('pagoproveedor_id', '>', 0)
            ->orderByDesc('id')
            ->get();
        $out = [];
        $vistos = [];
        foreach ($rows as $row) {
            $pagoId = (int) $row->pagoproveedor_id;
            if ($pagoId <= 0 || isset($vistos[$pagoId])) {
                continue;
            }
            $vistos[$pagoId] = true;
            $pago = $row->pagoproveedores;
            $out[] = [
                'id' => $pagoId,
                'etiqueta' => $pago ? $pago->etiquetaComprobante() : ('OP #'.$pagoId),
                'url' => route('editar_pagoproveedor', ['id' => $pagoId]),
            ];
        }

        return $out;
    }

    public function resolverPrecargaParaAsignacion(Ordencompra $oc, int|string $facturaRef): Precarga_Comprobante_Proveedor
    {
        $ref = trim((string) $facturaRef);
        if (preg_match('/^anita-(\d+)$/i', $ref, $m)) {
            return $this->precargaDesdeFacturaAnita($oc, (int) $m[1]);
        }

        return $this->assertPrecargaDelLegajo($oc, (int) $ref);
    }

    private function precargaDesdeFacturaAnita(Ordencompra $oc, int $documentoId): Precarga_Comprobante_Proveedor
    {
        $fila = OrdencompraLegajoAnitaScanFacturaSupport::filaDeOc($oc, $documentoId);
        if ($fila === null) {
            abort(404, 'La factura no pertenece a este legajo.');
        }

        $existente = $this->precargaDelLegajoCompatibleConScan($oc, $fila);
        if ($existente) {
            return $this->asegurarPdfScanEnPrecarga($oc, $existente, $documentoId, $fila);
        }

        $empresaId = (int) $oc->empresa_id;
        $proveedorId = (int) $oc->proveedor_id;
        $tipoGenerico = $this->tipoComprobanteDesdeScanAnita($fila);
        $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc, $tipoGenerico);
        if ($empresaId <= 0 || $proveedorId <= 0 || $tipoId <= 0) {
            throw ValidationException::withMessages([
                'precarga_id' => 'No se puede crear la precarga del legajo para asignar la COM (faltan empresa, proveedor o tipo de factura).',
            ]);
        }

        $letra = strtoupper(trim((string) ($fila['cletra'] ?? ''))) ?: 'A';
        $sucursal = (int) ($fila['isucursal'] ?? 0);
        $numero = (int) ($fila['inumero'] ?? 0);
        $fecha = $this->fechaYmdDesdeScanAnita((string) ($fila['ifecha'] ?? ''));

        if ($numero > 0) {
            $dup = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecarga(
                $empresaId,
                $tipoId,
                $letra,
                $sucursal,
                $numero,
                ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null),
            );
            if ($dup && $this->precargaPerteneceAlLegajo($oc, $dup)) {
                return $this->asegurarPdfScanEnPrecarga($oc, $dup, $documentoId, $fila);
            }
            if ($dup) {
                throw ValidationException::withMessages([
                    'precarga_id' => 'Ya existe una precarga con esa factura; no se puede asignar la COM desde este scan Anita.',
                ]);
            }
        }

        try {
            $monedaId = $this->facturaPdfService->monedaIdParaPrecarga($oc);
        } catch (\RuntimeException $e) {
            throw ValidationException::withMessages([
                'precarga_id' => $e->getMessage(),
            ]);
        }
        $moneda = Moneda::query()->whereKey($monedaId)->first();

        $precarga = Precarga_Comprobante_Proveedor::query()->create([
            'empresa_id' => $empresaId,
            'provincia_destino_id' => ComprobanteProveedorProvinciaDestinoSupport::DEFAULT_PROVINCIA_ID,
            'proveedor_id' => $proveedorId,
            'tipotransaccion_compra_id' => $tipoId,
            'letra' => $letra,
            'sucursal' => $sucursal,
            'numerocomprobante' => $numero,
            'fechafactura' => $fecha,
            'numeroordencompra' => (string) $oc->numeroordencompra,
            'subtotal' => 0,
            'total' => 0,
            'estado' => 'PENDIENTE',
            'origen_entrada' => PrecargaComprobanteOrigenEntrada::SCAN_ANITA,
            'pararevisar' => 1,
            'moneda' => strtoupper(trim((string) ($moneda->abreviatura ?: $moneda->nombre ?: 'PESOS'))),
            'moneda_id' => $monedaId,
            'cotizacion' => 1,
        ]);

        return $this->asegurarPdfScanEnPrecarga($oc, $precarga, $documentoId, $fila);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function precargaDelLegajoCompatibleConScan(Ordencompra $oc, array $fila): ?Precarga_Comprobante_Proveedor
    {
        $letra = strtoupper(trim((string) ($fila['cletra'] ?? '')));
        $sucursal = (int) ($fila['isucursal'] ?? 0);
        $numero = (int) ($fila['inumero'] ?? 0);

        $candidatas = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', (int) $oc->empresa_id)
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->orderByDesc('id')
            ->get();

        $delLegajo = $candidatas->filter(
            fn (Precarga_Comprobante_Proveedor $p) => $this->precargaPerteneceAlLegajo($oc, $p)
        );
        if ($delLegajo->isEmpty()) {
            return null;
        }

        if ($numero > 0) {
            $porNumero = $delLegajo->first(function (Precarga_Comprobante_Proveedor $p) use ($letra, $sucursal, $numero) {
                $mismaLetra = $letra === '' || strtoupper(trim((string) $p->letra)) === $letra;

                return $mismaLetra
                    && (int) $p->sucursal === $sucursal
                    && (int) $p->numerocomprobante === $numero;
            });
            if ($porNumero) {
                return $porNumero;
            }
        }

        // No reutilizar otra precarga del legajo: en multi-comprobante eso
        // pisa el tipo (p.ej. una ND queda como FIS por un scan FC distinto).
        return null;
    }

    private function fechaYmdDesdeScanAnita(string $ymd): string
    {
        $ymd = preg_replace('/\D+/', '', $ymd) ?? '';
        if (strlen($ymd) === 8) {
            return substr($ymd, 0, 4).'-'.substr($ymd, 4, 2).'-'.substr($ymd, 6, 2);
        }

        return now()->format('Y-m-d');
    }

    /**
     * @param  list<array<string, mixed>>  $precargas
     * @return list<array<string, mixed>>
     */
    private function scansAnitaSinPrecarga(Ordencompra $oc, array $precargas): array
    {
        $claves = [];
        foreach ($precargas as $pre) {
            $claves[$this->claveFacturaEtiqueta((string) ($pre['etiqueta'] ?? ''))] = true;
        }
        $out = [];
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $clave = $this->claveFacturaEtiqueta((string) ($scan['etiqueta'] ?? ''));
            if ($clave !== '' && isset($claves[$clave])) {
                continue;
            }
            $out[] = $scan;
        }

        return $out;
    }

    private function claveFacturaEtiqueta(string $etiqueta): string
    {
        $etiqueta = strtoupper(trim($etiqueta));
        if (preg_match('/([A-Z])\s+(\d{1,5})-(\d{1,8})/', $etiqueta, $m)) {
            return $m[1].'|'.((int) $m[2]).'|'.((int) $m[3]);
        }

        return $etiqueta;
    }

    private function materializarPdfsScanAnita(Ordencompra $oc): void
    {
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $docId = (int) ($scan['documento_id'] ?? 0);
            if ($docId <= 0) {
                continue;
            }
            try {
                $this->precargaDesdeFacturaAnita($oc, $docId);
            } catch (\Throwable $e) {
                Log::warning('bandeja.scan_pdf_precarga', [
                    'oc' => (int) $oc->id,
                    'documento' => $docId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function asegurarPdfScanEnPrecarga(
        Ordencompra $oc,
        Precarga_Comprobante_Proveedor $precarga,
        int $documentoId,
        array $fila,
    ): Precarga_Comprobante_Proveedor {
        $precarga = $this->marcarOrigenScanAnitaSiNoEsIa($precarga);
        $precarga = $this->alinearTipoPrecargaConScanAnita($oc, $precarga, $fila);
        $ruta = trim((string) ($precarga->rutaalmacenamiento ?? ''));
        if ($ruta !== '' && $this->scanPathResolver->resolve($ruta)) {
            return $precarga;
        }

        $origen = OrdencompraLegajoAnitaScanFacturaSupport::rutaPdf($documentoId);
        $proveedor = Proveedor::query()->find((int) $oc->proveedor_id);
        if ($origen === null || ! $proveedor) {
            throw ValidationException::withMessages([
                'precarga_id' => 'No se encontró el PDF del scan Anita para grabarlo en la carpeta de precargas.',
            ]);
        }

        $tipoId = (int) ($precarga->tipotransaccion_compra_id
            ?: OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra(
                $oc,
                $this->tipoComprobanteDesdeScanAnita($fila)
            ));
        $tipoAbrev = (string) (Tipotransaccion_Compra::query()->whereKey($tipoId)->value('abreviatura') ?? 'FAC');
        $fecha = $this->fechaYmdDesdeScanAnita((string) ($fila['ifecha'] ?? ''));
        if ($precarga->fechafactura) {
            $fecha = $precarga->fechafactura->format('Y-m-d');
        }

        try {
            $storage = $this->facturaPdfService->copiarPdfLocalAAlmacenPrecarga(
                $origen,
                $proveedor,
                $fecha,
                $tipoAbrev,
                (string) ($precarga->letra ?: ($fila['cletra'] ?? 'A')),
                (int) ($precarga->sucursal ?? $fila['isucursal'] ?? 0),
                (int) ($precarga->numerocomprobante ?? $fila['inumero'] ?? 0),
            );
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'precarga_id' => $e->getMessage(),
            ]);
        }

        $precarga->rutaalmacenamiento = $storage;
        $precarga->save();

        return $precarga;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function tipoComprobanteDesdeScanAnita(array $fila): string
    {
        return PrecargaProveedorTipoComprobanteSupport::normalizar(
            (string) ($fila['ctipo'] ?? 'FC')
        );
    }

    /**
     * Si el scan Anita trae ctipo NC/ND y la precarga quedó como FIS/FC, alinea el tipo fino.
     *
     * @param  array<string, mixed>  $fila
     */
    private function alinearTipoPrecargaConScanAnita(
        Ordencompra $oc,
        Precarga_Comprobante_Proveedor $precarga,
        array $fila,
    ): Precarga_Comprobante_Proveedor {
        // Solo alinear si el scan es del mismo número; si no, no tocar el tipo de otra precarga.
        $scanNro = (int) ($fila['inumero'] ?? 0);
        $preNro = (int) ($precarga->numerocomprobante ?? 0);
        if ($scanNro > 0 && $preNro > 0 && $scanNro !== $preNro) {
            return $precarga;
        }

        $esperado = $this->tipoComprobanteDesdeScanAnita($fila);
        $actual = OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($precarga);
        if ($esperado === $actual) {
            return $precarga;
        }
        $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc, $esperado);
        if ($tipoId <= 0 || $tipoId === (int) $precarga->tipotransaccion_compra_id) {
            return $precarga;
        }
        $precarga->tipotransaccion_compra_id = $tipoId;
        $precarga->save();

        return $precarga->fresh(['tipotransaccion_compras']) ?? $precarga;
    }

    /**
     * Corrige el tipo de una precarga del legajo.
     * FC/NC/ND re-resuelven el fino según el primer CC de la OC;
     * FIB/FGA/… graban esa abreviatura (útil en OC con varios centros de costo).
     *
     * @param  'FC'|'NC'|'ND'|string  $tipoPedido
     */
    public function corregirTipoDocumento(Ordencompra $oc, int $precargaId, string $tipoPedido): Precarga_Comprobante_Proveedor
    {
        $precarga = $this->assertPrecargaDelLegajo($oc, $precargaId);
        $tipo = strtoupper(trim($tipoPedido));
        if (! preg_match('/^[A-Z]{2,6}$/', $tipo)) {
            throw ValidationException::withMessages([
                'tipo' => 'Tipo de comprobante inválido.',
            ]);
        }

        if (PrecargaProveedorAbreviaturaTipoSupport::esTipoGenerico($tipo)) {
            $tipoId = OrdencompraEnvioCuentasAPagarGateSupport::tipotransaccionCompraIdParaOrdencompra($oc, $tipo);
        } else {
            try {
                $tipoId = (int) (Tipotransaccion_Compra::query()
                    ->where('abreviatura', $tipo)
                    ->value('id') ?? 0);
            } catch (\Throwable) {
                $tipoId = 0;
            }
        }

        if ($tipoId <= 0) {
            throw ValidationException::withMessages([
                'tipo' => 'No se pudo resolver el tipo contable «'.$tipo.'».',
            ]);
        }
        $precarga->tipotransaccion_compra_id = $tipoId;
        $precarga->save();

        return $precarga->fresh(['tipotransaccion_compras']) ?? $precarga;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tiposOpcionesCorreccion(Ordencompra $oc): array
    {
        try {
            return PrecargaProveedorAbreviaturaTipoSupport::opcionesCorreccionTipo($oc);
        } catch (\Throwable) {
            return [
                ['value' => 'FC', 'label' => 'FC — Factura (según primer centro de costo de la OC)'],
                ['value' => 'NC', 'label' => 'NC — Nota de crédito (no exige COM)'],
                ['value' => 'ND', 'label' => 'ND — Nota de débito (no exige COM)'],
            ];
        }
    }

    private function marcarOrigenScanAnitaSiNoEsIa(Precarga_Comprobante_Proveedor $precarga): Precarga_Comprobante_Proveedor
    {
        $origen = (string) ($precarga->origen_entrada ?? '');
        if (PrecargaComprobanteOrigenEntrada::conservarOrigenAlAdjuntarPdf($origen)
            || $origen === PrecargaComprobanteOrigenEntrada::SCAN_ANITA) {
            return $precarga;
        }
        $precarga->origen_entrada = PrecargaComprobanteOrigenEntrada::SCAN_ANITA;
        $precarga->save();

        return $precarga;
    }

    /**
     * En OC anuales el mismo número acumula FC/NC de todo el año.
     * Marca las que ya tienen CP en CxP para no tratarlas como parte de este envío.
     *
     * @param  list<array<string, mixed>>  $facturas
     * @param  list<array<string, mixed>>  $comprobantes
     * @return list<array<string, mixed>>
     */
    public function marcarFacturasCargadasEnCxp(array $facturas, array $comprobantes): array
    {
        $claves = [];
        $preIds = [];
        foreach ($comprobantes as $cp) {
            $preId = (int) ($cp['precarga_id'] ?? 0);
            if ($preId > 0) {
                $preIds[$preId] = true;
            }
            $clave = $this->claveFacturaEtiqueta((string) ($cp['etiqueta'] ?? ''));
            if ($clave !== '') {
                $claves[$clave] = true;
            }
            $letra = strtoupper(trim((string) ($cp['letra'] ?? '')));
            $suc = (int) ($cp['sucursal'] ?? 0);
            $nro = (int) ($cp['numerocomprobante'] ?? 0);
            if ($nro > 0) {
                $claves[($letra !== '' ? $letra : 'FC').'|'.$suc.'|'.$nro] = true;
            }
        }

        foreach ($facturas as &$fac) {
            $cargado = false;
            if (($fac['origen'] ?? 'precarga') === 'precarga') {
                $id = (int) ($fac['id'] ?? 0);
                if ($id > 0 && isset($preIds[$id])) {
                    $cargado = true;
                }
            }
            if (! $cargado) {
                $clave = $this->claveFacturaEtiqueta((string) ($fac['etiqueta'] ?? ''));
                if ($clave !== '' && isset($claves[$clave])) {
                    $cargado = true;
                }
            }
            $fac['cargado_cxp'] = $cargado;
        }
        unset($fac);

        return $facturas;
    }

    /**
     * Completa el listado con CP de CxP que no tienen PDF de precarga (OC anuales / import Anita).
     *
     * @param  list<array<string, mixed>>  $facturas
     * @param  list<array<string, mixed>>  $comprobantes
     * @return list<array<string, mixed>>
     */
    public function fusionarComprobantesEnFacturas(array $facturas, array $comprobantes): array
    {
        $porClave = [];
        $porPrecarga = [];
        foreach ($facturas as $i => $fac) {
            $clave = $this->claveFacturaEtiqueta((string) ($fac['etiqueta'] ?? $fac['numero'] ?? ''));
            if ($clave !== '') {
                $porClave[$clave] = $i;
            }
            if (($fac['origen'] ?? 'precarga') === 'precarga') {
                $preId = (int) ($fac['id'] ?? 0);
                if ($preId > 0) {
                    $porPrecarga[$preId] = $i;
                }
            }
        }

        foreach ($comprobantes as $cp) {
            $urlCp = (string) ($cp['url'] ?? '');
            $preId = (int) ($cp['precarga_id'] ?? 0);
            $clave = $this->claveFacturaEtiqueta((string) ($cp['etiqueta'] ?? ''));
            if ($clave === '') {
                $letra = strtoupper(trim((string) ($cp['letra'] ?? '')));
                $suc = (int) ($cp['sucursal'] ?? 0);
                $nro = (int) ($cp['numerocomprobante'] ?? 0);
                if ($nro > 0) {
                    $clave = ($letra !== '' ? $letra : 'FC').'|'.$suc.'|'.$nro;
                }
            }
            $idx = null;
            if ($preId > 0 && isset($porPrecarga[$preId])) {
                $idx = $porPrecarga[$preId];
            } elseif ($clave !== '' && isset($porClave[$clave])) {
                $idx = $porClave[$clave];
            }
            if ($idx !== null) {
                if ($urlCp !== '') {
                    $facturas[$idx]['url_comprobante'] = $urlCp;
                }
                $facturas[$idx]['cargado_cxp'] = true;

                continue;
            }
            $tipo = (string) ($cp['tipo'] ?? 'FC');
            $facturas[] = [
                'id' => 'cp-'.(int) ($cp['id'] ?? 0),
                'origen' => 'comprobante',
                'origen_label' => (string) ($cp['origen_label'] ?? 'Comprobante cargado en CxP'),
                'tipo' => $tipo,
                'tipo_label' => (string) ($cp['tipo_label'] ?? OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo)),
                'exige_com' => false,
                'etiqueta' => (string) ($cp['etiqueta'] ?? ''),
                'fecha' => (string) ($cp['fecha'] ?? ''),
                'total' => $cp['total'] ?? null,
                'estado' => (string) ($cp['estado'] ?? ''),
                'url_pdf' => null,
                'url_cargar_cxp' => null,
                'url_comprobante' => $urlCp !== '' ? $urlCp : null,
                'cargado_cxp' => true,
            ];
        }

        usort($facturas, static function (array $a, array $b): int {
            $ea = ! empty($a['cargado_cxp']) ? 1 : 0;
            $eb = ! empty($b['cargado_cxp']) ? 1 : 0;
            if ($ea !== $eb) {
                return $ea <=> $eb;
            }

            return strcmp((string) ($a['etiqueta'] ?? ''), (string) ($b['etiqueta'] ?? ''));
        });

        return $facturas;
    }

    public function precargaPerteneceAlLegajo(Ordencompra $oc, Precarga_Comprobante_Proveedor $precarga): bool
    {
        if ((int) $precarga->empresa_id !== (int) $oc->empresa_id) {
            return false;
        }

        $a = trim((string) $precarga->numeroordencompra);
        $b = trim((string) $oc->numeroordencompra);
        if ($a === $b) {
            return true;
        }
        $na = preg_replace('/\D+/', '', $a) ?? '';
        $nb = preg_replace('/\D+/', '', $b) ?? '';

        return $na !== '' && $na === $nb;
    }

    private function etiquetaFactura(Precarga_Comprobante_Proveedor $pre): string
    {
        $pre->loadMissing('tipotransaccion_compras:id,abreviatura');
        $abrev = strtoupper(trim((string) ($pre->tipotransaccion_compras->abreviatura ?? '')));
        $letra = trim((string) ($pre->letra ?? ''));
        $suc = (int) ($pre->sucursal ?? 0);
        $nro = (int) ($pre->numerocomprobante ?? 0);
        $numero = ($letra !== '' || $nro > 0)
            ? trim(sprintf('%s %04d-%08d', $letra !== '' ? $letra : 'FC', $suc, $nro))
            : 'Factura #'.$pre->id;
        $base = $abrev !== '' ? $abrev.' '.$numero : $numero;
        if ((string) ($pre->origen_entrada ?? '') === PrecargaComprobanteOrigenEntrada::SCAN_ANITA) {
            return $base.' ('.PrecargaComprobanteOrigenEntrada::etiqueta(PrecargaComprobanteOrigenEntrada::SCAN_ANITA).')';
        }

        return $base;
    }

    private function documentoCom(Recepcion_Proveedor $rec): string
    {
        $nro = $rec->numerorecepcion ?: $rec->id;
        if ($rec->anita_tipo && $rec->anita_sucursal && $rec->anita_nro) {
            return sprintf(
                'COM %s %s %d-%d',
                $rec->anita_tipo,
                $rec->anita_letra ?? '',
                $rec->anita_sucursal,
                $rec->anita_nro
            );
        }

        return 'COM #'.$nro;
    }
}
