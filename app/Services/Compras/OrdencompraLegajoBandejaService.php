<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Historia;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Compras\Precarga_Comprobante_Proveedor_Recepcion;
use App\Models\Compras\Proveedor_Cuentacorriente;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Stock\Recepcion_Proveedor;
use App\Repositories\Configuracion\EmpresaRepository;
use App\Support\Compras\ComprobanteProveedorFlujoOcComFacSupport;
use App\Support\Compras\OrdencompraEnvioCuentasAPagarGateSupport;
use App\Support\Compras\OrdencompraLegajoAnitaScanFacturaSupport;
use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
use App\Support\Compras\OrdencompraLegajoDocumentoTipoSupport;
use App\Support\Compras\OrdencompraLegajoGastronomiaSupport;
use App\Support\Compras\ComprobanteProveedorOrigenEntrada;
use App\Support\Compras\ComprobanteProveedorRetornoLegajoSupport;
use App\Support\Compras\OrdencompraListadoFiltros;
use App\Support\Compras\OrdencompraSectorVisibilidadSupport;
use App\Support\Compras\PrecargaComprobanteEstados;
use App\Support\Compras\PrecargaComprobanteOrigenEntrada;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class OrdencompraLegajoBandejaService
{
    /**
     * @param  array<string, mixed>  $filtros
     */
    public function paginar(array $filtros, int $perPage = 30): LengthAwarePaginator
    {
        $query = $this->queryBase($filtros);
        $pagina = $query->paginate($perPage)->withQueryString();
        $filas = $this->hidratar($pagina->getCollection());
        $pagina->setCollection($filas);

        return $pagina;
    }

    /**
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function listar(array $filtros, int $limite = 2000): Collection
    {
        $ocs = $this->queryBase($filtros)->limit($limite)->get();

        return $this->hidratar($ocs);
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    private function queryBase(array $filtros): Builder
    {
        $query = Ordencompra::query()
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'ordencompra.empresa_id',
                'ordencompra.proveedor_id',
                'ordencompra.centrocosto_id',
                'ordencompra.sector_legajocompra_id',
                'ordencompra.estadoordencompra',
                'ordencompra.tratamiento',
                'ordencompra.nota_legajo',
                'ordencompra.es_contrato',
                'ordencompra.contrato_requiere_recepcion',
                'ordencompra.contrato_vigencia_desde',
                'ordencompra.contrato_vigencia_hasta',
                'ordencompra.created_at',
            ])
            ->with([
                'empresas:id,codigo,nombre',
                'proveedores:id,codigo,nombre',
                'centrocostos:id,codigo,nombre',
                'sector_legajocompras:id,nombre',
            ])
            ->leftJoin('empresa', 'empresa.id', '=', 'ordencompra.empresa_id')
            ->leftJoin('centrocosto', 'centrocosto.id', '=', 'ordencompra.centrocosto_id')
            ->leftJoin('proveedor', 'proveedor.id', '=', 'ordencompra.proveedor_id')
            ->leftJoin('usuario', 'usuario.id', '=', 'ordencompra.creousuario_id')
            ->leftJoin('sector_legajocompra', 'sector_legajocompra.id', '=', 'ordencompra.sector_legajocompra_id')
            ->leftJoin('condicioncompra', 'condicioncompra.id', '=', 'ordencompra.condicioncompra_id')
            ->leftJoin('requisicion', 'requisicion.id', '=', 'ordencompra.requisicion_id')
            ->orderByDesc('ordencompra.fecha')
            ->orderByDesc('ordencompra.id');

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        OrdencompraSectorVisibilidadSupport::aplicarFiltro($query);
        $this->aplicarFiltrosBusqueda($query, $filtros);
        $this->aplicarFiltrosDocumento($query, $filtros);

        $ccGastro = OrdencompraLegajoGastronomiaSupport::centrocostoIdsCircuito();
        $tab = (string) ($filtros['tab'] ?? OrdencompraLegajoBandejaFiltros::TAB_TODOS);
        if ($tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA && $ccGastro !== []) {
            $query->whereIn('ordencompra.centrocosto_id', $ccGastro);
        } elseif ($tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO && $ccGastro !== []) {
            $query->where(function ($q) use ($ccGastro) {
                $q->whereNull('ordencompra.centrocosto_id')
                    ->orWhereNotIn('ordencompra.centrocosto_id', $ccGastro);
            });
        }

        $sectorCompras = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_COMPRAS
        );
        $sectorGastro = OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId();
        $sectorCxp = OrdencompraEnvioCuentasAPagarGateSupport::sectorIdPorNombre(
            OrdencompraEnvioCuentasAPagarGateSupport::SECTOR_CUENTAS_A_PAGAR
        );
        $sectorPagos = OrdencompraLegajoGastronomiaSupport::sectorPagosId();
        $sectorFin = OrdencompraLegajoGastronomiaSupport::sectorFinalizadoId();

        $vista = (string) ($filtros['vista'] ?? OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES);
        $lookupNumerico = $this->esBusquedaNumericaLookup($filtros);
        if ($lookupNumerico && $vista !== OrdencompraLegajoBandejaFiltros::VISTA_HISTORICO) {
            // Lookup por número: no filtrar por pestaña (sí respeta alcance de sector del usuario).
            $todos = array_values(array_filter([$sectorCompras, $sectorGastro, $sectorCxp, $sectorPagos, $sectorFin]));
            if ($todos !== []) {
                $query->whereIn('ordencompra.sector_legajocompra_id', $todos);
            }
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES) {
            $query->where(function ($q) use ($sectorCompras) {
                $q->where('ordencompra.sector_legajocompra_id', $sectorCompras);
                if ($sectorCompras <= 0) {
                    $q->orWhereNull('ordencompra.sector_legajocompra_id');
                }
            });
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS) {
            $activos = array_values(array_filter([$sectorCompras, $sectorGastro, $sectorCxp, $sectorPagos]));
            if ($activos !== []) {
                $query->whereIn('ordencompra.sector_legajocompra_id', $activos);
            }
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_CXP) {
            if ($sectorCxp > 0) {
                $query->where('ordencompra.sector_legajocompra_id', $sectorCxp);
            }
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_PAGOS) {
            if ($sectorPagos > 0) {
                $query->where('ordencompra.sector_legajocompra_id', $sectorPagos);
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($vista === OrdencompraLegajoBandejaFiltros::VISTA_ARCHIVADOS) {
            if ($sectorFin > 0) {
                $query->where('ordencompra.sector_legajocompra_id', $sectorFin);
            } else {
                $query->whereRaw('1 = 0');
            }
        } else {
            $todos = array_values(array_filter([$sectorCompras, $sectorGastro, $sectorCxp, $sectorPagos, $sectorFin]));
            $query->where(function ($q) use ($todos) {
                if ($todos !== []) {
                    $q->whereIn('ordencompra.sector_legajocompra_id', $todos);
                }
                $q->orWhereHas('ordencompra_historias');
            });
        }

        $this->aplicarAtajo($query, (string) ($filtros['atajo'] ?? ''));

        if ($vista === OrdencompraLegajoBandejaFiltros::VISTA_CXP) {
            $query->reorder()->orderBy('ordencompra.id');
        }

        return $query;
    }

    /**
     * Búsqueda solo-dígitos (nº OC / factura / COM / OP): no restringir por pestaña de vista,
     * para que un legajo en COMPRAS aparezca aunque el usuario esté en CxP.
     */
    private function esBusquedaNumericaLookup(array $filtros): bool
    {
        if (($filtros['modo'] ?? OrdencompraListadoFiltros::MODO_TODOS) === OrdencompraListadoFiltros::MODO_TODOS) {
            $valor = trim((string) ($filtros['valor'] ?? ''));
            if ($valor !== '' && ctype_digit($valor)) {
                return true;
            }
        }
        foreach (['nro_oc', 'nro_factura', 'nro_com', 'nro_op'] as $campo) {
            $v = trim((string) ($filtros[$campo] ?? ''));
            if ($v !== '' && ctype_digit($v)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Collection<int, Ordencompra>  $ocs
     * @return Collection<int, array<string, mixed>>
     */
    private function hidratar(Collection $ocs): Collection
    {
        $ids = $ocs->pluck('id')->map(fn ($id) => (int) $id)->all();
        $historias = $this->ultimaHistoriaPorOc($ids);
        $facturas = $this->facturasPorOc($ocs);
        foreach (OrdencompraLegajoAnitaScanFacturaSupport::facturasPorOcs($ocs) as $ocId => $anita) {
            $facturas[$ocId] = $this->fusionarScansAnitaSinDuplicar($facturas[$ocId] ?? [], $anita);
        }
        $coms = $this->comsPorOc($ids);
        $precargaIds = [];
        foreach ($facturas as $lista) {
            foreach ($lista as $fac) {
                if (($fac['origen'] ?? 'precarga') !== 'precarga') {
                    continue;
                }
                $preId = (int) ($fac['id'] ?? 0);
                if ($preId > 0) {
                    $precargaIds[] = $preId;
                }
            }
        }
        $asignadas = $this->asignacionesPorPrecarga($precargaIds);
        $comprobantes = $this->comprobantesPorOc($ids, $precargaIds, $facturas);
        $cpIds = [];
        foreach ($comprobantes as $lista) {
            foreach ($lista as $cp) {
                $cpIds[] = (int) $cp['id'];
            }
        }
        $pagos = $this->pagosPorComprobante($cpIds);
        $decisiones = $this->ultimaDecisionArbol($ids);

        return $ocs->values()->map(function (Ordencompra $oc) use ($historias, $facturas, $coms, $asignadas, $comprobantes, $pagos, $decisiones) {
            $id = (int) $oc->id;
            $hist = $historias[$id] ?? null;
            $desde = $hist['fecha'] ?? ($oc->created_at ? Carbon::parse($oc->created_at) : null);
            $facs = $facturas[$id] ?? [];
            $comList = $coms[$id] ?? [];
            $tieneFactura = $facs !== [];
            $tieneCom = $comList !== [];
            $exigeCom = OrdencompraEnvioCuentasAPagarGateSupport::exigeRecepcionCom($oc, $tieneCom);
            $decision = $decisiones[$id] ?? null;
            $esGastro = OrdencompraLegajoGastronomiaSupport::requiereCircuito($oc);
            $primeraFac = $facs[0] ?? null;
            $primeraCom = $comList[0] ?? null;
            $cps = $comprobantes[$id] ?? [];
            $primeraCp = $cps[0] ?? null;
            $pago = null;
            foreach ($cps as $cp) {
                if (isset($pagos[(int) $cp['id']])) {
                    $pago = $pagos[(int) $cp['id']];
                    break;
                }
            }
            $notaLegajo = trim((string) ($oc->nota_legajo ?? ''));
            $facturasLegajo = $this->resumenFacturasLegajo($cps, $facs);
            $etiquetasFactura = array_map(
                static fn (array $f) => (string) ($f['numero'] ?? ''),
                $facturasLegajo
            );
            // Derivar de datos ya hidratados (evitar N+1 Anita/SQL por fila).
            $pendientes = $this->documentosPendientesDesdeHidratacion($facs, $cps);
            $siguiente = $pendientes[0] ?? null;
            $enCxp = OrdencompraEnvioCuentasAPagarGateSupport::esSectorCuentasAPagar((int) ($oc->sector_legajocompra_id ?? 0));
            $urlCargar = null;
            if ($enCxp && $siguiente !== null) {
                $facPend = null;
                if (! empty($siguiente['precarga_id'])) {
                    foreach ($facs as $f) {
                        if ((int) ($f['id'] ?? 0) === (int) $siguiente['precarga_id']) {
                            $facPend = $f;
                            break;
                        }
                    }
                } elseif (! empty($siguiente['anita_id'])) {
                    foreach ($facs as $f) {
                        if ((string) ($f['id'] ?? '') === (string) $siguiente['anita_id']) {
                            $facPend = $f;
                            break;
                        }
                    }
                }
                $urlCargar = $this->urlCargarFacturaDesdeLegajo($id, $facPend ?? [
                    'origen' => ! empty($siguiente['anita_id']) ? 'anita' : 'precarga',
                    'id' => $siguiente['precarga_id'] ?? $siguiente['anita_id'] ?? 0,
                ]);
            }
            $faltanComDocs = $exigeCom
                ? $this->documentosSinComDesdeHidratacion($facs, $cps, $asignadas)
                : [];
            $comAsignadaOk = ! $exigeCom || $faltanComDocs === [];
            $tieneAlgunaFcCargada = $primeraCp !== null;
            $todasCargadas = $tieneAlgunaFcCargada && $pendientes === [];

            return [
                'id' => $id,
                'numero' => (string) $oc->numeroordencompra,
                'fecha' => $oc->fecha ? Carbon::parse($oc->fecha)->format('d/m/Y') : '',
                'empresa' => (string) ($oc->empresas->nombre ?? ''),
                'proveedor' => (string) ($oc->proveedores->nombre ?? ''),
                'centrocosto' => trim(($oc->centrocostos->codigo ?? '').' '.($oc->centrocostos->nombre ?? '')),
                'sector' => (string) ($oc->sector_legajocompras->nombre ?? '—'),
                'sector_id' => (int) ($oc->sector_legajocompra_id ?? 0),
                'estado_oc' => (string) $oc->estadoordencompra,
                'dias' => OrdencompraLegajoGastronomiaSupport::diasEnUbicacion($desde),
                'fecha_ubicacion' => $desde ? $desde->format('d/m/Y H:i') : '',
                'tiene_factura' => $tieneFactura,
                'tiene_com' => $tieneCom,
                'tiene_com_asignada' => $comAsignadaOk,
                'tiene_comprobante' => $todasCargadas,
                'tiene_comprobante_parcial' => $tieneAlgunaFcCargada && ! $todasCargadas,
                'tiene_pago' => $pago !== null,
                'exige_com' => $exigeCom,
                'paquete_ok' => $tieneFactura && (! $exigeCom || ($tieneCom && $comAsignadaOk)),
                'es_anticipada' => ComprobanteProveedorFlujoOcComFacSupport::esOcAnticipada($oc),
                'es_gastronomia' => $esGastro,
                'nota_legajo' => $notaLegajo,
                'tiene_nota' => $notaLegajo !== '',
                'facturas_legajo' => $facturasLegajo,
                'etiquetas_factura' => $etiquetasFactura,
                'pendientes_carga' => count($pendientes),
                'siguiente_pendiente' => $siguiente['etiqueta'] ?? null,
                'puede_enviar' => $esGastro && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviar($oc),
                'puede_enviar_cxp' => ! $esGastro && OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarCuentasAPagar(
                    $oc,
                    count($pendientes) > 0
                ),
                'puede_enviar_pagos' => OrdencompraLegajoGastronomiaSupport::puedeMostrarEnviarPagos(
                    $oc,
                    $tieneAlgunaFcCargada,
                    count($pendientes) > 0
                ),
                'puede_devolver_cxp' => OrdencompraLegajoGastronomiaSupport::puedeDevolverACuentasAPagar($oc),
                'puede_devolver_compras' => OrdencompraLegajoGastronomiaSupport::puedeDevolverACompras($oc),
                'puede_finalizar' => OrdencompraLegajoGastronomiaSupport::puedeFinalizar($oc),
                'decision' => $decision['estado'] ?? '',
                'firmante' => $decision['usuario'] ?? '',
                'fecha_decision' => $decision['fecha'] ?? '',
                'comentario_decision' => $decision['comentario'] ?? '',
                'url_oc' => can('editar-ordencompra', false)
                    ? route('editar_ordencompra', ['id' => $id])
                    : route('solo_consulta_ordencompra', ['id' => $id]),
                'url_factura' => $primeraFac['url_pdf'] ?? null,
                'url_com' => $primeraCom['url_pdf'] ?? null,
                'url_historia' => route('ordencompra_legajo_bandeja_historia', ['id' => $id]),
                'url_paquete' => route('ordencompra_legajo_bandeja_paquete', ['id' => $id]),
                'url_nota' => route('ordencompra_legajo_bandeja_nota', ['id' => $id]),
                'url_asignar_com' => route('ordencompra_legajo_bandeja_asignar_com', ['id' => $id]),
                'url_asignar_factura' => route('ordencompra_asignar_factura_pdf', ['id' => $id]),
                'url_cargar_cxp' => $urlCargar,
                'url_comprobante' => $primeraCp['url'] ?? null,
                'url_pago' => $pago['url'] ?? null,
                'etiqueta_pago' => $pago['etiqueta'] ?? '',
                'url_enviar' => route('ordencompra_enviar_gastronomia', ['id' => $id]),
                'url_enviar_cxp' => route('ordencompra_enviar_cuentas_a_pagar', ['id' => $id]),
                'url_enviar_pagos' => route('ordencompra_enviar_pagos', ['id' => $id]),
                'url_devolver_cxp' => route('ordencompra_devolver_cuentas_a_pagar', ['id' => $id]),
                'url_devolver_compras' => route('ordencompra_devolver_compras', ['id' => $id]),
                'url_finalizar' => route('ordencompra_finalizar_legajo', ['id' => $id]),
            ];
        });
    }

    /**
     * @param  array<string, mixed>|null  $primeraFac
     */
    private function urlCargarFacturaDesdeLegajo(int $ocId, ?array $primeraFac): string
    {
        $params = [
            'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
            'ordencompra_id' => $ocId,
        ];
        if (($primeraFac['origen'] ?? '') === 'precarga' && (int) ($primeraFac['id'] ?? 0) > 0) {
            $params['precarga_id'] = (int) $primeraFac['id'];
        }

        return route('crear_comprobante_proveedor', $params);
    }

    /**
     * Pendientes de carga CxP a partir de facturas/CPs ya hidratados (sin queries extra).
     *
     * @param  list<array<string, mixed>>  $facs
     * @param  list<array<string, mixed>>  $cps
     * @return list<array{precarga_id: int|null, anita_id: string|null, tipo: string, etiqueta: string, fecha: string|null, orden: int}>
     */
    private function documentosPendientesDesdeHidratacion(array $facs, array $cps): array
    {
        $cargadas = [];
        $preConCp = [];
        foreach ($cps as $cp) {
            $clave = $this->claveFacturaEtiqueta((string) ($cp['numero'] ?? $cp['etiqueta'] ?? ''));
            if ($clave !== '') {
                $cargadas[$clave] = true;
            }
            $preId = (int) ($cp['precarga_id'] ?? 0);
            if ($preId > 0) {
                $preConCp[$preId] = true;
            }
        }

        $docs = [];
        foreach ($facs as $fac) {
            $origen = (string) ($fac['origen'] ?? 'precarga');
            if ($origen === 'precarga' && isset($preConCp[(int) ($fac['id'] ?? 0)])) {
                continue;
            }
            if (! empty($fac['cargada_anita']) || PrecargaComprobanteEstados::esCargadaAnita($fac['estado_precarga'] ?? null)) {
                continue;
            }
            $etiqueta = trim((string) ($fac['etiqueta'] ?? $fac['numero'] ?? ''));
            $clave = $this->claveFacturaEtiqueta($etiqueta);
            if ($clave !== '' && isset($cargadas[$clave])) {
                continue;
            }
            $tipo = (string) ($fac['tipo'] ?? 'FC');
            $fecha = null;
            $fechaRaw = (string) ($fac['fecha'] ?? '');
            if ($fechaRaw !== '') {
                // d/m/Y o Y-m-d
                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $fechaRaw, $m)) {
                    $fecha = $m[3].'-'.$m[2].'-'.$m[1];
                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}/', $fechaRaw)) {
                    $fecha = substr($fechaRaw, 0, 10);
                }
            }
            $docs[] = [
                'precarga_id' => $origen === 'precarga' ? (int) ($fac['id'] ?? 0) : null,
                'anita_id' => $origen === 'anita' ? (string) ($fac['id'] ?? '') : null,
                'tipo' => $tipo,
                'etiqueta' => $etiqueta !== ''
                    ? OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $etiqueta)
                    : OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo),
                'fecha' => $fecha,
                'orden' => OrdencompraLegajoDocumentoTipoSupport::prioridadCarga($tipo),
            ];
        }

        usort($docs, static function (array $a, array $b): int {
            $po = ((int) $a['orden']) <=> ((int) $b['orden']);
            if ($po !== 0) {
                return $po;
            }
            $fa = (string) ($a['fecha'] ?? '');
            $fb = (string) ($b['fecha'] ?? '');
            if ($fa !== $fb) {
                return $fa <=> $fb;
            }

            return ((int) ($a['precarga_id'] ?? 0)) <=> ((int) ($b['precarga_id'] ?? 0));
        });

        return array_values($docs);
    }

    /**
     * Documentos que exigen COM y aún no la tienen, desde hidratación.
     *
     * @param  list<array<string, mixed>>  $facs
     * @param  array<int, list<int>>  $asignadas
     * @return list<string>
     */
    /**
     * Documentos que exigen COM y aún no la tienen, desde hidratación.
     * Ignora facturas ya cargadas en CxP (aunque la precarga no tenga COM).
     *
     * @param  list<array<string, mixed>>  $facs
     * @param  list<array<string, mixed>>  $cps
     * @param  array<int, list<int>>  $asignadas
     * @return list<string>
     */
    private function documentosSinComDesdeHidratacion(array $facs, array $cps, array $asignadas): array
    {
        $cargadas = [];
        $preConCp = [];
        foreach ($cps as $cp) {
            $clave = $this->claveFacturaEtiqueta((string) ($cp['numero'] ?? $cp['etiqueta'] ?? ''));
            if ($clave !== '') {
                $cargadas[$clave] = true;
            }
            $preId = (int) ($cp['precarga_id'] ?? 0);
            if ($preId > 0) {
                $preConCp[$preId] = true;
            }
        }

        $out = [];
        foreach ($facs as $fac) {
            $origen = (string) ($fac['origen'] ?? 'precarga');
            if ($origen === 'precarga' && isset($preConCp[(int) ($fac['id'] ?? 0)])) {
                continue;
            }
            if (! empty($fac['cargada_anita']) || PrecargaComprobanteEstados::esCargadaAnita($fac['estado_precarga'] ?? null)) {
                continue;
            }
            $etiqueta = trim((string) ($fac['etiqueta'] ?? $fac['numero'] ?? ''));
            $clave = $this->claveFacturaEtiqueta($etiqueta);
            if ($clave !== '' && isset($cargadas[$clave])) {
                continue;
            }
            $tipo = (string) ($fac['tipo'] ?? 'FC');
            $exige = array_key_exists('exige_com', $fac)
                ? (bool) $fac['exige_com']
                : OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipo);
            if (! $exige) {
                continue;
            }
            if ($origen === 'precarga') {
                $preId = (int) ($fac['id'] ?? 0);
                if ($preId > 0 && ! empty($asignadas[$preId])) {
                    continue;
                }
            }
            $out[] = $etiqueta !== ''
                ? OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $etiqueta)
                : OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo);
        }

        return $out;
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosBusqueda(Builder $query, array $filtros): void
    {
        if (! empty($filtros['empresa_id'])) {
            $query->where('ordencompra.empresa_id', (int) $filtros['empresa_id']);
        }
        if (! OrdencompraListadoFiltros::tieneCriteriosTexto($filtros)) {
            return;
        }

        $valor = trim((string) ($filtros['valor'] ?? ''));
        $modo = $filtros['modo'] ?? OrdencompraListadoFiltros::MODO_TODOS;

        // Solo dígitos: igualdad indexada. Sin LIKE ni EXISTS correlacionados (cuelgan el COUNT).
        if ($modo === OrdencompraListadoFiltros::MODO_TODOS && $valor !== '' && ctype_digit($valor)) {
            $id = (int) $valor;
            $ocIdsDoc = $this->ocIdsPorNumeroDocumentoExacto($id);
            $query->where(function ($q) use ($id, $ocIdsDoc) {
                $q->where('ordencompra.id', $id)
                    ->orWhere('ordencompra.numeroordencompra', $id)
                    ->orWhere('requisicion.numerorequisicion', $id);
                if ($ocIdsDoc !== []) {
                    $q->orWhereIn('ordencompra.id', $ocIdsDoc);
                }
            });

            return;
        }

        $query->where(function ($q) use ($filtros) {
            $inner = $filtros;
            $inner['empresa_id'] = null;
            OrdencompraListadoFiltros::aplicar($q, $inner);
            $valorInner = trim((string) ($filtros['valor'] ?? ''));
            if ($valorInner !== '' && ($filtros['modo'] ?? OrdencompraListadoFiltros::MODO_TODOS) === OrdencompraListadoFiltros::MODO_TODOS) {
                // Texto libre: no expandir a EXISTS de documentos (usar Nº factura/COM/OP del panel).
            }
        });
    }

    /**
     * OCs que tienen factura/COM/OP con ese número exacto (consultas indexadas, no EXISTS por fila).
     *
     * @return list<int>
     */
    private function ocIdsPorNumeroDocumentoExacto(int $numero): array
    {
        if ($numero <= 0) {
            return [];
        }

        $ids = [];

        foreach (
            \Illuminate\Support\Facades\DB::table('comprobante_proveedor')
                ->where('numerocomprobante', $numero)
                ->where(function ($w) {
                    $w->whereNull('estado')
                        ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
                })
                ->whereNotNull('ordencompra_id')
                ->where('ordencompra_id', '>', 0)
                ->limit(200)
                ->pluck('ordencompra_id') as $ocId
        ) {
            $ids[(int) $ocId] = true;
        }

        $pares = \Illuminate\Support\Facades\DB::table('precarga_comprobante_proveedor')
            ->where('numerocomprobante', $numero)
            ->whereNotNull('numeroordencompra')
            ->where('numeroordencompra', '!=', '')
            ->limit(200)
            ->get(['empresa_id', 'numeroordencompra']);
        if ($pares->isNotEmpty()) {
            $ocQuery = Ordencompra::query()->select('id');
            $ocQuery->where(function ($q) use ($pares) {
                foreach ($pares as $par) {
                    $q->orWhere(function ($w) use ($par) {
                        $w->where('empresa_id', (int) $par->empresa_id)
                            ->where('numeroordencompra', (int) $par->numeroordencompra);
                    });
                }
            });
            foreach ($ocQuery->limit(200)->pluck('id') as $ocId) {
                $ids[(int) $ocId] = true;
            }
        }

        foreach (
            \Illuminate\Support\Facades\DB::table('recepcion_proveedor')
                ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where(function ($w) use ($numero) {
                    $w->where('id', $numero)
                        ->orWhere('anita_nro', $numero)
                        ->orWhere('numerorecepcion', (string) $numero);
                })
                ->whereNotNull('ordencompra_id')
                ->where('ordencompra_id', '>', 0)
                ->limit(200)
                ->pluck('ordencompra_id') as $ocId
        ) {
            $ids[(int) $ocId] = true;
        }

        foreach (
            \Illuminate\Support\Facades\DB::table('proveedor_cuentacorriente as pcc')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'pcc.pagoproveedor_id')
                ->where('pcc.pagoproveedor_id', '>', 0)
                ->where(function ($w) use ($numero) {
                    $w->where('pp.id', $numero)
                        ->orWhere('pp.numerotransaccion', $numero)
                        ->orWhere('pp.numerotransaccion', (string) $numero);
                })
                ->whereNotNull('cp.ordencompra_id')
                ->where('cp.ordencompra_id', '>', 0)
                ->limit(200)
                ->pluck('cp.ordencompra_id') as $ocId
        ) {
            $ids[(int) $ocId] = true;
        }

        return array_keys($ids);
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     * @param  array<string, mixed>  $filtros
     */
    private function aplicarFiltrosDocumento(Builder $query, array $filtros): void
    {
        $nroOc = trim((string) ($filtros['nro_oc'] ?? ''));
        if ($nroOc !== '') {
            $soloDigitos = preg_replace('/\D+/', '', $nroOc) ?? '';
            if ($soloDigitos !== '' && ctype_digit($soloDigitos)) {
                $query->where('ordencompra.numeroordencompra', (int) $soloDigitos);
            } else {
                $query->where('ordencompra.numeroordencompra', 'like', '%'.$nroOc.'%');
            }
        }
        $nroFac = trim((string) ($filtros['nro_factura'] ?? ''));
        if ($nroFac !== '') {
            $digitos = preg_replace('/\D+/', '', $nroFac) ?: '';
            if ($digitos !== '' && ctype_digit($digitos)) {
                $idsFac = $this->ocIdsPorFacturaExacta((int) $digitos);
                $query->whereIn('ordencompra.id', $idsFac !== [] ? $idsFac : [0]);
            } else {
                $query->where(function ($q) use ($nroFac) {
                    $this->whereExisteFacturaNumero($q, $nroFac);
                });
            }
        }
        $nroCom = trim((string) ($filtros['nro_com'] ?? ''));
        if ($nroCom !== '') {
            if (ctype_digit($nroCom)) {
                $n = (int) $nroCom;
                $ids = \Illuminate\Support\Facades\DB::table('recepcion_proveedor')
                    ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                    ->where(function ($w) use ($n, $nroCom) {
                        $w->where('id', $n)
                            ->orWhere('anita_nro', $n)
                            ->orWhere('numerorecepcion', $nroCom);
                    })
                    ->where('ordencompra_id', '>', 0)
                    ->limit(200)
                    ->pluck('ordencompra_id')
                    ->map(static fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $query->whereIn('ordencompra.id', $ids !== [] ? $ids : [0]);
            } else {
                $query->where(function ($q) use ($nroCom) {
                    $this->whereExisteComNumero($q, $nroCom);
                });
            }
        }
        $nroOp = trim((string) ($filtros['nro_op'] ?? ''));
        if ($nroOp !== '') {
            if (ctype_digit($nroOp)) {
                $n = (int) $nroOp;
                $ids = \Illuminate\Support\Facades\DB::table('proveedor_cuentacorriente as pcc')
                    ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                    ->join('pagoproveedor as pp', 'pp.id', '=', 'pcc.pagoproveedor_id')
                    ->where('pcc.pagoproveedor_id', '>', 0)
                    ->where(function ($w) use ($n, $nroOp) {
                        $w->where('pp.id', $n)
                            ->orWhere('pp.numerotransaccion', $n)
                            ->orWhere('pp.numerotransaccion', $nroOp);
                    })
                    ->where('cp.ordencompra_id', '>', 0)
                    ->limit(200)
                    ->pluck('cp.ordencompra_id')
                    ->map(static fn ($id) => (int) $id)
                    ->unique()
                    ->values()
                    ->all();
                $query->whereIn('ordencompra.id', $ids !== [] ? $ids : [0]);
            } else {
                $query->where(function ($q) use ($nroOp) {
                    $this->whereExistePagoNumero($q, $nroOp);
                });
            }
        }
    }

    /**
     * @return list<int>
     */
    private function ocIdsPorFacturaExacta(int $numero): array
    {
        if ($numero <= 0) {
            return [];
        }
        $ids = [];
        foreach (
            \Illuminate\Support\Facades\DB::table('comprobante_proveedor')
                ->where('numerocomprobante', $numero)
                ->where(function ($w) {
                    $w->whereNull('estado')
                        ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
                })
                ->where('ordencompra_id', '>', 0)
                ->limit(200)
                ->pluck('ordencompra_id') as $ocId
        ) {
            $ids[(int) $ocId] = true;
        }
        $pares = \Illuminate\Support\Facades\DB::table('precarga_comprobante_proveedor')
            ->where('numerocomprobante', $numero)
            ->whereNotNull('numeroordencompra')
            ->where('numeroordencompra', '!=', '')
            ->limit(200)
            ->get(['empresa_id', 'numeroordencompra']);
        if ($pares->isNotEmpty()) {
            $ocQuery = Ordencompra::query()->select('id')->where(function ($q) use ($pares) {
                foreach ($pares as $par) {
                    $q->orWhere(function ($w) use ($par) {
                        $w->where('empresa_id', (int) $par->empresa_id)
                            ->where('numeroordencompra', (int) $par->numeroordencompra);
                    });
                }
            });
            foreach ($ocQuery->limit(200)->pluck('id') as $ocId) {
                $ids[(int) $ocId] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $query
     */
    private function aplicarAtajo(Builder $query, string $atajo): void
    {
        if ($atajo === '') {
            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_SIN_FACTURA) {
            $query->where(function ($q) {
                $this->whereExistePrecargaPdf($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_SIN_COM) {
            $query->where(function ($q) {
                $this->whereExisteCom($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_COM_SIN_ASIGNAR) {
            $query->where(function ($q) {
                $this->whereExistePrecargaPdf($q, false);
            })->where(function ($q) {
                $this->whereExisteCom($q, false);
            })->where(function ($q) {
                $this->whereExisteAsignacionCom($q, true);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR) {
            // Al menos una precarga con PDF aún no cargada (OC anual: otras FC ya
            // contabilizadas no sacan el legajo de esta bandeja).
            $query->where(function ($q) {
                $this->whereExistePrecargaPdfPendienteCarga($q, false);
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_FC_CARGADA) {
            // CP en ERP o precarga marcada como ya cargada en Anita.
            $query->where(function ($q) {
                $q->where(function ($w) {
                    $this->whereExisteComprobante($w, false);
                })->orWhere(function ($w) {
                    $this->whereExistePrecargaCargadaAnita($w, false);
                });
            });

            return;
        }
        if ($atajo === OrdencompraLegajoBandejaFiltros::ATAJO_CON_PAGO) {
            $query->where(function ($q) {
                $this->whereExistePago($q, false);
            });
        }
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePrecargaPdf(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor as pcp')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra')
                ->whereNotNull('pcp.rutaalmacenamiento')
                ->where('pcp.rutaalmacenamiento', '!=', '')
                ->where(function ($w) {
                    $w->whereNull('pcp.estado')
                        ->orWhereRaw('UPPER(TRIM(pcp.estado)) != ?', ['ANULADA']);
                });
        });
    }

    /**
     * Precarga con PDF aún pendiente de carga en CxP.
     * Excluye ANULADA / CARGADA_ANITA y las que ya tienen CP (por precarga_id o letra+sucursal+número).
     *
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePrecargaPdfPendienteCarga(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor as pcp')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra')
                ->whereNotNull('pcp.rutaalmacenamiento')
                ->where('pcp.rutaalmacenamiento', '!=', '')
                ->where(function ($w) {
                    $w->whereNull('pcp.estado')
                        ->orWhereRaw(
                            'UPPER(TRIM(pcp.estado)) NOT IN (?, ?)',
                            ['ANULADA', PrecargaComprobanteEstados::CARGADA_ANITA]
                        );
                })
                ->whereNotExists(function ($cp) {
                    $cp->selectRaw('1')
                        ->from('comprobante_proveedor as cp')
                        ->where(function ($w) {
                            $w->whereColumn('cp.precarga_comprobante_proveedor_id', 'pcp.id')
                                ->orWhere(function ($m) {
                                    $m->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                                        ->whereColumn('cp.letra', 'pcp.letra')
                                        ->whereColumn('cp.sucursal', 'pcp.sucursal')
                                        ->whereColumn('cp.numerocomprobante', 'pcp.numerocomprobante');
                                });
                        })
                        ->where(function ($w) {
                            $w->whereNull('cp.estado')
                                ->orWhereRaw('UPPER(TRIM(cp.estado)) != ?', ['ANULADA']);
                        });
                });
        });
    }

    /**
     * Precarga marcada como ya cargada en Anita (con PDF).
     *
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePrecargaCargadaAnita(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor as pcp')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra')
                ->whereNotNull('pcp.rutaalmacenamiento')
                ->where('pcp.rutaalmacenamiento', '!=', '')
                ->whereRaw('UPPER(TRIM(pcp.estado)) = ?', [PrecargaComprobanteEstados::CARGADA_ANITA]);
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteCom(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('recepcion_proveedor as rp')
                ->whereColumn('rp.ordencompra_id', 'ordencompra.id')
                ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where('rp.estado', Recepcion_Proveedor::ESTADO_CONFIRMADA);
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteAsignacionCom(Builder $q, bool $not = false): void
    {
        if (! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            if (! $not) {
                $q->whereRaw('1 = 0');
            }

            return;
        }
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor_recepcion as pcr')
                ->join('precarga_comprobante_proveedor as pcp', 'pcp.id', '=', 'pcr.precarga_comprobante_proveedor_id')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteComprobante(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('comprobante_proveedor as cp')
                ->where(function ($w) {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhere(function ($p) {
                            $p->whereNotNull('cp.precarga_comprobante_proveedor_id')
                                ->whereExists(function ($pre) {
                                    $pre->selectRaw('1')
                                        ->from('precarga_comprobante_proveedor as pcp')
                                        ->whereColumn('pcp.id', 'cp.precarga_comprobante_proveedor_id')
                                        ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                                        ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
                                });
                        });
                })
                ->where(function ($w) {
                    $w->whereNull('cp.estado')
                        ->orWhereRaw('UPPER(TRIM(cp.estado)) != ?', ['ANULADA']);
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePago(Builder $q, bool $not = false): void
    {
        $method = $not ? 'whereNotExists' : 'whereExists';
        $q->{$method}(function ($e) {
            $e->selectRaw('1')
                ->from('proveedor_cuentacorriente as pcc')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                ->where('pcc.pagoproveedor_id', '>', 0)
                ->where(function ($w) {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhereExists(function ($pre) {
                            $pre->selectRaw('1')
                                ->from('precarga_comprobante_proveedor as pcp')
                                ->whereColumn('pcp.id', 'cp.precarga_comprobante_proveedor_id')
                                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
                        });
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteFacturaNumero(Builder $q, string $valor): void
    {
        $digitos = preg_replace('/\D+/', '', $valor) ?: $valor;
        $esEntero = ctype_digit((string) $digitos);
        $n = $esEntero ? (int) $digitos : 0;
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $q->whereExists(function ($e) use ($digitos, $like, $esEntero, $n) {
            $e->selectRaw('1')
                ->from('precarga_comprobante_proveedor as pcp')
                ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra')
                ->where(function ($w) use ($digitos, $like, $esEntero, $n) {
                    if ($esEntero && $n > 0) {
                        $w->where('pcp.numerocomprobante', $n);
                    } else {
                        $w->where('pcp.numerocomprobante', 'like', '%'.$digitos.'%')
                            ->orWhere('pcp.numerocomprobante', 'like', $like);
                    }
                });
        })->orWhereExists(function ($e) use ($digitos, $like, $esEntero, $n) {
            $e->selectRaw('1')
                ->from('comprobante_proveedor as cp')
                ->where(function ($w) {
                    $w->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                        ->orWhere(function ($p) {
                            $p->whereNotNull('cp.precarga_comprobante_proveedor_id')
                                ->whereExists(function ($pre) {
                                    $pre->selectRaw('1')
                                        ->from('precarga_comprobante_proveedor as pcp')
                                        ->whereColumn('pcp.id', 'cp.precarga_comprobante_proveedor_id')
                                        ->whereColumn('pcp.empresa_id', 'ordencompra.empresa_id')
                                        ->whereColumn('pcp.numeroordencompra', 'ordencompra.numeroordencompra');
                                });
                        });
                })
                ->where(function ($w) {
                    $w->whereNull('cp.estado')
                        ->orWhereRaw('UPPER(TRIM(cp.estado)) != ?', ['ANULADA']);
                })
                ->where(function ($w) use ($digitos, $like, $esEntero, $n) {
                    if ($esEntero && $n > 0) {
                        $w->where('cp.numerocomprobante', $n);
                    } else {
                        $w->where('cp.numerocomprobante', 'like', '%'.$digitos.'%')
                            ->orWhere('cp.numerocomprobante', 'like', $like);
                    }
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExisteComNumero(Builder $q, string $valor): void
    {
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $q->whereExists(function ($e) use ($like, $valor) {
            $e->selectRaw('1')
                ->from('recepcion_proveedor as rp')
                ->whereColumn('rp.ordencompra_id', 'ordencompra.id')
                ->where('rp.tipo', Recepcion_Proveedor::TIPO_RECEPCION)
                ->where(function ($w) use ($like, $valor) {
                    $w->where('rp.numerorecepcion', 'like', $like);
                    if (ctype_digit($valor)) {
                        $w->orWhere('rp.id', (int) $valor)
                            ->orWhere('rp.anita_nro', (int) $valor);
                    }
                });
        });
    }

    /**
     * @param  Builder<\App\Models\Compras\Ordencompra>  $q
     */
    private function whereExistePagoNumero(Builder $q, string $valor): void
    {
        $like = '%'.addcslashes($valor, '%_\\').'%';
        $q->whereExists(function ($e) use ($like, $valor) {
            $e->selectRaw('1')
                ->from('proveedor_cuentacorriente as pcc')
                ->join('comprobante_proveedor as cp', 'cp.id', '=', 'pcc.comprobante_proveedor_id')
                ->join('pagoproveedor as pp', 'pp.id', '=', 'pcc.pagoproveedor_id')
                ->where('pcc.pagoproveedor_id', '>', 0)
                ->whereColumn('cp.ordencompra_id', 'ordencompra.id')
                ->where(function ($w) use ($like, $valor) {
                    $w->where('pp.numerotransaccion', 'like', $like);
                    if (ctype_digit($valor)) {
                        $w->orWhere('pp.id', (int) $valor)
                            ->orWhere('pp.numerotransaccion', (int) $valor);
                    }
                });
        });
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{fecha: Carbon|null}>
     */
    private function ultimaHistoriaPorOc(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Ordencompra_Historia::query()
            ->whereIn('ordencompra_id', $ids)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['ordencompra_id', 'fecha']);
        $out = [];
        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            if (isset($out[$ocId])) {
                continue;
            }
            $out[$ocId] = [
                'fecha' => $row->fecha ? Carbon::parse($row->fecha) : null,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Ordencompra>  $ocs
     * @return array<int, list<array{id: int, url_pdf: string, url_cargar_cxp: string}>>
     */
    private function facturasPorOc(Collection $ocs): array
    {
        $claves = [];
        foreach ($ocs as $oc) {
            $num = trim((string) $oc->numeroordencompra);
            $emp = (int) $oc->empresa_id;
            if ($num !== '' && $emp > 0) {
                $claves[$emp.'|'.$num] = (int) $oc->id;
            }
        }
        if ($claves === []) {
            return [];
        }
        $query = Precarga_Comprobante_Proveedor::query()
            ->whereNotNull('rutaalmacenamiento')
            ->where('rutaalmacenamiento', '!=', '')
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            })
            ->where(function ($q) use ($claves) {
                foreach (array_keys($claves) as $clave) {
                    [$emp, $num] = explode('|', $clave, 2);
                    $q->orWhere(function ($w) use ($emp, $num) {
                        $w->where('empresa_id', (int) $emp)->where('numeroordencompra', $num);
                    });
                }
            })
            ->orderByDesc('id');
        $out = [];
        foreach ($query->with('tipotransaccion_compras:id,abreviatura,codigoafip,signo')->get([
            'id', 'empresa_id', 'numeroordencompra', 'letra', 'sucursal', 'numerocomprobante',
            'tipotransaccion_compra_id', 'origen_entrada', 'estado',
        ]) as $pre) {
            $clave = ((int) $pre->empresa_id).'|'.trim((string) $pre->numeroordencompra);
            if (! isset($claves[$clave])) {
                continue;
            }
            $ocId = $claves[$clave];
            $preId = (int) $pre->id;
            $numero = $this->numeroFacturaPrecarga($pre);
            $tipo = OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($pre);
            $estadoPrecarga = (string) ($pre->estado ?? '');
            $cargadaAnita = PrecargaComprobanteEstados::esCargadaAnita($estadoPrecarga);
            $out[$ocId][] = [
                'id' => $preId,
                'origen' => 'precarga',
                'tipo' => $tipo,
                'tipo_label' => OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo),
                'exige_com' => OrdencompraLegajoDocumentoTipoSupport::exigeCom($tipo),
                'numero' => OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $numero),
                'origen_label' => PrecargaComprobanteOrigenEntrada::etiqueta($pre->origen_entrada ?? null),
                'etiqueta' => OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $numero),
                'estado_precarga' => $estadoPrecarga,
                'cargada_anita' => $cargadaAnita,
                'url_pdf' => route('ordencompra_legajo_bandeja_factura_pdf', [
                    'id' => $ocId,
                    'precarga' => $preId,
                    'inline' => 1,
                ]),
                'url_cargar_cxp' => $cargadaAnita ? null : route('crear_comprobante_proveedor', [
                    'origen' => ComprobanteProveedorRetornoLegajoSupport::ORIGEN_BANDEJA,
                    'ordencompra_id' => $ocId,
                    'precarga_id' => $preId,
                ]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, list<array{id: int, url_pdf: string}>>
     */
    private function comsPorOc(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $rows = Recepcion_Proveedor::query()
            ->whereIn('ordencompra_id', $ids)
            ->where('tipo', Recepcion_Proveedor::TIPO_RECEPCION)
            ->where('estado', Recepcion_Proveedor::ESTADO_CONFIRMADA)
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->get(['id', 'ordencompra_id']);
        $out = [];
        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            $recId = (int) $row->id;
            $out[$ocId][] = [
                'id' => $recId,
                'url_pdf' => route('ordencompra_legajo_bandeja_com_pdf', [
                    'id' => $ocId,
                    'recepcion' => $recId,
                    'inline' => 1,
                ]),
            ];
        }

        return $out;
    }

    /**
     * @param  list<int>  $precargaIds
     * @return array<int, list<int>>
     */
    /**
     * @param  list<int>  $ids
     * @param  list<int>  $precargaIds
     * @param  array<int, list<array{id: int}>>  $facturas
     * @return array<int, list<array{id: int, url: string}>>
     */
    private function comprobantesPorOc(array $ids, array $precargaIds, array $facturas): array
    {
        if ($ids === [] && $precargaIds === []) {
            return [];
        }
        $preAOc = [];
        foreach ($facturas as $ocId => $lista) {
            foreach ($lista as $fac) {
                $preAOc[(int) $fac['id']] = (int) $ocId;
            }
        }
        $query = Comprobante_Proveedor::query()
            ->where(function ($q) {
                $q->whereNull('estado')
                    ->orWhereRaw('UPPER(TRIM(estado)) != ?', ['ANULADA']);
            });
        $query->where(function ($q) use ($ids, $precargaIds) {
            if ($ids !== []) {
                $q->whereIn('ordencompra_id', $ids);
            }
            if ($precargaIds !== []) {
                $q->orWhereIn('precarga_comprobante_proveedor_id', $precargaIds);
            }
        });
        $out = [];
        foreach ($query->orderByDesc('id')->with('tipotransaccion_compras:id,abreviatura,codigoafip')->get([
            'id', 'ordencompra_id', 'precarga_comprobante_proveedor_id',
            'letra', 'sucursal', 'numerocomprobante', 'origen_entrada', 'tipotransaccion_compra_id',
        ]) as $cp) {
            $ocId = (int) ($cp->ordencompra_id ?? 0);
            if ($ocId <= 0) {
                $ocId = $preAOc[(int) $cp->precarga_comprobante_proveedor_id] ?? 0;
            }
            if ($ocId <= 0) {
                continue;
            }
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
            $numero = OrdencompraLegajoDocumentoTipoSupport::numeroConTipo($tipo, $numero);
            $out[$ocId][] = [
                'id' => (int) $cp->id,
                'precarga_id' => (int) ($cp->precarga_comprobante_proveedor_id ?? 0) ?: null,
                'tipo' => $tipo,
                'numero' => $numero,
                'origen_label' => ComprobanteProveedorOrigenEntrada::etiqueta(
                    (string) ($cp->origen_entrada ?? ComprobanteProveedorOrigenEntrada::PRECARGA)
                ),
                'etiqueta' => $numero,
                'url' => route('editar_comprobante_proveedor', ['id' => (int) $cp->id]),
            ];
        }

        return $out;
    }

    /**
     * Facturas visibles en la grilla: número + origen + tipo + estado de carga.
     *
     * @param  list<array<string, mixed>>  $comprobantes
     * @param  list<array<string, mixed>>  $facturas
     * @return list<array{numero: string, origen: string, tipo: string, tipo_label: string, estado: string, capa: string}>
     */
    private function resumenFacturasLegajo(array $comprobantes, array $facturas): array
    {
        $out = [];
        $vistos = [];
        foreach ($comprobantes as $cp) {
            $numero = trim((string) ($cp['numero'] ?? $cp['etiqueta'] ?? ''));
            $clave = $this->claveFacturaEtiqueta($numero);
            if ($numero === '' || ($clave !== '' && isset($vistos[$clave]))) {
                continue;
            }
            if ($clave !== '') {
                $vistos[$clave] = true;
            }
            $tipo = (string) ($cp['tipo'] ?? 'FC');
            $out[] = [
                'numero' => $numero,
                'origen' => (string) ($cp['origen_label'] ?? 'Comprobante cargado en CxP'),
                'tipo' => $tipo,
                'tipo_label' => OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo),
                'estado' => 'cargada',
                'capa' => 'comprobante',
            ];
        }
        $preConCp = [];
        foreach ($comprobantes as $cp) {
            $preId = (int) ($cp['precarga_id'] ?? 0);
            if ($preId > 0) {
                $preConCp[$preId] = true;
            }
        }
        foreach ($facturas as $fac) {
            $capaOrigen = (string) ($fac['origen'] ?? 'precarga');
            if ($capaOrigen === 'precarga' && isset($preConCp[(int) ($fac['id'] ?? 0)])) {
                continue;
            }
            $numero = trim((string) ($fac['numero'] ?? $fac['etiqueta'] ?? ''));
            $clave = $this->claveFacturaEtiqueta($numero);
            if ($numero === '' || ($clave !== '' && isset($vistos[$clave]))) {
                continue;
            }
            if ($clave !== '') {
                $vistos[$clave] = true;
            }
            $origen = trim((string) ($fac['origen_label'] ?? ''));
            if ($origen === '') {
                $origen = $capaOrigen === 'anita'
                    ? PrecargaComprobanteOrigenEntrada::etiqueta(PrecargaComprobanteOrigenEntrada::SCAN_ANITA)
                    : 'Precarga';
            }
            $tipo = (string) ($fac['tipo'] ?? 'FC');
            $cargadaAnita = ! empty($fac['cargada_anita'])
                || PrecargaComprobanteEstados::esCargadaAnita($fac['estado_precarga'] ?? null);
            $out[] = [
                'numero' => $numero,
                'origen' => $origen,
                'tipo' => $tipo,
                'tipo_label' => OrdencompraLegajoDocumentoTipoSupport::etiquetaCorta($tipo),
                'estado' => $cargadaAnita ? 'en_anita' : 'pendiente',
                'capa' => $capaOrigen === 'anita' ? 'anita' : 'precarga',
            ];
        }

        usort($out, static function (array $a, array $b): int {
            $rank = static function (string $estado): int {
                return match ($estado) {
                    'pendiente' => 0,
                    'en_anita' => 1,
                    default => 2, // cargada
                };
            };
            $ea = $rank((string) ($a['estado'] ?? ''));
            $eb = $rank((string) ($b['estado'] ?? ''));

            return $ea <=> $eb;
        });

        return $out;
    }

    /**
     * Evita listar el mismo comprobante dos veces (precarga materializada + scan Anita crudo).
     *
     * @param  list<array<string, mixed>>  $precargas
     * @param  list<array<string, mixed>>  $scansAnita
     * @return list<array<string, mixed>>
     */
    private function fusionarScansAnitaSinDuplicar(array $precargas, array $scansAnita): array
    {
        $claves = [];
        foreach ($precargas as $fac) {
            $clave = $this->claveFacturaEtiqueta((string) ($fac['numero'] ?? $fac['etiqueta'] ?? ''));
            if ($clave !== '') {
                $claves[$clave] = true;
            }
        }
        foreach ($scansAnita as $scan) {
            $clave = $this->claveFacturaEtiqueta((string) ($scan['numero'] ?? $scan['etiqueta'] ?? ''));
            if ($clave !== '' && isset($claves[$clave])) {
                continue;
            }
            if ($clave !== '') {
                $claves[$clave] = true;
            }
            $precargas[] = $scan;
        }

        return $precargas;
    }

    /** Clave letra|sucursal|número para deduplicar etiquetas (ignora prefijo FGA, origen Anita, etc.). */
    private function claveFacturaEtiqueta(string $etiqueta): string
    {
        $etiqueta = strtoupper(trim($etiqueta));
        if (preg_match('/([A-Z])\s+(\d{1,5})-(\d{1,8})/', $etiqueta, $m)) {
            return $m[1].'|'.((int) $m[2]).'|'.((int) $m[3]);
        }

        return $etiqueta;
    }

    /**
     * @param  \App\Models\Compras\Precarga_Comprobante_Proveedor  $pre
     */
    private function numeroFacturaPrecarga($pre): string
    {
        $abrev = strtoupper(trim((string) ($pre->tipotransaccion_compras->abreviatura ?? '')));
        $letra = trim((string) ($pre->letra ?? ''));
        $suc = (int) ($pre->sucursal ?? 0);
        $nro = (int) ($pre->numerocomprobante ?? 0);
        $numero = ($letra !== '' || $nro > 0)
            ? trim(sprintf('%s %04d-%08d', $letra !== '' ? $letra : 'FC', $suc, $nro))
            : 'Factura #'.$pre->id;

        return $abrev !== '' ? $abrev.' '.$numero : $numero;
    }

    /**
     * Consulta global de legajos (sin recorte por sector). Requiere criterio de documento.
     *
     * @param  array<string, mixed>  $filtros
     * @return Collection<int, array<string, mixed>>
     */
    public function buscarSeguimiento(array $filtros, int $limite = 50): Collection
    {
        if (! $this->tieneCriterioSeguimiento($filtros)) {
            return collect();
        }

        $query = Ordencompra::query()
            ->select([
                'ordencompra.id',
                'ordencompra.numeroordencompra',
                'ordencompra.fecha',
                'ordencompra.empresa_id',
                'ordencompra.proveedor_id',
                'ordencompra.centrocosto_id',
                'ordencompra.sector_legajocompra_id',
                'ordencompra.estadoordencompra',
                'ordencompra.tratamiento',
                'ordencompra.nota_legajo',
                'ordencompra.es_contrato',
                'ordencompra.contrato_requiere_recepcion',
                'ordencompra.contrato_vigencia_desde',
                'ordencompra.contrato_vigencia_hasta',
                'ordencompra.created_at',
            ])
            ->with([
                'empresas:id,codigo,nombre',
                'proveedores:id,codigo,nombre',
                'centrocostos:id,codigo,nombre',
                'sector_legajocompras:id,nombre',
            ]);

        app(EmpresaRepository::class)->aplicarFiltroEmpresasAsignadas($query, 'ordencompra.empresa_id');
        $this->aplicarFiltrosBusqueda($query, $filtros);
        $this->aplicarFiltrosDocumento($query, $filtros);
        $query->orderByDesc('ordencompra.id')->limit($limite);

        return $this->hidratar($query->get());
    }

    /**
     * @param  array<string, mixed>  $filtros
     */
    public function tieneCriterioSeguimiento(array $filtros): bool
    {
        foreach (['nro_oc', 'nro_factura', 'nro_com', 'nro_op', 'valor'] as $k) {
            if (trim((string) ($filtros[$k] ?? '')) !== '') {
                return true;
            }
        }

        return OrdencompraListadoFiltros::tieneCriteriosTexto($filtros);
    }

    /**
     * @param  list<int>  $comprobanteIds
     * @return array<int, array{id: int, url: string, etiqueta: string}>
     */
    private function pagosPorComprobante(array $comprobanteIds): array
    {
        if ($comprobanteIds === []) {
            return [];
        }
        $rows = Proveedor_Cuentacorriente::query()
            ->with(['pagoproveedores:id,tipocomprobante,letra,sucursal,numerotransaccion'])
            ->whereIn('comprobante_proveedor_id', $comprobanteIds)
            ->where('pagoproveedor_id', '>', 0)
            ->orderByDesc('id')
            ->get(['comprobante_proveedor_id', 'pagoproveedor_id']);
        $out = [];
        foreach ($rows as $row) {
            $cpId = (int) $row->comprobante_proveedor_id;
            if (isset($out[$cpId])) {
                continue;
            }
            $pago = $row->pagoproveedores;
            $pagoId = (int) $row->pagoproveedor_id;
            $out[$cpId] = [
                'id' => $pagoId,
                'url' => route('editar_pagoproveedor', ['id' => $pagoId]),
                'etiqueta' => $pago ? $pago->etiquetaComprobante() : ('OP #'.$pagoId),
            ];
        }

        return $out;
    }

    private function asignacionesPorPrecarga(array $precargaIds): array
    {
        if ($precargaIds === [] || ! Schema::hasTable('precarga_comprobante_proveedor_recepcion')) {
            return [];
        }
        $out = [];
        $rows = Precarga_Comprobante_Proveedor_Recepcion::query()
            ->whereIn('precarga_comprobante_proveedor_id', $precargaIds)
            ->get(['precarga_comprobante_proveedor_id', 'recepcion_proveedor_id']);
        foreach ($rows as $row) {
            $preId = (int) $row->precarga_comprobante_proveedor_id;
            $out[$preId][] = (int) $row->recepcion_proveedor_id;
        }

        return $out;
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, array{estado: string, usuario: string, fecha: string, comentario: string}>
     */
    private function ultimaDecisionArbol(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $nombreA = 'Aprobado';
        $nombreR = 'Rechazado';
        $rows = Arbolaprobacion_Movimiento::query()
            ->with('destinatariousuarios:id,nombre')
            ->whereIn('ordencompra_id', $ids)
            ->where('circuito_oc', OrdencompraLegajoGastronomiaSupport::CIRCUITO_SECTOR)
            ->whereIn('estado', [$nombreA, $nombreR])
            ->orderByDesc('fechaproceso')
            ->orderByDesc('id')
            ->get();
        $out = [];
        foreach ($rows as $row) {
            $ocId = (int) $row->ordencompra_id;
            if (isset($out[$ocId])) {
                continue;
            }
            $out[$ocId] = [
                'estado' => (string) $row->estado,
                'usuario' => (string) ($row->destinatariousuarios->nombre ?? ''),
                'fecha' => $row->fechaproceso ? Carbon::parse($row->fechaproceso)->format('d/m/Y H:i') : '',
                'comentario' => (string) ($row->observacion ?? ''),
            ];
        }

        return $out;
    }
}
