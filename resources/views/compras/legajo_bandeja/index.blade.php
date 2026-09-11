@extends("theme.$theme.layout")
@section('titulo')
Bandeja de legajos
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css') }}?v={{ @filemtime(public_path('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css')) ?: time() }}">
@include('includes.tabs-activas-estilos')
<style>
    .bandeja-col-facturas {
        max-width: 10.5rem;
        min-width: 7rem;
        white-space: normal;
        word-wrap: break-word;
        overflow-wrap: anywhere;
        line-height: 1.3;
        vertical-align: top;
    }
    .bandeja-fac-item + .bandeja-fac-item {
        margin-top: 0.35rem;
        padding-top: 0.35rem;
        border-top: 1px dashed #dee2e6;
    }
    .bandeja-fac-origen {
        display: block;
        font-size: 0.78em;
        color: #6c757d;
        line-height: 1.2;
    }
    .bandeja-fac-consultar {
        display: inline;
        padding: 0;
        font-size: 0.82em;
        line-height: 1.2;
        vertical-align: baseline;
    }
    .oc-empresas-export {
        white-space: nowrap;
    }
    #modalBandejaLegajo thead th {
        position: sticky;
        top: 0;
        background: #85C1E9;
        color: #17202A;
        z-index: 1;
    }
    #modalBandejaLegajo td {
        font-size: 0.82rem;
        padding: 0.28rem 0.4rem;
        vertical-align: middle;
    }
</style>
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/enviar_gastronomia_firmante.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/legajo_bandeja/bandeja.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/legajo_bandeja/bandeja.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
@include('compras.ordencompra.partials.modal_asignar_factura_legajo')
@include('compras.ordencompra.partials.modal_firmante_gastronomia_arbol')
@php
    use App\Support\Compras\OrdencompraLegajoBandejaFiltros;
    use App\Support\Compras\OrdencompraListadoFiltros;
    $vista = $filtros['vista'] ?? OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES;
    $tab = $filtros['tab'] ?? OrdencompraLegajoBandejaFiltros::TAB_TODOS;
    $atajo = $filtros['atajo'] ?? '';
    $limpiarUrl = route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryStringEmpresaYVista($filtros));
    $qs = function (array $extra = []) use ($filtros) {
        return route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString(array_merge($filtros, $extra)));
    };
    $qsAtajo = function (string $nuevo) use ($filtros, $atajo) {
        $extra = [
            'atajo' => $atajo === $nuevo ? '' : $nuevo,
        ];
        if ($atajo !== $nuevo && $nuevo === OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR) {
            $extra['vista'] = OrdencompraLegajoBandejaFiltros::VISTA_CXP;
        }

        return route('consultar_legajo_compra', OrdencompraLegajoBandejaFiltros::paraQueryString(array_merge($filtros, $extra)));
    };
    $vistasTodas = [
        OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES => 'Pendientes',
        OrdencompraLegajoBandejaFiltros::VISTA_ESTADOS => 'En circuito',
        OrdencompraLegajoBandejaFiltros::VISTA_CXP => 'Cuentas a pagar',
        OrdencompraLegajoBandejaFiltros::VISTA_PAGOS => 'Pagos',
        OrdencompraLegajoBandejaFiltros::VISTA_ARCHIVADOS => 'Archivados',
        OrdencompraLegajoBandejaFiltros::VISTA_HISTORICO => 'Histórico',
    ];
    $vistasPermitidas = $vistasPermitidas ?? null;
    $vistas = $vistasTodas;
    if (is_array($vistasPermitidas) && $vistasPermitidas !== []) {
        $vistas = array_intersect_key($vistasTodas, array_flip($vistasPermitidas));
        if ($vistas === []) {
            $vistas = $vistasTodas;
        }
    }
    $atajos = [
        OrdencompraLegajoBandejaFiltros::ATAJO_SIN_FACTURA => 'Sin factura',
        OrdencompraLegajoBandejaFiltros::ATAJO_SIN_COM => 'Sin COM',
        OrdencompraLegajoBandejaFiltros::ATAJO_COM_SIN_ASIGNAR => 'COM sin asignar',
        OrdencompraLegajoBandejaFiltros::ATAJO_LISTO_CARGAR => 'Listo para cargar',
        OrdencompraLegajoBandejaFiltros::ATAJO_FC_CARGADA => 'FC cargada',
        OrdencompraLegajoBandejaFiltros::ATAJO_CON_PAGO => 'Con orden de pago',
    ];
