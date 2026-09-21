<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Pagoproveedor_Comprobante;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Configuracion\Moneda;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\ComprobanteProveedorImporteComparacionComSupport;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport;
use App\Support\Compras\ComprobanteProveedorReservaComLegajoSupport;
use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use App\Support\Compras\ComprobanteProveedorToleranciaImporteSupport;
use App\Support\Compras\ComprobanteProveedorUnicidadSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraLegajoDocumentoTipoSupport;
use App\Support\Compras\OrdencompraLegajoScanMaterializacionLock;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use App\Support\Compras\PrecargaFacturaScanPathResolver;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorAbreviaturaTipoSupport;
use App\Support\Compras\PrecargaProveedor\PrecargaProveedorTipoComprobanteSupport;
use Illuminate\Support\Facades\Auth;
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
    ) {}

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
        $tiposOpciones = $this->tiposOpcionesCorreccion($oc, $facturas);
        $coms = $this->comsDelLegajo($oc);
        $devoluciones = $this->devolucionesDelLegajo($oc);
        $precargaIds = [];
        foreach ($facturas as $f) {
            if (($f['origen'] ?? 'precarga') === 'precarga') {
                $precargaIds[] = (int) $f['id'];
            }
        }
        // Todas las FC→COM del legajo (incluye ya en CxP / precargas no listadas),
        // para que el modal no ofrezca COM ya vinculadas a otra factura.
        $asignadas = $this->asignacionesActualesDelLegajo($oc);
        $comprobantes = $this->comprobantesDelLegajo($oc, $precargaIds);
        $facturas = $this->marcarFacturasCargadasEnCxp($facturas, $comprobantes);
        $facturas = $this->marcarDuplicadosFiscalesFueraDelLegajo($facturas);
        $facturas = $this->fusionarComprobantesEnFacturas($facturas, $comprobantes);
        $facturas = $this->adjuntarScansAnitaAFacturas($oc, $facturas);
        [$facturas, $coms] = $this->adjuntarAsignacionesYSugerenciasCom($facturas, $coms, $asignadas);
        $detallePagos = $this->resolverPagosDeComprobantes(
            array_map(static fn (array $c) => (int) $c['id'], $comprobantes)
        );
        $pagos = $detallePagos['lista'];
        $facturas = $this->adjuntarPagosAFacturas($facturas, $detallePagos['por_comprobante']);
        $pendientes = OrdencompraEnvioCuentasAPagarGateSupport::documentosPendientesCarga($oc);
        $enCxp = OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar((int) ($oc->sector_legajocompra_id ?? 0));
        // Misma secuencia que la lista de facturas del paquete / index (pendientes primero).
        $siguiente = null;
        $urlCargar = null;
        foreach ($facturas as $fac) {
            if (! empty($fac['cargado_cxp']) || ($fac['origen'] ?? '') === 'comprobante') {
                continue;
            }
            $siguiente = $this->pendienteDesdeFacturaPaquete($fac, $pendientes);
            if ($siguiente === null) {
                continue;
            }
            if ($enCxp) {
                $urlCargar = $this->urlCargarFacturaDesdePendiente((int) $oc->id, $siguiente);
            }
            break;
        }
        if ($siguiente === null) {
            $siguiente = $pendientes[0] ?? null;
            if ($enCxp && $siguiente !== null) {
                $urlCargar = $this->urlCargarFacturaDesdePendiente((int) $oc->id, $siguiente);
            }
        }

        return [
            'ordencompra_id' => (int) $oc->id,
            'numero' => (string) $oc->numeroordencompra,
            'es_anticipada' => \App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc),
            'tratamiento' => (string) ($oc->tratamiento ?? ''),
            'facturas' => $facturas,
            'tipos_opciones' => $tiposOpciones,
            'coms' => $coms,
            'scans_descartados' => app(OrdencompraLegajoScanAnitaDescartarService::class)
                ->descartadosVigentesDeOc($oc),
            'devoluciones' => $devoluciones,
            'asignadas' => $asignadas,
            'comprobantes' => $comprobantes,
            'pagos' => $pagos,
            'pendientes_carga' => count($pendientes),
            'siguiente_pendiente' => $siguiente,
            'url_cargar_cxp' => $urlCargar,
            'url_oc' => can('editar-ordencompra', false)
                ? route('editar_ordencompra', ['id' => (int) $oc->id])
                : route('solo_consulta_ordencompra', ['id' => (int) $oc->id]),
        ];
    }

    /**
     * @param  array<string, mixed>  $fac
     * @param  list<array<string, mixed>>  $pendientes
     * @return array<string, mixed>|null
     */
    private function pendienteDesdeFacturaPaquete(array $fac, array $pendientes): ?array
    {
        $origen = (string) ($fac['origen'] ?? 'precarga');
        $id = $fac['id'] ?? null;
        foreach ($pendientes as $pendiente) {
            if ($origen === 'precarga' && (int) ($pendiente['precarga_id'] ?? 0) === (int) $id) {
                return $pendiente;
            }
            if ($origen === 'anita' && (string) ($pendiente['anita_id'] ?? '') === (string) $id) {
                return $pendiente;
            }
        }

        if ($origen === 'precarga' && (int) $id > 0) {
            return [
                'precarga_id' => (int) $id,
                'anita_id' => null,
                'tipo' => (string) ($fac['tipo'] ?? 'FC'),
                'etiqueta' => (string) ($fac['etiqueta'] ?? $fac['numero'] ?? ''),
            ];
        }
        if ($origen === 'anita' && (string) $id !== '') {
            return [
                'precarga_id' => null,
                'anita_id' => (string) $id,
                'tipo' => (string) ($fac['tipo'] ?? 'FC'),
                'etiqueta' => (string) ($fac['etiqueta'] ?? $fac['numero'] ?? ''),
            ];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $pendiente
     */
    private function urlCargarFacturaDesdePendiente(int $ocId, array $pendiente): string
    {
        $params = [
            'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
            'ordencompra_id' => $ocId,
        ];
        $precargaId = (int) ($pendiente['precarga_id'] ?? 0);
        $anitaId = trim((string) ($pendiente['anita_id'] ?? ''));
        if ($precargaId > 0) {
            $params['precarga_id'] = $precargaId;
        } elseif ($anitaId !== '') {
            $params['anita_id'] = $anitaId;
        }

        return route('crear_comprobante_proveedor', $params);
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
            // Ids sintéticos de CP ya en CxP (cp-N) no son precargas asignables.
            if (! $this->esReferenciaAsignable($ref)) {
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
        $etiquetasCom = [];
        if ($todosIds !== []) {
            $comsValidas = Recepcion_Proveedor::query()
                ->where('ordencompra_id', $oc->id)
                ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
                ->whereIn('id', $todosIds)
                ->get(['id', 'numerorecepcion']);
            $validas = $comsValidas
                ->map(static fn ($row) => (int) $row->id)
                ->all();
            foreach ($comsValidas as $com) {
                $cid = (int) $com->id;
                $nro = trim((string) ($com->numerorecepcion ?? ''));
                $etiquetasCom[$cid] = $nro !== '' ? 'Nº '.$nro : '#'.$cid;
            }
            $faltan = array_values(array_diff($todosIds, $validas));
            if ($faltan !== []) {
                throw ValidationException::withMessages([
                    'recepcion_ids' => 'Hay COM que no pertenecen a esta OC o no están confirmadas.',
                ]);
            }
        }

        // Validar y escribir bajo el mismo candado: dos submits simultáneos del mismo
        // legajo se serializan y el segundo ve la asignación del primero.
        DB::transaction(function () use ($oc, $normalizadas, $etiquetasCom) {
            $this->bloquearLegajoParaAsignacion($oc);

            $mapaEfectivo = $this->asignacionesActualesDelLegajo($oc);
            foreach ($normalizadas as $precargaId => $ids) {
                $mapaEfectivo[(int) $precargaId] = $ids;
            }

            if (! $this->permiteCompartirComEntreFacturas($oc)) {
                $conflicto = ComprobanteProveedorReservaComLegajoSupport::mensajeComDuplicadaEntreFacturas(
                    $mapaEfectivo,
                    $etiquetasCom
                );
                if ($conflicto !== null) {
                    throw ValidationException::withMessages([
                        'recepcion_ids' => $conflicto,
                    ]);
                }
            }

            $exceso = ComprobanteProveedorReservaComLegajoSupport::mensajeExcesoProvisionPorCom(
                $mapaEfectivo,
                $this->provisionPorCom($oc, $mapaEfectivo),
                $this->importePorFacturaDelLegajo($oc, $mapaEfectivo),
                $etiquetasCom,
                ComprobanteProveedorToleranciaImporteSupport::porcentajeDesdeOc($oc),
            );
            if ($exceso !== null) {
                throw ValidationException::withMessages([
                    'recepcion_ids' => $exceso,
                ]);
            }

            $previas = $this->asignacionesPorPrecarga(array_map('intval', array_keys($normalizadas)));

            foreach ($normalizadas as $precargaId => $ids) {
                // De a un modelo: el delete masivo no dispara eventos y la baja no quedaría auditada.
                Precarga_Comprobante_Proveedor_Recepcion::query()
                    ->where('precarga_comprobante_proveedor_id', $precargaId)
                    ->get()
                    ->each(static fn (Precarga_Comprobante_Proveedor_Recepcion $fila) => $fila->delete());
                foreach ($ids as $orden => $recepcionId) {
                    Precarga_Comprobante_Proveedor_Recepcion::query()->create([
                        'precarga_comprobante_proveedor_id' => $precargaId,
                        'recepcion_proveedor_id' => $recepcionId,
                        'orden' => $orden + 1,
                        'user_id' => Auth::id() ? (int) Auth::id() : null,
                    ]);
                }
            }

            $this->registrarHistoriaAsignacionCom($oc, $normalizadas, $previas, $etiquetasCom);
        });
    }

    /**
     * OC anticipada (50/50) y contratos: la provisión de una COM puede repartirse entre
     * varias facturas del legajo. El freno ahí es el importe, no la unicidad.
     */
    private function permiteCompartirComEntreFacturas(Ordencompra $oc): bool
    {
        if (ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc)) {
            return true;
        }

        return (bool) ($oc->es_contrato ?? false);
    }

    /**
     * Candado de escritura sobre las precargas del legajo (clave empresa + nº de OC).
     * Si el legajo todavía no tiene precargas, cae al registro de la OC.
     */
    private function bloquearLegajoParaAsignacion(Ordencompra $oc): void
    {
        $numero = trim((string) $oc->numeroordencompra);
        $empresaId = (int) $oc->empresa_id;

        $bloqueadas = 0;
        if ($numero !== '' && $empresaId > 0) {
            $bloqueadas = count(
                DB::table('precarga_comprobante_proveedor')
                    ->where('empresa_id', $empresaId)
                    ->where('numeroordencompra', $numero)
                    ->lockForUpdate()
                    ->pluck('id')
                    ->all()
            );
        }

        if ($bloqueadas === 0) {
            DB::table('ordencompra')->where('id', (int) $oc->id)->lockForUpdate()->value('id');
        }
    }

    /**
     * Provisión contable de cada COM involucrada, en la moneda de la recepción.
     *
     * @param  array<int|string, list<int>>  $mapaEfectivo
     * @return array<int, float>
     */
    private function provisionPorCom(Ordencompra $oc, array $mapaEfectivo): array
    {
        $ids = [];
        foreach ($mapaEfectivo as $recepcionIds) {
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid > 0) {
                    $ids[$rid] = true;
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->whereIn('id', array_keys($ids))
            ->with(['recepcion_proveedor_articulos'])
            ->get();
        if ($recepciones->isEmpty()) {
            return [];
        }

        $out = [];
        foreach (
            app(ComprobanteProveedorRecepcionesSupport::class)
                ->enriquecerConImporteProvision($recepciones) as $recepcion
        ) {
            $out[(int) $recepcion->id] = round((float) ($recepcion->importe_provision_com ?? 0), 2);
        }

        return $out;
    }

    /**
     * Importe comparable de cada factura (neto gravado en letra A; total en B/C o monotributo),
     * por clave de asignación (precarga_id / cp-N). La provisión COM es ese mismo neto, no el total con IVA.
     *
     * @param  array<int|string, list<int>>  $asignacionesPorFactura
     * @return array<int|string, float>
     */
    private function importePorFacturaDelLegajo(Ordencompra $oc, array $asignacionesPorFactura = []): array
    {
        $numero = trim((string) $oc->numeroordencompra);
        $empresaId = (int) $oc->empresa_id;
        $out = [];
        $iiPorCom = $this->impuestoInternoPorComAsignada($asignacionesPorFactura);

        if ($numero !== '' && $empresaId > 0) {
            $precargas = Precarga_Comprobante_Proveedor::query()
                ->where('empresa_id', $empresaId)
                ->where('numeroordencompra', $numero)
                ->with([
                    'proveedores:id,condicioniva_id',
                    'precarga_comprobante_proveedor_conceptos.concepto_ivacompras',
                ])
                ->get(['id', 'letra', 'subtotal', 'total', 'proveedor_id']);
            foreach ($precargas as $precarga) {
                $preId = (int) $precarga->id;
                $out[$preId] = $this->importeComparableConProvisionCom(
                    (string) ($precarga->letra ?? ''),
                    $this->condicionIvaId($precarga->proveedores->condicioniva_id ?? null),
                    (float) ($precarga->total ?? 0),
                    (float) ($precarga->subtotal ?? 0),
                    $precarga->precarga_comprobante_proveedor_conceptos,
                    $this->asignacionIncluyeImpuestoInterno($asignacionesPorFactura, $preId, $iiPorCom),
                );
            }
        }

        $precargaIds = array_values(array_filter(
            array_map(static fn ($id) => is_int($id) || ctype_digit((string) $id) ? (int) $id : 0, array_keys($out)),
            static fn (int $id) => $id > 0
        ));
        $cps = Comprobante_Proveedor::query()
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
            ->with([
                'proveedores:id,condicioniva_id',
                'comprobante_proveedor_conceptos.concepto_ivacompras',
            ])
            ->get(['id', 'letra', 'subtotal', 'total', 'precarga_comprobante_proveedor_id', 'proveedor_id', 'estado']);

        foreach ($cps as $cp) {
            $cpId = (int) $cp->id;
            if ($cpId <= 0) {
                continue;
            }
            $preId = (int) ($cp->precarga_comprobante_proveedor_id ?? 0);
            $claveAsignacion = $preId > 0 ? $preId : 'cp-'.$cpId;
            $comparable = $this->importeComparableConProvisionCom(
                (string) ($cp->letra ?? ''),
                $this->condicionIvaId($cp->proveedores->condicioniva_id ?? null),
                (float) ($cp->total ?? 0),
                (float) ($cp->subtotal ?? 0),
                $cp->comprobante_proveedor_conceptos,
                $this->asignacionIncluyeImpuestoInterno($asignacionesPorFactura, $claveAsignacion, $iiPorCom),
            );
            $out['cp-'.$cpId] = $comparable;
            // El CP manda sobre la precarga cuando ya tiene importe: el scan de Anita a veces llega en cero.
            if ($preId > 0 && $comparable > 0.00001) {
                $out[$preId] = $comparable;
            }
        }

        return $out;
    }

    /**
     * @param  array<int|string, list<int>>  $asignacionesPorFactura
     * @return array<int, float>
     */
    private function impuestoInternoPorComAsignada(array $asignacionesPorFactura): array
    {
        $ids = [];
        foreach ($asignacionesPorFactura as $recepcionIds) {
            foreach ((array) $recepcionIds as $recepcionId) {
                $rid = (int) $recepcionId;
                if ($rid > 0) {
                    $ids[$rid] = true;
                }
            }
        }
        if ($ids === []) {
            return [];
        }

        $out = [];
        foreach (
            Recepcion_Proveedor::query()
                ->whereIn('id', array_keys($ids))
                ->get(['id', 'impuesto_interno']) as $recepcion
        ) {
            $out[(int) $recepcion->id] = (float) ($recepcion->impuesto_interno ?? 0);
        }

        return $out;
    }

    /**
     * @param  array<int|string, list<int>>  $asignacionesPorFactura
     * @param  array<int, float>  $iiPorCom
     */
    private function asignacionIncluyeImpuestoInterno(
        array $asignacionesPorFactura,
        int|string $claveFactura,
        array $iiPorCom,
    ): bool {
        $ids = $asignacionesPorFactura[$claveFactura] ?? $asignacionesPorFactura[(string) $claveFactura] ?? [];
        foreach ((array) $ids as $recepcionId) {
            if ((float) ($iiPorCom[(int) $recepcionId] ?? 0) > 0.005) {
                return true;
            }
        }

        return false;
    }

    private function condicionIvaId(mixed $condicionIvaId): ?int
    {
        $id = (int) $condicionIvaId;
        if ($id <= 0) {
            return null;
        }

        return $id;
    }

    private function importeComparableConProvisionCom(
        string $letra,
        ?int $condicionIvaId,
        float $total,
        float $subtotal,
        iterable $conceptos,
        bool $incluirImpuestoInterno,
    ): float {
        $meta = ComprobanteProveedorImporteComparacionComSupport::importeParaCompararConRecepcion(
            $letra,
            $condicionIvaId,
            $total,
            $subtotal,
            $conceptos,
            $incluirImpuestoInterno,
        );

        return round(abs((float) $meta['importe']), 2);
    }

    /**
     * Traza quién asignó/desasignó cada COM (la tabla pivote se reescribe por completo).
     *
     * @param  array<int, list<int>>  $normalizadas
     * @param  array<int, list<int>>  $previas
     * @param  array<int, string>  $etiquetasCom
     */
    private function registrarHistoriaAsignacionCom(
        Ordencompra $oc,
        array $normalizadas,
        array $previas,
        array $etiquetasCom,
    ): void {
        $lineas = [];
        foreach ($normalizadas as $precargaId => $ids) {
            $antes = array_map('intval', $previas[(int) $precargaId] ?? []);
            $ahora = array_map('intval', $ids);
            $agregadas = array_values(array_diff($ahora, $antes));
            $quitadas = array_values(array_diff($antes, $ahora));
            if ($agregadas === [] && $quitadas === []) {
                continue;
            }

            $etiquetar = static function (array $comIds) use ($etiquetasCom): string {
                return implode(', ', array_map(
                    static fn (int $id) => trim((string) ($etiquetasCom[$id] ?? '')) !== ''
                        ? (string) $etiquetasCom[$id]
                        : '#'.$id,
                    $comIds
                ));
            };

            $partes = [];
            if ($agregadas !== []) {
                $partes[] = 'asignó COM '.$etiquetar($agregadas);
            }
            if ($quitadas !== []) {
                $partes[] = 'quitó COM '.$etiquetar($quitadas);
            }
            $lineas[] = 'Factura #'.(int) $precargaId.': '.implode('; ', $partes).'.';
        }

        if ($lineas === []) {
            return;
        }

        $usuarioId = Auth::id() ? (int) Auth::id() : (int) ($oc->creousuario_id ?? 0);
        if ($usuarioId <= 0) {
            // Sin usuario no se puede grabar historia (creousuario_id NOT NULL); la asignación
            // de COM ya quedó persistida en el mismo transaction — no abortar por la traza.
            return;
        }

        Ordencompra_Historia::query()->create([
            'ordencompra_id' => (int) $oc->id,
            'sector_legajocompra_id' => $oc->sector_legajocompra_id ? (int) $oc->sector_legajocompra_id : null,
            'fecha' => now(),
            'observacion' => 'Asignación de COM a facturas del legajo',
            'leyenda' => implode(' ', $lineas),
            'creousuario_id' => $usuarioId,
        ]);
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
                'subtotal', 'total', 'rutaalmacenamiento', 'estado', 'origen_entrada',
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
                'subtotal' => $pre->subtotal !== null ? (float) $pre->subtotal : null,
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
            ->get([
                'id', 'numerorecepcion', 'fecha', 'estado', 'numerofactura',
                'anita_tipo', 'anita_letra', 'anita_sucursal', 'anita_nro',
            ]);

        $facturadas = $this->idsComFacturadasEnCxp($rows->pluck('id')->map(static fn ($id) => (int) $id)->all());
        $provisionPorId = $this->provisionPorComIds(
            $rows->pluck('id')->map(static fn ($id) => (int) $id)->all()
        );

        $out = [];
        foreach ($rows as $rec) {
            $id = (int) $rec->id;
            $numeroFactura = trim((string) ($rec->numerofactura ?? ''));
            $parsed = $this->parsearNumeroFacturaCom($numeroFactura);
            $out[] = [
                'id' => $id,
                'numerorecepcion' => (int) ($rec->numerorecepcion ?? 0) ?: null,
                'documento' => $this->documentoCom($rec),
                'fecha' => $rec->fecha ? $rec->fecha->format('d/m/Y') : '',
                'estado' => (string) $rec->estado,
                'confirmada' => $rec->estado === Recepcion_Proveedor::ESTADO_CONFIRMADA,
                'facturada_en_cxp' => isset($facturadas[$id]),
                'neto' => $provisionPorId[$id] ?? null,
                'numerofactura' => $numeroFactura !== '' ? $numeroFactura : null,
                'factura_sucursal' => $parsed['sucursal'],
                'factura_numero' => $parsed['numero'],
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
     * @param  list<int>  $comIds
     * @return array<int, float>
     */
    private function provisionPorComIds(array $comIds): array
    {
        $comIds = array_values(array_filter($comIds, static fn (int $id) => $id > 0));
        if ($comIds === []) {
            return [];
        }

        $recepciones = Recepcion_Proveedor::query()
            ->whereIn('id', $comIds)
            ->with(['recepcion_proveedor_articulos'])
            ->get();

        $out = [];
        foreach (
            app(ComprobanteProveedorRecepcionesSupport::class)
                ->enriquecerConImporteProvision($recepciones) as $recepcion
        ) {
            $neto = round((float) ($recepcion->importe_provision_com ?? 0), 2);
            if ($neto > 0.00001) {
                $out[(int) $recepcion->id] = $neto;
            }
        }

        return $out;
    }

    /**
     * @return array{sucursal: int|null, numero: int|null}
     */
    private function parsearNumeroFacturaCom(string $numerofactura): array
    {
        $raw = trim($numerofactura);
        if ($raw === '') {
            return ['sucursal' => null, 'numero' => null];
        }

        // Formatos típicos Anita: "04-79223", "00004-00079223", "4/79223".
        if (preg_match('/(\d+)\D+(\d+)\s*$/', $raw, $m)) {
            return [
                'sucursal' => (int) $m[1],
                'numero' => (int) $m[2],
            ];
        }
        if (preg_match('/(\d+)\s*$/', $raw, $m)) {
            return ['sucursal' => null, 'numero' => (int) $m[1]];
        }

        return ['sucursal' => null, 'numero' => null];
    }

    /**
     * Adjunta a cada factura/COM lo ya asignado y, si falta, la sugerencia por número de
     * factura anotado en la COM o por neto coincidente. No escribe nada: solo orienta al operador.
     *
     * @param  list<array<string, mixed>>  $facturas
     * @param  list<array<string, mixed>>  $coms
     * @param  array<int|string, list<int>>  $asignadas
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function adjuntarAsignacionesYSugerenciasCom(array $facturas, array $coms, array $asignadas): array
    {
        $comsPorId = [];
        foreach ($coms as $com) {
            $comsPorId[(int) ($com['id'] ?? 0)] = $com;
        }

        $facPorId = [];
        foreach ($facturas as $fac) {
            $facPorId[(string) ($fac['id'] ?? '')] = $fac;
        }

        $asignadaComAFac = [];
        foreach ($asignadas as $facKey => $comIds) {
            foreach ((array) $comIds as $comId) {
                $rid = (int) $comId;
                if ($rid > 0) {
                    $asignadaComAFac[$rid] = (string) $facKey;
                }
            }
        }

        $sugeridaFacACom = $this->sugerirComPorFactura($facturas, $coms, $asignadaComAFac);
        $sugeridaComAFac = [];
        foreach ($sugeridaFacACom as $facKey => $info) {
            $rid = (int) ($info['com_id'] ?? 0);
            if ($rid > 0) {
                $sugeridaComAFac[$rid] = [
                    'factura_id' => (string) $facKey,
                    'motivo' => (string) ($info['motivo'] ?? 'neto'),
                ];
            }
        }

        foreach ($facturas as &$fac) {
            $key = (string) ($fac['id'] ?? '');
            $idsAsig = array_values(array_filter(
                array_map('intval', (array) ($asignadas[$key] ?? $asignadas[(int) $key] ?? [])),
                static fn (int $id) => $id > 0
            ));
            $asignadasDetalle = [];
            foreach ($idsAsig as $rid) {
                $com = $comsPorId[$rid] ?? null;
                $asignadasDetalle[] = [
                    'id' => $rid,
                    'documento' => (string) ($com['documento'] ?? ('COM #'.$rid)),
                    'numerorecepcion' => $com['numerorecepcion'] ?? null,
                    'neto' => $com['neto'] ?? null,
                ];
            }
            $fac['coms_asignadas'] = $asignadasDetalle;
            $fac['coms_asignadas_ids'] = $idsAsig;

            $sug = $sugeridaFacACom[$key] ?? null;
            if ($sug !== null && $idsAsig === []) {
                $rid = (int) $sug['com_id'];
                $com = $comsPorId[$rid] ?? null;
                $fac['com_sugerida'] = [
                    'id' => $rid,
                    'documento' => (string) ($com['documento'] ?? ('COM #'.$rid)),
                    'numerorecepcion' => $com['numerorecepcion'] ?? null,
                    'neto' => $com['neto'] ?? null,
                    'motivo' => (string) ($sug['motivo'] ?? 'neto'),
                    'motivo_label' => $sug['motivo'] === 'numero'
                        ? 'mismo nº de factura'
                        : 'mismo neto',
                ];
            } else {
                $fac['com_sugerida'] = null;
            }
        }
        unset($fac);

        foreach ($coms as &$com) {
            $rid = (int) ($com['id'] ?? 0);
            $facKey = $asignadaComAFac[$rid] ?? null;
            if ($facKey !== null) {
                $fac = $facPorId[$facKey] ?? null;
                $com['asignada_a'] = [
                    'id' => $facKey,
                    'etiqueta' => (string) ($fac['etiqueta'] ?? ('#'.$facKey)),
                ];
                $com['sugerida_para'] = null;
                continue;
            }
            $com['asignada_a'] = null;
            $sug = $sugeridaComAFac[$rid] ?? null;
            if ($sug !== null) {
                $fac = $facPorId[$sug['factura_id']] ?? null;
                $com['sugerida_para'] = [
                    'id' => $sug['factura_id'],
                    'etiqueta' => (string) ($fac['etiqueta'] ?? ('#'.$sug['factura_id'])),
                    'motivo' => (string) ($sug['motivo'] ?? 'neto'),
                    'motivo_label' => $sug['motivo'] === 'numero'
                        ? 'mismo nº de factura'
                        : 'mismo neto',
                ];
            } else {
                $com['sugerida_para'] = null;
            }
        }
        unset($com);

        return [$facturas, $coms];
    }

    /**
     * @param  list<array<string, mixed>>  $facturas
     * @param  list<array<string, mixed>>  $coms
     * @param  array<int, string>  $asignadaComAFac
     * @return array<string, array{com_id: int, motivo: string}>
     */
    private function sugerirComPorFactura(array $facturas, array $coms, array $asignadaComAFac): array
    {
        $comsLibres = [];
        foreach ($coms as $com) {
            $rid = (int) ($com['id'] ?? 0);
            if ($rid <= 0 || isset($asignadaComAFac[$rid]) || ! empty($com['facturada_en_cxp'])) {
                continue;
            }
            if (empty($com['confirmada'])) {
                continue;
            }
            $comsLibres[$rid] = $com;
        }

        $out = [];
        $usadas = [];

        // 1) Match por número de factura anotado en la COM (el más confiable).
        foreach ($facturas as $fac) {
            if (! $this->facturaExigeComParaSugerencia($fac)) {
                continue;
            }
            $key = (string) ($fac['id'] ?? '');
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $suc = (int) ($fac['sucursal'] ?? 0);
            $nro = (int) ($fac['numerocomprobante'] ?? 0);
            if ($nro <= 0) {
                continue;
            }
            foreach ($comsLibres as $rid => $com) {
                if (isset($usadas[$rid])) {
                    continue;
                }
                $comNro = (int) ($com['factura_numero'] ?? 0);
                if ($comNro !== $nro) {
                    continue;
                }
                $comSuc = $com['factura_sucursal'];
                if ($comSuc !== null && (int) $comSuc !== $suc && $suc > 0) {
                    continue;
                }
                $out[$key] = ['com_id' => $rid, 'motivo' => 'numero'];
                $usadas[$rid] = true;
                break;
            }
        }

        // 2) Match por neto (provisión COM ≈ subtotal factura), 1 a 1.
        foreach ($facturas as $fac) {
            if (! $this->facturaExigeComParaSugerencia($fac)) {
                continue;
            }
            $key = (string) ($fac['id'] ?? '');
            if ($key === '' || isset($out[$key])) {
                continue;
            }
            $netoFac = (float) ($fac['subtotal'] ?? 0);
            if ($netoFac <= 0.00001) {
                continue;
            }
            $mejor = null;
            $mejorDiff = null;
            foreach ($comsLibres as $rid => $com) {
                if (isset($usadas[$rid])) {
                    continue;
                }
                $netoCom = (float) ($com['neto'] ?? 0);
                if ($netoCom <= 0.00001) {
                    continue;
                }
                $diff = abs($netoFac - $netoCom);
                if ($diff > 0.05 && $diff / max($netoCom, $netoFac) > 0.002) {
                    continue;
                }
                if ($mejorDiff === null || $diff < $mejorDiff) {
                    $mejor = $rid;
                    $mejorDiff = $diff;
                }
            }
            if ($mejor !== null) {
                $out[$key] = ['com_id' => $mejor, 'motivo' => 'neto'];
                $usadas[$mejor] = true;
            }
        }

        return $out;
    }

    private function facturaExigeComParaSugerencia(array $fac): bool
    {
        if (! empty($fac['cargado_cxp'])) {
            return false;
        }
        $tipo = strtoupper(trim((string) ($fac['tipo'] ?? 'FC')));
        if ($tipo === 'NC' || $tipo === 'ND') {
            return false;
        }

        return ($fac['exige_com'] ?? true) !== false;
    }

    /**
     * @param  list<int>  $recepcionIds
     * @return array<int, true>
     */
    private function idsComFacturadasEnCxp(array $recepcionIds): array
    {
        $recepcionIds = array_values(array_filter($recepcionIds, static fn (int $id) => $id > 0));
        if ($recepcionIds === [] || ! Schema::hasTable('comprobante_proveedor_recepcion')) {
            return [];
        }

        $ids = DB::table('comprobante_proveedor_recepcion as cpr')
            ->join('comprobante_proveedor as cp', 'cp.id', '=', 'cpr.comprobante_proveedor_id')
            ->whereIn('cpr.recepcion_proveedor_id', $recepcionIds)
            ->where('cp.estado', ComprobanteProveedorEstados::CONTABILIZADO)
            ->pluck('cpr.recepcion_proveedor_id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = true;
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
     * Asignaciones FC→COM actuales del legajo (precargas + CP ya en CxP).
     *
     * @return array<int|string, list<int>>
     */
    public function asignacionesActualesDelLegajo(Ordencompra $oc): array
    {
        $numero = trim((string) $oc->numeroordencompra);
        $empresaId = (int) $oc->empresa_id;
        if ($numero === '' || $empresaId <= 0 || ! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return [];
        }

        $precargas = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', $empresaId)
            ->where('numeroordencompra', $numero)
            ->get(['id', 'letra', 'sucursal', 'numerocomprobante']);
        $precargaIds = $precargas
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();

        $asignadas = $this->asignacionesPorPrecarga($precargaIds);

        return $this->incorporarAsignacionesDeComprobantesCxp(
            $asignadas,
            $this->comprobantesDelLegajo($oc, $precargaIds),
            $this->comsPorComprobanteDelLegajo($oc, $precargaIds),
            $precargas->map(static fn ($pre) => [
                'id' => (int) $pre->id,
                'letra' => (string) ($pre->letra ?? ''),
                'sucursal' => (int) ($pre->sucursal ?? 0),
                'numerocomprobante' => (int) ($pre->numerocomprobante ?? 0),
            ])->all(),
        );
    }

    /**
     * COM ya vinculadas a un CP del legajo (incluye facturas anuales importadas sin precarga).
     *
     * @param  array<int|string, list<int>>  $asignadas
     * @param  list<array<string, mixed>>  $comprobantes
     * @param  array<int, list<int>>  $comsPorComprobante
     * @param  list<array{id: int, letra?: string, sucursal?: int, numerocomprobante?: int}>  $precargas
     * @return array<int|string, list<int>>
     */
    public function incorporarAsignacionesDeComprobantesCxp(
        array $asignadas,
        array $comprobantes,
        array $comsPorComprobante,
        array $precargas = [],
    ): array {
        $prePorClave = [];
        foreach ($precargas as $pre) {
            $preId = (int) ($pre['id'] ?? 0);
            if ($preId <= 0) {
                continue;
            }
            $clave = $this->claveLetraSucursalNumero(
                (string) ($pre['letra'] ?? ''),
                (int) ($pre['sucursal'] ?? 0),
                (int) ($pre['numerocomprobante'] ?? 0),
            );
            if ($clave !== '') {
                $prePorClave[$clave] = $preId;
            }
        }

        foreach ($comprobantes as $cp) {
            $cpId = (int) ($cp['id'] ?? 0);
            $ids = $comsPorComprobante[$cpId] ?? [];
            if ($cpId <= 0 || $ids === []) {
                continue;
            }
            $preId = (int) ($cp['precarga_id'] ?? 0);
            if ($preId <= 0) {
                $clave = $this->claveLetraSucursalNumero(
                    (string) ($cp['letra'] ?? ''),
                    (int) ($cp['sucursal'] ?? 0),
                    (int) ($cp['numerocomprobante'] ?? 0),
                );
                $preId = $clave !== '' ? (int) ($prePorClave[$clave] ?? 0) : 0;
            }
            $key = $preId > 0 ? $preId : ('cp-'.$cpId);
            $asignadas[$key] ??= [];
            foreach ($ids as $rid) {
                $rid = (int) $rid;
                if ($rid > 0 && ! in_array($rid, $asignadas[$key], true)) {
                    $asignadas[$key][] = $rid;
                }
            }
        }

        return $asignadas;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return array<int, list<int>>
     */
    private function comsPorComprobanteDelLegajo(Ordencompra $oc, array $precargaIds): array
    {
        if (! Schema::hasTable('comprobante_proveedor_recepcion')) {
            return [];
        }

        $cpIds = Comprobante_Proveedor::query()
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
            ->pluck('id')
            ->map(static fn ($id) => (int) $id)
            ->all();
        if ($cpIds === []) {
            return [];
        }

        $out = [];
        $rows = Comprobante_Proveedor_Recepcion::query()
            ->whereIn('comprobante_proveedor_id', $cpIds)
            ->orderBy('orden')
            ->orderBy('id')
            ->get(['comprobante_proveedor_id', 'recepcion_proveedor_id']);
        foreach ($rows as $row) {
            $cpId = (int) $row->comprobante_proveedor_id;
            $rid = (int) $row->recepcion_proveedor_id;
            if ($cpId <= 0 || $rid <= 0) {
                continue;
            }
            $out[$cpId] ??= [];
            $out[$cpId][] = $rid;
        }

        return $out;
    }

    private function claveLetraSucursalNumero(string $letra, int $sucursal, int $numero): string
    {
        $letra = strtoupper(trim($letra));
        if ($letra === '' || $numero <= 0) {
            return '';
        }

        return $letra.'|'.$sucursal.'|'.$numero;
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
     * Órdenes de pago aplicadas a los comprobantes del legajo (vía CC + aplicaciones OP).
     *
     * @param  list<int>  $comprobanteIds
     * @return array{
     *   lista: list<array<string, mixed>>,
     *   por_comprobante: array<int, list<array<string, mixed>>>
     * }
     */
    private function resolverPagosDeComprobantes(array $comprobanteIds): array
    {
        $vacio = ['lista' => [], 'por_comprobante' => []];
        if ($comprobanteIds === []) {
            return $vacio;
        }

        $ctRows = Proveedor_Cuentacorriente::query()
            ->whereIn('comprobante_proveedor_id', $comprobanteIds)
            ->get(['id', 'comprobante_proveedor_id']);
        if ($ctRows->isEmpty()) {
            return $this->pagosFallbackPorCuentacorriente($comprobanteIds);
        }

        $ctPorCp = [];
        foreach ($ctRows as $ct) {
            $cpId = (int) $ct->comprobante_proveedor_id;
            if ($cpId > 0) {
                $ctPorCp[$cpId][] = (int) $ct->id;
            }
        }
        $ctIds = $ctRows->pluck('id')->map(static fn ($id) => (int) $id)->all();
        $ctACp = [];
        foreach ($ctRows as $ct) {
            $ctACp[(int) $ct->id] = (int) $ct->comprobante_proveedor_id;
        }

        $aplicaciones = Pagoproveedor_Comprobante::query()
            ->whereIn('proveedor_cuentacorriente_id', $ctIds)
            ->with([
                'pagoproveedores:id,fecha,tipocomprobante,letra,sucursal,numerotransaccion,monto,moneda_id,estado',
                'pagoproveedores.monedas:id,abreviatura',
            ])
            ->orderByDesc('id')
            ->get();

        if ($aplicaciones->isEmpty()) {
            return $this->pagosFallbackPorCuentacorriente($comprobanteIds);
        }

        /** @var array<int, array<string, mixed>> $ops */
        $ops = [];
        /** @var array<int, array<int, array<string, mixed>>> $porCp */
        $porCp = [];

        foreach ($aplicaciones as $apl) {
            $pago = $apl->pagoproveedores;
            if ($pago === null) {
                continue;
            }
            $pagoId = (int) $pago->id;
            $ctId = (int) $apl->proveedor_cuentacorriente_id;
            $cpId = (int) ($ctACp[$ctId] ?? 0);
            if ($pagoId <= 0 || $cpId <= 0) {
                continue;
            }
            $montoApl = (float) $apl->montoaplicado;
            $filaPago = $this->filaPagoResumen($pago, $montoApl);

            if (! isset($ops[$pagoId])) {
                $ops[$pagoId] = $filaPago;
                $ops[$pagoId]['monto_aplicado_legajo'] = 0.0;
                $ops[$pagoId]['aplicaciones'] = [];
            } else {
                $ops[$pagoId]['monto_aplicado_legajo'] = (float) $ops[$pagoId]['monto_aplicado_legajo'] + $montoApl;
            }
            $ops[$pagoId]['aplicaciones'][] = [
                'comprobante_proveedor_id' => $cpId,
                'monto_aplicado' => $montoApl,
            ];

            if (! isset($porCp[$cpId][$pagoId])) {
                $porCp[$cpId][$pagoId] = $filaPago;
            } else {
                $porCp[$cpId][$pagoId]['monto_aplicado'] = (float) $porCp[$cpId][$pagoId]['monto_aplicado'] + $montoApl;
            }
        }

        // Si alguna CC tiene OP directo sin fila en pagoproveedor_comprobante, completar.
        $faltantes = [];
        foreach ($ctPorCp as $cpId => $_) {
            if (! isset($porCp[$cpId])) {
                $faltantes[] = $cpId;
            }
        }
        if ($faltantes !== []) {
            $fallback = $this->pagosFallbackPorCuentacorriente($faltantes);
            foreach ($fallback['lista'] as $op) {
                $opId = (int) ($op['id'] ?? 0);
                if ($opId > 0 && ! isset($ops[$opId])) {
                    $ops[$opId] = $op;
                }
            }
            foreach ($fallback['por_comprobante'] as $cpId => $lista) {
                foreach ($lista as $fila) {
                    $opId = (int) ($fila['id'] ?? 0);
                    if ($opId > 0 && ! isset($porCp[$cpId][$opId])) {
                        $porCp[$cpId][$opId] = $fila;
                    }
                }
            }
        }

        $lista = array_values($ops);
        usort($lista, static function (array $a, array $b): int {
            return strcmp((string) ($b['fecha_iso'] ?? ''), (string) ($a['fecha_iso'] ?? ''));
        });

        $porComprobante = [];
        foreach ($porCp as $cpId => $map) {
            $rows = array_values($map);
            usort($rows, static function (array $a, array $b): int {
                return strcmp((string) ($b['fecha_iso'] ?? ''), (string) ($a['fecha_iso'] ?? ''));
            });
            $porComprobante[$cpId] = $rows;
        }

        return ['lista' => $lista, 'por_comprobante' => $porComprobante];
    }

    /**
     * Fallback cuando no hay aplicaciones OP→CC (solo vínculo en cuenta corriente).
     *
     * @param  list<int>  $comprobanteIds
     * @return array{
     *   lista: list<array<string, mixed>>,
     *   por_comprobante: array<int, list<array<string, mixed>>>
     * }
     */
    private function pagosFallbackPorCuentacorriente(array $comprobanteIds): array
    {
        if ($comprobanteIds === []) {
            return ['lista' => [], 'por_comprobante' => []];
        }
        $rows = Proveedor_Cuentacorriente::query()
            ->with([
                'pagoproveedores:id,fecha,tipocomprobante,letra,sucursal,numerotransaccion,monto,moneda_id,estado',
                'pagoproveedores.monedas:id,abreviatura',
            ])
            ->whereIn('comprobante_proveedor_id', $comprobanteIds)
            ->where('pagoproveedor_id', '>', 0)
            ->orderByDesc('id')
            ->get();

        $ops = [];
        $porCp = [];
        foreach ($rows as $row) {
            $pago = $row->pagoproveedores;
            $pagoId = (int) $row->pagoproveedor_id;
            $cpId = (int) $row->comprobante_proveedor_id;
            if ($pagoId <= 0 || $cpId <= 0 || $pago === null) {
                continue;
            }
            $fila = $this->filaPagoResumen($pago, null);
            if (! isset($ops[$pagoId])) {
                $ops[$pagoId] = $fila;
                $ops[$pagoId]['monto_aplicado_legajo'] = $fila['monto'];
                $ops[$pagoId]['aplicaciones'] = [[
                    'comprobante_proveedor_id' => $cpId,
                    'monto_aplicado' => null,
                ]];
            }
            if (! isset($porCp[$cpId][$pagoId])) {
                $porCp[$cpId][$pagoId] = $fila;
            }
        }

        $lista = array_values($ops);
        usort($lista, static function (array $a, array $b): int {
            return strcmp((string) ($b['fecha_iso'] ?? ''), (string) ($a['fecha_iso'] ?? ''));
        });
        $porComprobante = [];
        foreach ($porCp as $cpId => $map) {
            $porComprobante[$cpId] = array_values($map);
        }

        return ['lista' => $lista, 'por_comprobante' => $porComprobante];
    }

    /**
     * @param  \App\Models\Compras\Pagoproveedor  $pago
     * @return array<string, mixed>
     */
    private function filaPagoResumen($pago, ?float $montoAplicado): array
    {
        $pagoId = (int) $pago->id;
        $fecha = $pago->fecha;
        $moneda = $pago->monedas?->abreviatura ?? '';

        return [
            'id' => $pagoId,
            'etiqueta' => $pago->etiquetaComprobante(),
            'fecha' => $fecha ? $fecha->format('d/m/Y') : '',
            'fecha_iso' => $fecha ? $fecha->format('Y-m-d') : '',
            'estado' => (string) ($pago->estado ?? ''),
            'monto' => $pago->monto !== null ? (float) $pago->monto : null,
            'moneda' => (string) $moneda,
            'monto_aplicado' => $montoAplicado,
            'url' => route('editar_pagoproveedor', ['id' => $pagoId]),
            'url_pdf' => route('imprimir_pagoproveedor', ['id' => $pagoId]),
        ];
    }

    /**
     * Adjunta pagos, total pagado y saldo a cada factura del paquete.
     *
     * @param  list<array<string, mixed>>  $facturas
     * @param  array<int, list<array<string, mixed>>>  $pagosPorComprobante
     * @return list<array<string, mixed>>
     */
    public function adjuntarPagosAFacturas(array $facturas, array $pagosPorComprobante): array
    {
        foreach ($facturas as &$fac) {
            $cpId = (int) ($fac['comprobante_proveedor_id'] ?? 0);
            if ($cpId <= 0) {
                $idRaw = (string) ($fac['id'] ?? '');
                if (preg_match('/^cp-(\d+)$/i', $idRaw, $m)) {
                    $cpId = (int) $m[1];
                    $fac['comprobante_proveedor_id'] = $cpId;
                }
            }
            $pagos = $cpId > 0 ? ($pagosPorComprobante[$cpId] ?? []) : [];
            $totalPagado = 0.0;
            $tieneMonto = false;
            foreach ($pagos as $p) {
                if (isset($p['monto_aplicado']) && $p['monto_aplicado'] !== null) {
                    $totalPagado += (float) $p['monto_aplicado'];
                    $tieneMonto = true;
                }
            }
            $totalFac = isset($fac['total']) && $fac['total'] !== null ? (float) $fac['total'] : null;
            $fac['pagos'] = $pagos;
            $fac['total_pagado'] = $tieneMonto ? $totalPagado : null;
            $fac['saldo'] = ($tieneMonto && $totalFac !== null)
                ? max(0.0, $totalFac - $totalPagado)
                : null;
            $fac['tiene_pagos'] = $pagos !== [];
        }
        unset($fac);

        return $facturas;
    }

    public function resolverPrecargaParaAsignacion(Ordencompra $oc, int|string $facturaRef): Precarga_Comprobante_Proveedor
    {
        $ref = trim((string) $facturaRef);
        if (preg_match('/^anita-(\d+)$/i', $ref, $m)) {
            return $this->precargaDesdeFacturaAnita($oc, (int) $m[1]);
        }
        if (preg_match('/^cp-\d+$/i', $ref) || ! ctype_digit($ref) || (int) $ref <= 0) {
            abort(404, 'La factura no pertenece a este legajo.');
        }

        return $this->assertPrecargaDelLegajo($oc, (int) $ref);
    }

    /**
     * Referencias que el modal Asignar COM puede persistir (precarga numérica o scan Anita).
     * Los ids sintéticos cp-N (CP ya en CxP sin precarga) se ignoran.
     */
    public function esReferenciaAsignable(int|string $ref): bool
    {
        $ref = trim((string) $ref);
        if ($ref === '') {
            return false;
        }
        if (preg_match('/^anita-\d+$/i', $ref)) {
            return true;
        }
        if (preg_match('/^cp-\d+$/i', $ref)) {
            return false;
        }

        return ctype_digit($ref) && (int) $ref > 0;
    }

    private function precargaDesdeFacturaAnita(Ordencompra $oc, int $documentoId): Precarga_Comprobante_Proveedor
    {
        $fila = OrdencompraLegajoAnitaScanFacturaSupport::filaDeOc($oc, $documentoId);
        if ($fila === null) {
            abort(404, 'La factura no pertenece a este legajo.');
        }

        $existente = $this->precargaDelLegajoCompatibleConScan($oc, $fila, $documentoId);
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

        $anulada = $this->precargaAnuladaDelScan($oc, $documentoId, $letra, $sucursal, $numero);
        if ($anulada !== null) {
            // En la apertura del modal este error se loguea y se saltea; al asignar COM a mano desde
            // el scan, el operador ve el motivo.
            throw ValidationException::withMessages([
                'precarga_id' => 'Este escaneo ya fue anulado en el legajo (factura #'.$anulada->id.', '
                    .$anulada->letra.'-'.$anulada->sucursal.'-'.$anulada->numerocomprobante
                    .'), así que no se vuelve a crear la precarga. Si hay que cargarlo igual, primero'
                    .' hay que revertir esa anulación.',
            ]);
        }

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

        // El CUIT sale del proveedor de la OC, no del scan: es parte de la clave fiscal y si queda
        // en NULL el índice único no puede comparar (en MySQL un NULL nunca choca con otro NULL).
        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos($proveedorId, null);

        try {
            $precarga = Precarga_Comprobante_Proveedor::query()->create([
                'empresa_id' => $empresaId,
                'provincia_destino_id' => ComprobanteProveedorProvinciaDestinoSupport::DEFAULT_PROVINCIA_ID,
                'proveedor_id' => $proveedorId,
                'identificacion_proveedor_cuit' => $cuit !== '' ? $cuit : null,
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
                'anita_scan_documento_id' => $documentoId,
                'pararevisar' => 1,
                'moneda' => strtoupper(trim((string) ($moneda->abreviatura ?: $moneda->nombre ?: 'PESOS'))),
                'moneda_id' => $monedaId,
                'cotizacion' => 1,
            ]);
        } catch (\Throwable $e) {
            // Dos operadores abriendo el mismo legajo a la vez llegan acá con el chequeo de arriba ya
            // vencido; el índice único los frena y el mensaje tiene que ser legible, no SQL.
            $mensaje = ComprobanteProveedorUnicidadSupport::mensajeViolacionUnicidadPrecarga(
                $e,
                $empresaId,
                $tipoId,
                $letra,
                $sucursal,
                $numero,
                $proveedorId,
            );
            if ($mensaje === null) {
                throw $e;
            }

            throw ValidationException::withMessages(['precarga_id' => $mensaje]);
        }

        return $this->asegurarPdfScanEnPrecarga($oc, $precarga, $documentoId, $fila);
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function precargaDelLegajoCompatibleConScan(
        Ordencompra $oc,
        array $fila,
        int $documentoId = 0,
    ): ?Precarga_Comprobante_Proveedor {
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

        // Primero por el documento del scan, que es el vínculo real: la letra, la sucursal y el número
        // se editan, y cuando alguien los corrige el match por número deja el scan huérfano y el modal
        // lo materializa de nuevo como precarga nueva.
        if ($documentoId > 0) {
            $porDocumento = $delLegajo->first(
                fn (Precarga_Comprobante_Proveedor $p) => (int) ($p->anita_scan_documento_id ?? 0) === $documentoId
            );
            if ($porDocumento) {
                return $porDocumento;
            }
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
        $enCxp = OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar((int) ($oc->sector_legajocompra_id ?? 0));
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $clave = $this->claveFacturaEtiqueta((string) ($scan['etiqueta'] ?? ''));
            if ($clave !== '' && isset($claves[$clave])) {
                continue;
            }
            if ($enCxp) {
                $anitaId = (string) ($scan['id'] ?? '');
                if ($anitaId !== '') {
                    $scan['url_cargar_cxp'] = route('crear_comprobante_proveedor', [
                        'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
                        'ordencompra_id' => (int) $oc->id,
                        'anita_id' => $anitaId,
                    ]);
                }
            }
            $out[] = $scan;
        }

        return $out;
    }

    /**
     * Una misma factura puede tener varios escaneos en Anita (se escaneó dos veces, o el proveedor
     * mandó el PDF de nuevo). Al materializarse, todos caen en la misma precarga y solo se ve el PDF
     * del primero: el resto queda invisible y sin forma de descartarlo. Acá se expone la lista completa.
     *
     * @param  list<array<string, mixed>>  $facturas
     * @return list<array<string, mixed>>
     */
    private function adjuntarScansAnitaAFacturas(Ordencompra $oc, array $facturas): array
    {
        $porClave = [];
        foreach ($facturas as $idx => $fac) {
            // Las filas que ya son un scan sin precarga traen su propio documento y su propio botón.
            if (($fac['origen'] ?? '') === 'anita') {
                continue;
            }
            $clave = $this->claveFacturaEtiqueta((string) ($fac['etiqueta'] ?? ''));
            if ($clave !== '') {
                $porClave[$clave][] = $idx;
            }
        }
        if ($porClave === []) {
            return $facturas;
        }

        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasDeOc($oc) as $scan) {
            $documentoId = (int) ($scan['documento_id'] ?? 0);
            $clave = $this->claveFacturaEtiqueta((string) ($scan['etiqueta'] ?? ''));
            if ($documentoId <= 0 || $clave === '' || ! isset($porClave[$clave])) {
                continue;
            }
            foreach ($porClave[$clave] as $idx) {
                $facturas[$idx]['scans_anita'][] = [
                    'documento_id' => $documentoId,
                    'etiqueta' => (string) ($scan['etiqueta'] ?? ''),
                    'fecha' => (string) ($scan['fecha'] ?? ''),
                    'url_pdf' => (string) ($scan['url_pdf'] ?? ''),
                ];
            }
        }

        return $facturas;
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
        // Este alta corre al abrir el modal (un GET). El lock evita que dos aperturas simultáneas
        // del mismo legajo entren las dos al alta de la misma precarga.
        $lock = OrdencompraLegajoScanMaterializacionLock::intentar((int) $oc->id);
        if ($lock === null) {
            Log::info('bandeja.scan_pdf_precarga_tomado', ['oc' => (int) $oc->id]);

            return;
        }

        try {
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
        } finally {
            OrdencompraLegajoScanMaterializacionLock::liberar($lock);
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
        $precarga = $this->vincularScanAnitaSiFalta($precarga, $documentoId);
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
     * Anular una precarga es una decisión deliberada ("esta factura no va en este legajo"), así que su
     * escaneo no puede volver a materializarse en la siguiente apertura del modal. Sin esto, anular una
     * cáscara duplicada y reabrir el legajo la recrea igual.
     *
     * Busca por el documento del scan, y si la anulada es vieja y no tiene ese vínculo grabado, cae al
     * número, que es como se venían uniendo scan y precarga.
     */
    private function precargaAnuladaDelScan(
        Ordencompra $oc,
        int $documentoId,
        string $letra,
        int $sucursal,
        int $numero,
    ): ?Precarga_Comprobante_Proveedor {
        $anuladas = Precarga_Comprobante_Proveedor::query()
            ->where('empresa_id', (int) $oc->empresa_id)
            ->whereRaw("UPPER(TRIM(COALESCE(estado, ''))) = ?", [PrecargaComprobanteEstados::ANULADA])
            ->get()
            ->filter(fn (Precarga_Comprobante_Proveedor $p) => $this->precargaPerteneceAlLegajo($oc, $p));

        if ($anuladas->isEmpty()) {
            return null;
        }

        if ($documentoId > 0) {
            $porDocumento = $anuladas->first(
                fn (Precarga_Comprobante_Proveedor $p) => (int) ($p->anita_scan_documento_id ?? 0) === $documentoId
            );
            if ($porDocumento) {
                return $porDocumento;
            }
        }

        if ($numero <= 0) {
            return null;
        }

        return $anuladas->first(function (Precarga_Comprobante_Proveedor $p) use ($letra, $sucursal, $numero) {
            $mismaLetra = $letra === '' || strtoupper(trim((string) $p->letra)) === $letra;

            return $mismaLetra
                && (int) $p->sucursal === $sucursal
                && (int) $p->numerocomprobante === $numero;
        });
    }

    /**
     * Graba de qué scan salió la precarga. Solo si está vacío: una factura puede tener más de un
     * escaneo asociado y lo que interesa guardar es el que la originó, no el último que se miró.
     */
    private function vincularScanAnitaSiFalta(
        Precarga_Comprobante_Proveedor $precarga,
        int $documentoId,
    ): Precarga_Comprobante_Proveedor {
        if ($documentoId <= 0 || (int) ($precarga->anita_scan_documento_id ?? 0) > 0) {
            return $precarga;
        }

        $precarga->anita_scan_documento_id = $documentoId;
        $precarga->save();

        return $precarga;
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
        if ($this->alinearTipoCrearianDuplicado($precarga, $tipoId)) {
            return $precarga;
        }
        $precarga->tipotransaccion_compra_id = $tipoId;
        $precarga->save();

        return $precarga->fresh(['tipotransaccion_compras']) ?? $precarga;
    }

    /**
     * El tipo define el código AFIP, y el código AFIP es parte de la clave fiscal. Alinear el tipo
     * mueve la precarga de clave, así que puede chocar contra otra precarga viva: es como nació el
     * par A-1151-748 (el mismo scan materializado dos veces, y al alinear el tipo quedaron iguales).
     */
    private function alinearTipoCrearianDuplicado(Precarga_Comprobante_Proveedor $precarga, int $tipoIdNuevo): bool
    {
        $numero = (int) ($precarga->numerocomprobante ?? 0);
        if ($numero <= 0) {
            return false;
        }

        $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
            $precarga->proveedor_id !== null ? (int) $precarga->proveedor_id : null,
            $precarga->identificacion_proveedor_cuit,
        );
        $codigoAfip = ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId($tipoIdNuevo);
        if ($cuit === '' || $codigoAfip === '') {
            return false;
        }

        $duplicado = ComprobanteProveedorUnicidadSupport::findDuplicadoPrecargaPorAfip(
            (int) $precarga->empresa_id,
            $codigoAfip,
            (string) $precarga->letra,
            (int) $precarga->sucursal,
            $numero,
            $cuit,
            (int) $precarga->id,
        );
        if ($duplicado === null) {
            return false;
        }

        Log::warning('bandeja.alinear_tipo_scan_duplicaria', [
            'precarga' => (int) $precarga->id,
            'tipo_actual' => (int) $precarga->tipotransaccion_compra_id,
            'tipo_nuevo' => $tipoIdNuevo,
            'codigo_afip_nuevo' => $codigoAfip,
            'choca_con_precarga' => (int) $duplicado->id,
        ]);

        return true;
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
     * @param  list<array<string, mixed>>  $facturas
     * @return list<array{value: string, label: string}>
     */
    private function tiposOpcionesCorreccion(Ordencompra $oc, array $facturas = []): array
    {
        $abrevsActuales = [];
        foreach ($facturas as $f) {
            $abrev = strtoupper(trim((string) ($f['tipo_abrev'] ?? $f['tipo_label'] ?? '')));
            if ($abrev !== '' && ! PrecargaProveedorAbreviaturaTipoSupport::esTipoGenerico($abrev)) {
                $abrevsActuales[] = $abrev;
            }
        }

        try {
            return PrecargaProveedorAbreviaturaTipoSupport::opcionesCorreccionTipo($oc, $abrevsActuales);
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
     * La factura puede estar cargada en CxP bajo otra OC o sin OC (import de Anita): comparar contra
     * los comprobantes del legajo no la ve, y el operador la trabaja hasta que el control de unicidad
     * fiscal la rechaza al grabar. Acá se busca por la misma clave que ese control (AFIP + CUIT).
     *
     * @param  list<array<string, mixed>>  $facturas
     * @return list<array<string, mixed>>
     */
    private function marcarDuplicadosFiscalesFueraDelLegajo(array $facturas): array
    {
        $pendientes = [];
        foreach ($facturas as $idx => $fac) {
            if (($fac['origen'] ?? 'precarga') !== 'precarga' || ! empty($fac['cargado_cxp'])) {
                continue;
            }
            $preId = (int) ($fac['id'] ?? 0);
            if ($preId > 0) {
                $pendientes[$preId] = $idx;
            }
        }
        if ($pendientes === []) {
            return $facturas;
        }

        $precargas = Precarga_Comprobante_Proveedor::query()
            ->whereIn('id', array_keys($pendientes))
            ->get([
                'id', 'empresa_id', 'proveedor_id', 'identificacion_proveedor_cuit',
                'tipotransaccion_compra_id', 'letra', 'sucursal', 'numerocomprobante',
            ]);

        foreach ($precargas as $pre) {
            $idx = $pendientes[(int) $pre->id] ?? null;
            if ($idx === null || (int) $pre->numerocomprobante <= 0) {
                continue;
            }
            $cuit = ComprobanteProveedorUnicidadSupport::resolverCuitDigitos(
                $pre->proveedor_id !== null ? (int) $pre->proveedor_id : null,
                $pre->identificacion_proveedor_cuit,
            );
            $codigoAfip = ComprobanteProveedorUnicidadSupport::codigoAfipDesdeTipoId(
                (int) $pre->tipotransaccion_compra_id
            );
            if ($cuit === '' || $codigoAfip === '') {
                continue;
            }
            $duplicado = ComprobanteProveedorUnicidadSupport::findDuplicadoPorAfip(
                (int) $pre->empresa_id,
                $codigoAfip,
                (string) $pre->letra,
                (int) $pre->sucursal,
                (int) $pre->numerocomprobante,
                $cuit,
            );
            if ($duplicado === null) {
                continue;
            }
            $facturas[$idx]['cargado_cxp'] = true;
            $facturas[$idx]['cargado_cxp_fuera_legajo'] = true;
            $facturas[$idx]['cargado_cxp_detalle'] = ComprobanteProveedorUnicidadSupport::mensajeDuplicado(
                $duplicado,
                $codigoAfip
            );
            $facturas[$idx]['url_comprobante'] = route('editar_comprobante_proveedor', ['id' => (int) $duplicado->id]);
        }

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
                $facturas[$idx]['comprobante_proveedor_id'] = (int) ($cp['id'] ?? 0) ?: null;
                if ($urlCp !== '') {
                    $facturas[$idx]['url_comprobante'] = $urlCp;
                }
                $facturas[$idx]['cargado_cxp'] = true;
                if (! isset($facturas[$idx]['total']) || $facturas[$idx]['total'] === null) {
                    $facturas[$idx]['total'] = $cp['total'] ?? null;
                }
                if (($facturas[$idx]['estado'] ?? '') === '' && ($cp['estado'] ?? '') !== '') {
                    $facturas[$idx]['estado'] = (string) $cp['estado'];
                }

                continue;
            }
            $tipo = (string) ($cp['tipo'] ?? 'FC');
            $facturas[] = [
                'id' => 'cp-'.(int) ($cp['id'] ?? 0),
                'comprobante_proveedor_id' => (int) ($cp['id'] ?? 0) ?: null,
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
        $anitaTipo = strtoupper(trim((string) ($rec->anita_tipo ?? '')));
        $anitaLetra = trim((string) ($rec->anita_letra ?? ''));
        $anitaSuc = ltrim((string) ($rec->anita_sucursal ?? ''), '0');
        $anitaNro = ltrim((string) ($rec->anita_nro ?? ''), '0');
        if ($anitaNro !== '') {
            $pref = $anitaTipo !== '' ? $anitaTipo : 'COM';
            $medio = trim($anitaLetra.' '.$anitaSuc);
            if ($pref === 'COM') {
                return $medio !== '' ? 'COM '.$medio.'-'.$anitaNro : 'COM '.$anitaNro;
            }

            return $medio !== '' ? $pref.' '.$medio.'-'.$anitaNro : $pref.' '.$anitaNro;
        }

        return 'COM #'.$nro;
    }
}