@endphp

@if (!empty($puede_actualizar))
<div class="modal fade" id="modalBandejaEnviarGastro" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaEnviarGastro" action="" enctype="multipart/form-data"
                  data-ordencompra-id=""
                  data-sector-gastronomia-id="{{ (int) \App\Support\Compras\OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId() }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a Gastronomía</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">El referente verá la factura, la OC y la recepción. Al autorizar, el legajo pasa a Cuentas a pagar.</p>
                    @include('compras.ordencompra.partials.bloque_factura_legajo_sector', [
                        'prefix' => 'bandeja_ocg',
                        'tituloBloque' => 'Factura y recepción del legajo',
                    ])
                    <div class="form-group">
                        <label for="bandeja_ocg_obs">Comentario al referente</label>
                        <input type="text" name="observacion" id="bandeja_ocg_obs" class="form-control" maxlength="255" placeholder="Opcional">
                    </div>
                    <div class="form-group">
                        <label for="bandeja_ocg_leyenda">Leyenda / detalle</label>
                        <textarea name="leyenda" id="bandeja_ocg_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar a Gastronomía</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaEnviarCxp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaEnviarCxp" action="" enctype="multipart/form-data" data-ordencompra-id="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a Cuentas a pagar</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Pasa el legajo a Cuentas a pagar. Exige factura. La COM es obligatoria según la empresa o el contrato.</p>
                    @include('compras.ordencompra.partials.bloque_factura_legajo_sector', [
                        'prefix' => 'bandeja_ocx',
                        'tituloBloque' => 'Factura y recepción del legajo',
                    ])
                    <div class="form-group">
                        <label for="bandeja_ocx_obs">Observación</label>
                        <input type="text" name="observacion" id="bandeja_ocx_obs" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="bandeja_ocx_leyenda">Leyenda / detalle</label>
                        <textarea name="leyenda" id="bandeja_ocx_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar a Cuentas a pagar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@if (!empty($puede_enviar_pagos))
<div class="modal fade" id="modalBandejaEnviarPagos" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaEnviarPagos" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Enviar a Pagos</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Pasa el legajo de Cuentas a pagar a <strong>PAGOS</strong>. Requiere la factura ya cargada.</p>
                    <div class="form-group">
                        <label for="bandeja_ocp_obs">Observación</label>
                        <input type="text" name="observacion" id="bandeja_ocp_obs" class="form-control" maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="bandeja_ocp_leyenda">Leyenda / detalle</label>
                        <textarea name="leyenda" id="bandeja_ocp_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Enviar a Pagos</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@if (!empty($puede_devolver_cxp))
<div class="modal fade" id="modalBandejaDevolverCxp" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaDevolverCxp" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Devolver a Cuentas a pagar</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Vuelve el legajo de Pagos a <strong>CUENTAS A PAGAR</strong>. El comentario es obligatorio.</p>
                    <div class="form-group">
                        <label for="bandeja_dev_cxp_obs">Comentario / motivo</label>
                        <input type="text" name="observacion" id="bandeja_dev_cxp_obs" class="form-control" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="bandeja_dev_cxp_leyenda">Detalle</label>
                        <textarea name="leyenda" id="bandeja_dev_cxp_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Devolver a Cuentas a pagar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@if (!empty($puede_devolver_compras))
<div class="modal fade" id="modalBandejaDevolverCompras" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaDevolverCompras" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Devolver a Compras</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small">Vuelve el legajo de Cuentas a pagar a <strong>COMPRAS</strong>. El comentario es obligatorio.</p>
                    <div class="form-group">
                        <label for="bandeja_dev_com_obs">Comentario / motivo</label>
                        <input type="text" name="observacion" id="bandeja_dev_com_obs" class="form-control" maxlength="255" required>
                    </div>
                    <div class="form-group">
                        <label for="bandeja_dev_com_leyenda">Detalle</label>
                        <textarea name="leyenda" id="bandeja_dev_com_leyenda" class="form-control" rows="3" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Devolver a Compras</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="modal fade" id="modalBandejaHistoria" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historia del legajo</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body p-0">
                <table class="table table-sm table-striped mb-0" id="tablaBandejaHistoria">
                    <thead><tr><th>Fecha</th><th>Sector</th><th>Observación</th><th>Leyenda</th><th>Usuario</th></tr></thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaNota" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaNota" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Nota del legajo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <label for="bandeja_nota_texto" class="small font-weight-bold">Nota interna</label>
                    <textarea name="nota_legajo" id="bandeja_nota_texto" class="form-control" rows="5" maxlength="4000"
                              placeholder="Escriba una nota visible para quienes trabajan el legajo…"></textarea>
                    <p class="text-muted small mb-0 mt-2">Deje vacío y guarde para quitar la nota. El ícono queda destacado mientras haya texto.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-warning">Guardar nota</button>
                </div>
            </form>
        </div>
    </div>
</div>
<div class="modal fade" id="modalBandejaLegajo" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered" role="document" style="max-width: 96vw;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bandejaLegajoTitulo">Legajo</h5>
                <a id="bandejaLegajoOc" href="#" class="btn btn-sm btn-outline-info ml-2" target="_blank" rel="noopener" style="display:none;">
                    <i class="fa fa-file-text-o"></i> Abrir OC
                </a>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            </div>
            <div class="modal-body">
                <div class="tabs-activas mb-2">
                    <ul class="nav nav-tabs" id="tabs-bandeja-legajo" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" id="bandeja-tab-facturas" data-toggle="tab" href="#tab-bandeja-facturas" role="tab">
                                <i class="fa fa-file-pdf-o"></i> Facturas
                                <span class="badge badge-secondary" id="bandejaLegajoNFac">0</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" id="bandeja-tab-coms" data-toggle="tab" href="#tab-bandeja-coms" role="tab">
                                <i class="fa fa-cubes"></i> COM
                                <span class="badge badge-secondary" id="bandejaLegajoNCom">0</span>
                            </a>
                        </li>
                        <li class="nav-item" id="bandeja-tab-pagos-item" style="display:none;">
                            <a class="nav-link" id="bandeja-tab-pagos" data-toggle="tab" href="#tab-bandeja-pagos" role="tab">
                                <i class="fa fa-money"></i> Pagos
                                <span class="badge badge-secondary" id="bandejaLegajoNPago">0</span>
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-bandeja-facturas" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4 mb-2">
                                <div class="table-responsive" style="max-height: 70vh; overflow:auto;">
                                    <table class="table table-sm table-striped table-hover mb-0" id="tablaBandejaFacturas">
                                        <thead><tr><th>Comprobante</th><th>Fecha</th><th>Origen</th><th>Estado</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <iframe id="bandejaFacturaPdf" title="Factura" style="width:100%; height:70vh; border:1px solid #dee2e6; background:#f8f9fa;"></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-bandeja-coms" role="tabpanel">
                        <div class="row">
                            <div class="col-lg-4 mb-2">
                                <div class="table-responsive" style="max-height: 70vh; overflow:auto;">
                                    <table class="table table-sm table-striped table-hover mb-0" id="tablaBandejaComs">
                                        <thead><tr><th>Documento</th><th>Fecha</th><th>Estado</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-8">
                                <iframe id="bandejaComPdf" title="COM" style="width:100%; height:70vh; border:1px solid #dee2e6; background:#f8f9fa;"></iframe>
                            </div>
                        </div>
                    </div>
                    <div class="tab-pane fade" id="tab-bandeja-pagos" role="tabpanel">
                        <div id="bandejaLegajoPagos" class="p-2"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@if (!empty($puede_asignar_com))
<div class="modal fade" id="modalBandejaAsignarCom" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <form method="POST" id="formBandejaAsignarCom" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Asignar COM a los comprobantes</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Este envío incluye solo los comprobantes pendientes (aún no cargados en CxP).
                        En una OC anual las FC/NC ya contabilizadas quedan aparte y no se vuelven a mandar.
                        Seleccioná cada pendiente y asignale COM si corresponde.
                        Si el tipo está mal (p.ej. ND marcada como FIS), corregilo con el selector a la derecha.
                        Las notas de crédito y de débito no exigen recepción.
                    </p>
                    <div class="row">
                        <div class="col-md-5 mb-3">
                            <label class="small font-weight-bold">Comprobantes del legajo</label>
                            <div id="bandejaAsignarPrecarga" class="list-group list-group-flush border rounded" style="max-height: 50vh; overflow:auto;"></div>
                        </div>
                        <div class="col-md-7 mb-3">
                            <label class="small font-weight-bold">COM para el comprobante seleccionado</label>
                            <div id="bandejaAsignarComs" class="border rounded p-2" style="min-height: 8rem; max-height: 50vh; overflow:auto;"></div>
                        </div>
                    </div>
                    <div id="bandejaAsignarAtajos" class="mt-2"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar asignaciones</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Bandeja de legajos</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.ayuda.boton-guia', [
                        'slug' => 'bandeja-legajos',
                        'titulo' => 'Guía: bandeja de legajos (COM y envío)',
                        'clase' => 'btn btn-outline-light btn-sm mr-1',
                    ])
                    @if (can('listar-seguimiento-legajo-compra', false))
                    <a href="{{ route('consultar_seguimiento_legajo_compra') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-search"></i> Seguimiento
                    </a>
                    @endif
                    <a href="{{ route('consultar_ordencompra') }}" class="btn btn-outline-light btn-sm mr-1">
                        <i class="fa fa-file-text-o"></i> Órdenes de compra
                    </a>
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-legajo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => OrdencompraListadoFiltros::tieneCriteriosTexto($filtros ?? [])
                            || !empty($filtros['nro_oc'])
                            || !empty($filtros['nro_factura'])
                            || !empty($filtros['nro_com'])
                            || !empty($filtros['nro_op'])
                            || !empty($atajo),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Nº OC, proveedor, factura, COM u OP…',
                        'toggleTarget' => '#panel-filtros-ordencompra',
                        'toggleId' => 'btn-toggle-filtros-ordencompra',
                        'inputId' => 'filtro_valor',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('consultar_legajo_compra') }}" id="form-filtros-legajo" class="mb-0">
                <input type="hidden" name="vista" value="{{ $vista }}">
                <input type="hidden" name="tab" value="{{ $tab }}">
                @if ($atajo !== '')
                    <input type="hidden" name="atajo" value="{{ $atajo }}">
                @endif
                @include('compras.ordencompra.partials.filtros_listado', ['limpiarUrl' => $limpiarUrl])
                <div class="card-body py-2 border-bottom bg-light">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_oc">Nº OC</label>
                            <input type="text" name="nro_oc" id="nro_oc" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_oc'] ?? '' }}" placeholder="Orden de compra" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_factura">Nº factura</label>
                            <input type="text" name="nro_factura" id="nro_factura" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_factura'] ?? '' }}" placeholder="Número o dígitos" autocomplete="off">
                        </div>
                        <div class="form-group col-md-2 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_com">Nº COM</label>
                            <input type="text" name="nro_com" id="nro_com" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_com'] ?? '' }}" placeholder="Número o ID" autocomplete="off">
                        </div>
                        <div class="form-group col-md-3 col-sm-6 mb-2">
                            <label class="small mb-1" for="nro_op">Nº orden de pago</label>
                            <input type="text" name="nro_op" id="nro_op" class="form-control form-control-sm"
                                   value="{{ $filtros['nro_op'] ?? '' }}" placeholder="Cuando el legajo está pago" autocomplete="off">
                        </div>
                        <div class="form-group col-md-3 col-sm-6 mb-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Buscar
                            </button>
                            <a href="{{ $limpiarUrl }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
                        </div>
                    </div>
                </div>
            </form>
            @include('compras.ordencompra.partials.filtros_externos', [
                'rutaIndex' => 'consultar_legajo_compra',
                'filtros' => $filtros,
                'filtrosQuery' => $filtrosQuery ?? [],
                'empresa_query' => $empresa_query ?? collect(),
                'exportRuta' => 'listar_legajo_compra',
                'exportQueryparams' => $filtrosQuery ?? [],
            ])
            <div class="card-body py-2">
                <p class="text-muted small mb-2">
                    El legajo es la OC (sector, historia, factura y COM).
                    Compras envía; Cuentas a pagar carga la factura y envía a Pagos; Pagos ve solo lo que está en Pagos y archiva.
                    <strong>Listo para cargar</strong> abre Cuentas a pagar y muestra las OC con al menos una factura aún pendiente (aunque otras de la misma OC ya estén cargadas).
                    La columna Facturas muestra solo lo pendiente; las ya ingresadas en CxP se consultan con
                    <strong>N ya en CxP</strong> o el ícono PDF (abre el legajo: facturas, COM y OC).
                </p>
                @if (!empty($alcanceSector))
                    <p class="text-muted small mb-2">{{ $alcanceSector }}</p>
                @endif
                <div class="d-flex flex-wrap align-items-center">
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="text-muted small mr-2 mb-0"><i class="fa fa-filter"></i> Estado:</span>
                        <div class="btn-group btn-group-sm flex-wrap" role="group" aria-label="Estado del legajo">
                            @foreach ($vistas as $key => $label)
                                <a href="{{ $qs(['vista' => $key]) }}"
                                   class="btn {{ $vista === $key ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
                            @endforeach
                        </div>
                    </div>
                    <div class="d-none d-md-block mx-3 mb-2 align-self-center" style="width:1px;height:28px;background:#dee2e6;"></div>
                    <div class="d-flex flex-wrap align-items-center mb-2">
                        <span class="text-muted small mr-2 mb-0"><i class="fa fa-cutlery"></i> Ámbito:</span>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Ámbito gastronomía">
                            <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_TODOS]) }}"
                               class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_TODOS ? 'btn-info' : 'btn-outline-info' }}">Todas</a>
                            <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA]) }}"
                               class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_GASTRONOMIA ? 'btn-info' : 'btn-outline-info' }}">Gastronomía</a>
                            <a href="{{ $qs(['tab' => OrdencompraLegajoBandejaFiltros::TAB_RESTO]) }}"
                               class="btn {{ $tab === OrdencompraLegajoBandejaFiltros::TAB_RESTO ? 'btn-info' : 'btn-outline-info' }}">Resto</a>
                        </div>
                    </div>
                </div>
                <div class="d-flex flex-wrap align-items-center">
                    <span class="text-muted small mr-2 mb-1">Atajos:</span>
                    <div class="btn-group btn-group-sm flex-wrap mb-1" role="group" aria-label="Atajos de documentos">
                        @foreach ($atajos as $key => $label)
                            <a href="{{ $qsAtajo($key) }}"
                               class="btn {{ $atajo === $key ? 'btn-secondary' : 'btn-outline-secondary' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                <table class="table table-striped table-bordered table-hover">
                    <thead>
                        <tr>
                            <th>Id</th>
                            <th>OC</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Proveedor</th>
                            <th>Centro de costo</th>
                            <th>Sector</th>
                            <th>Días</th>
                            <th>Paquete</th>
                            <th class="bandeja-col-facturas">Facturas</th>
                            @if ($vista !== OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES)
                                <th>Decisión</th>
                            @endif
                            <th>Herramientas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($filas as $row)
                            @php
                                $nCargadasFac = (int) ($row['facturas_cargadas_count'] ?? 0);
                                $hayFacPendiente = ! empty($row['facturas_legajo']);
                                $puedeConsultarFacturas = ! empty($row['url_paquete'])
                                    && ($hayFacPendiente || $nCargadasFac > 0 || ! empty($row['url_factura']));
                            @endphp
                            <tr>
                                <td>{{ $row['id'] }}</td>
                                <td>
                                    <a href="{{ $row['url_oc'] }}" target="_blank" rel="noopener">{{ $row['numero'] }}</a>
                                    @if (!empty($row['es_anticipada']))
                                        <span class="badge badge-warning" title="Legajo anticipado (factura antes de recepción)">
                                            <i class="fa fa-clock-o"></i> Anticipado
                                        </span>
                                    @endif
                                    @if (!empty($row['es_gastronomia']))
                                        <span class="badge badge-info">Gastro</span>
                                    @endif
                                </td>
                                <td>{{ $row['fecha'] }}</td>
                                <td><small>{{ $row['empresa'] }}</small></td>
                                <td><small>{{ $row['proveedor'] }}</small></td>
                                <td><small>{{ $row['centrocosto'] }}</small></td>
                                <td><small>{{ $row['sector'] }}</small></td>
                                <td>
                                    @if ((int) $row['dias'] >= (int) ($dias_recordatorio ?? 3))
                                        <span class="badge badge-warning" title="{{ $row['fecha_ubicacion'] }}">{{ $row['dias'] }}</span>
                                    @else
                                        <span title="{{ $row['fecha_ubicacion'] }}">{{ $row['dias'] }}</span>
                                    @endif
                                </td>
                                <td>
                                    @if (!empty($row['paquete_ok']))
                                        <span class="badge badge-success">{{ !empty($row['exige_com']) || !array_key_exists('exige_com', $row) ? 'FC + COM' : 'FC (contrato sin COM)' }}</span>
                                    @else
                                        @if (!empty($row['tiene_factura']))
                                            <span class="badge badge-secondary">FC</span>
                                        @endif
                                        @if (!empty($row['tiene_com']))
                                            <span class="badge badge-secondary">COM</span>
                                        @endif
                                        @if (empty($row['tiene_factura']) && empty($row['tiene_com']))
                                            <span class="text-muted">—</span>
                                        @endif
                                    @endif
                                    @if (!empty($row['tiene_com_asignada']))
                                        <span class="badge badge-primary" title="COM asignada a la factura">asignada</span>
                                    @endif
                                    @if (!empty($row['tiene_comprobante']))
                                        <span class="badge badge-info" title="Todas las facturas y NC del legajo están en CxP">cargada</span>
                                    @elseif (!empty($row['tiene_comprobante_parcial']))
                                        <span class="badge badge-warning" title="Hay comprobantes en CxP, pero quedan documentos pendientes">parcial</span>
                                    @endif
                                    @if (!empty($row['tiene_pago']))
                                        <span class="badge badge-success" title="Orden de pago">{{ !empty($row['etiqueta_pago']) ? $row['etiqueta_pago'] : 'OP' }}</span>
                                    @endif
                                </td>
                                <td class="small bandeja-col-facturas">
                                    @if ($hayFacPendiente)
                                        @foreach ($row['facturas_legajo'] as $facLeg)
                                            <div class="bandeja-fac-item">
                                                <span>{{ $facLeg['numero'] ?? '' }}</span>
                                                @if (!empty($facLeg['estado']))
                                                    @php
                                                        $estadoFac = (string) ($facLeg['estado'] ?? '');
                                                        $badgeFac = $estadoFac === 'en_anita' ? 'badge-success' : 'badge-warning';
                                                        $textoFac = $estadoFac === 'en_anita' ? 'en Anita' : 'pendiente';
                                                    @endphp
                                                    <span class="badge {{ $badgeFac }}">
                                                        {{ $textoFac }}
                                                    </span>
                                                @endif
                                                @if (!empty($facLeg['origen']))
                                                    <span class="bandeja-fac-origen">{{ $facLeg['origen'] }}</span>
                                                @endif
                                            </div>
                                        @endforeach
                                    @endif
                                    @if ($nCargadasFac > 0)
                                        <div class="bandeja-fac-item">
                                            <button type="button"
                                                    class="btn btn-link bandeja-fac-consultar js-bandeja-ver-legajo"
                                                    data-url-paquete="{{ $row['url_paquete'] }}"
                                                    data-numero="{{ $row['numero'] }}"
                                                    data-tab="facturas"
                                                    title="Consultar el legajo (facturas, COM y OC)">
                                                {{ $nCargadasFac }} ya en CxP
                                            </button>
                                        </div>
                                    @elseif (! $hayFacPendiente)
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                @if ($vista !== OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES)
                                    <td>
                                        @if (($row['decision'] ?? '') === 'Aprobado')
                                            <span class="badge badge-success">Aprobado</span>
                                        @elseif (($row['decision'] ?? '') === 'Rechazado')
                                            <span class="badge badge-danger">Rechazado</span>
                                        @endif
                                        @if (!empty($row['firmante']))
                                            <div><small>{{ $row['firmante'] }}</small></div>
                                        @endif
                                        @if (!empty($row['fecha_decision']))
                                            <div><small>{{ $row['fecha_decision'] }}</small></div>
                                        @endif
                                        @if (!empty($row['comentario_decision']))
                                            <div><small class="text-muted">{{ $row['comentario_decision'] }}</small></div>
                                        @endif
                                    </td>
                                @endif
                                <td class="text-nowrap">
                                    <a href="{{ $row['url_oc'] }}" class="btn btn-xs btn-info" title="Ver orden de compra" target="_blank" rel="noopener">
                                        <i class="fa fa-file-text-o"></i>
                                    </a>
                                    @if (!empty($puede_actualizar) && !empty($row['url_asignar_factura']))
                                        <button type="button" class="btn btn-xs btn-outline-danger js-oc-asignar-factura"
                                                data-url="{{ $row['url_asignar_factura'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-proveedor="{{ $row['proveedor'] }}"
                                                title="Asignar PDF de factura al legajo">
                                            <i class="fa fa-cloud-upload"></i>
                                        </button>
                                    @endif
                                    @if ($puedeConsultarFacturas)
                                        <button type="button" class="btn btn-xs btn-outline-danger js-bandeja-ver-legajo"
                                                data-url-pdf="{{ $row['url_factura'] ?? '' }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-tab="facturas"
                                                title="Consultar facturas del legajo">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Sin PDF de factura">
                                            <i class="fa fa-file-pdf-o"></i>
                                        </button>
                                    @endif
                                    @if (!empty($row['tiene_com']))
                                        <button type="button" class="btn btn-xs btn-outline-dark js-bandeja-ver-legajo"
                                                data-url-pdf="{{ $row['url_com'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                data-tab="coms"
                                                title="Consultar COM del legajo">
                                            <i class="fa fa-cubes"></i>
                                        </button>
                                    @else
                                        <button type="button" class="btn btn-xs btn-outline-secondary" disabled title="Sin COM">
                                            <i class="fa fa-cubes"></i>
                                        </button>
                                    @endif
                                    <button type="button" class="btn btn-xs btn-outline-info js-bandeja-historia"
                                            data-url="{{ $row['url_historia'] }}"
                                            data-numero="{{ $row['numero'] }}"
                                            title="Historia de asignación del legajo">
                                        <i class="fa fa-history"></i>
                                    </button>
                                    <button type="button"
                                            class="btn btn-xs js-bandeja-nota {{ !empty($row['tiene_nota']) ? 'btn-warning' : 'btn-outline-secondary' }}"
                                            data-url="{{ $row['url_nota'] }}"
                                            data-numero="{{ $row['numero'] }}"
                                            data-nota="{{ $row['nota_legajo'] ?? '' }}"
                                            title="{{ !empty($row['tiene_nota']) ? ('Nota: '.$row['nota_legajo']) : 'Agregar nota al legajo' }}">
                                        <i class="fa fa-sticky-note{{ !empty($row['tiene_nota']) ? '' : '-o' }}"></i>
                                    </button>
                                    @if (!empty($puede_asignar_com) && !empty($row['tiene_factura']) && !empty($row['tiene_com']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-asignar-com"
                                                data-url-asignar="{{ $row['url_asignar_com'] }}"
                                                data-url-paquete="{{ $row['url_paquete'] }}"
                                                data-numero="{{ $row['numero'] }}"
                                                title="Asignar COM a la factura">
                                            <i class="fa fa-link"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_cargar_cxp) && !empty($row['url_cargar_cxp']))
                                        <a href="{{ $row['url_cargar_cxp'] }}" class="btn btn-xs btn-primary"
                                           title="Cargar {{ $row['siguiente_pendiente'] ?? 'comprobante' }} en Cuentas a pagar">
                                            <i class="fa fa-plus"></i>
                                        </a>
                                    @endif
                                    @if (!empty($puede_ver_comprobante) && !empty($row['url_comprobante']))
                                        <a href="{{ $row['url_comprobante'] }}" class="btn btn-xs btn-outline-info" title="Ver comprobante cargado" target="_blank" rel="noopener">
                                            <i class="fa fa-check-square-o"></i>
                                        </a>
                                    @endif
                                    @if (!empty($puede_ver_pago) && !empty($row['url_pago']))
                                        <a href="{{ $row['url_pago'] }}" class="btn btn-xs btn-outline-success" title="Orden de pago {{ $row['etiqueta_pago'] ?? '' }}" target="_blank" rel="noopener">
                                            <i class="fa fa-money"></i>
                                        </a>
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['puede_enviar']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-enviar-gastro"
                                                data-url="{{ $row['url_enviar'] }}"
                                                data-ordencompra-id="{{ $row['id'] }}"
                                                title="Enviar a Gastronomía">
                                            <i class="fa fa-cutlery"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_actualizar) && !empty($row['puede_enviar_cxp']))
                                        <button type="button" class="btn btn-xs btn-outline-primary js-bandeja-enviar-cxp"
                                                data-url="{{ $row['url_enviar_cxp'] }}"
                                                data-ordencompra-id="{{ $row['id'] }}"
                                                title="{{ ((int) ($row['pendientes_carga'] ?? 0) > 0 && (int) ($row['sector_id'] ?? 0) > 0) ? 'Enviar FC/NC pendientes a Cuentas a pagar' : 'Enviar a Cuentas a pagar' }}">
                                            <i class="fa fa-share"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_enviar_pagos) && !empty($row['puede_enviar_pagos']))
                                        <button type="button" class="btn btn-xs btn-outline-success js-bandeja-enviar-pagos"
                                                data-url="{{ $row['url_enviar_pagos'] }}"
                                                title="Enviar a Pagos">
                                            <i class="fa fa-share-square-o"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_devolver_cxp) && !empty($row['puede_devolver_cxp']))
                                        <button type="button" class="btn btn-xs btn-outline-warning js-bandeja-devolver-cxp"
                                                data-url="{{ $row['url_devolver_cxp'] }}"
                                                title="Devolver a Cuentas a pagar">
                                            <i class="fa fa-undo"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_devolver_compras) && !empty($row['puede_devolver_compras']))
                                        <button type="button" class="btn btn-xs btn-outline-warning js-bandeja-devolver-compras"
                                                data-url="{{ $row['url_devolver_compras'] }}"
                                                title="Devolver a Compras">
                                            <i class="fa fa-reply"></i>
                                        </button>
                                    @endif
                                    @if (!empty($puede_archivar) && !empty($row['puede_finalizar']))
                                        <form method="POST" action="{{ $row['url_finalizar'] }}" class="d-inline"
                                              onsubmit="return confirm('¿Archivar el legajo OC {{ $row['numero'] }}?');">
                                            @csrf
                                            <button type="submit" class="btn btn-xs btn-outline-success" title="Archivar legajo">
                                                <i class="fa fa-archive"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $vista !== OrdencompraLegajoBandejaFiltros::VISTA_PENDIENTES ? 12 : 11 }}" class="text-center text-muted">
                                    @if (!empty($sinSectorAsignado))
                                        No tiene sector de legajo asignado. No se muestran registros.
                                    @else
                                        No hay legajos para estos filtros.
                                    @endif
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($filas, 'links'))
                <div class="card-footer">
                    {{ $filas->links() }}
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
