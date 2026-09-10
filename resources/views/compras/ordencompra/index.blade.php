@extends("theme.$theme.layout")
@section('titulo')
Órdenes de compra
@endsection

@section('styles')
<link rel="stylesheet" href="{{ asset('assets/css/compras/ordencompra-ui.css') }}?v={{ @filemtime(public_path('assets/css/compras/ordencompra-ui.css')) ?: time() }}">
<link rel="stylesheet" href="{{ asset('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css') }}?v={{ @filemtime(public_path('assets/pages/css/compras/ordencompra/asignar_factura_legajo.css')) ?: time() }}">
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar-proveedor.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/cambiar_sector_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ordencompra/asignar_factura_legajo.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/erp-workspace-panel.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/erp-workspace-panel.js')) ?: time() }}" type="text/javascript"></script>
@if (session('sugerir_envio_oc'))
<script>
    window.ocSugerirEnvioProveedor = { ordencompra_id: {{ (int) session('sugerir_envio_oc') }} };
</script>
@endif
<script>
$(function () {
    $('.js-oc-index-abrir-estado').on('click', function () {
        $('#formIndexOcEstado').attr('action', $(this).data('url'));
        var cur = $(this).data('estado-actual') || '';
        $('#index_oc_estado_nuevo').val(cur);
        $('#index_oc_estado_obs').val('');
        $('#modalIndexOcCambiarEstado').modal('show');
    });
    if (window.OcCambiarSectorLegajo) {
        window.OcCambiarSectorLegajo.initForm($('#formIndexOcSector'));
    }
    $('.js-oc-index-abrir-sector').on('click', function () {
        var $form = $('#formIndexOcSector');
        $form.attr('action', $(this).data('url'));
        var sid = $(this).data('sector-id');
        if (sid) {
            $('#index_oc_sector_nuevo').val(String(sid));
        }
        $('#index_oc_sector_obs').val('');
        $('#index_oc_sector_leyenda').val('');
        $form.find('input[type=file]').val('');
        var ocId = $(this).data('ordencompra-id') || '';
        if (window.OcCambiarSectorLegajo) {
            window.OcCambiarSectorLegajo.setOrdencompraId($form, ocId);
        }
        $('#modalIndexOcCambiarSector').modal('show');
    });
});
</script>
@endsection

<?php use App\Support\Compras\OrdencompraListadoFiltros; ?>

@section('contenido')
@php
    $retornoListadoQuery = \App\Support\Listado\QueryRetornoListado::retornoLinksDesdeFiltrosQuery($filtrosQuery ?? []);
@endphp
@include('compras.ordencompra.partials.modal_enviar_proveedor')
@include('compras.ordencompra.partials.modal_asignar_factura_legajo')

<div class="modal fade" id="modalIndexOcCambiarEstado" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formIndexOcEstado" action="">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar estado</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="index_oc_estado_nuevo">Nuevo estado</label>
                        <select name="estado" id="index_oc_estado_nuevo" class="form-control" required>
                            @foreach ($estados as $est)
                                <option value="{{ $est }}">{{ $est }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="index_oc_estado_obs">Observación</label>
                        <textarea name="observacion" id="index_oc_estado_obs" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="modalIndexOcCambiarSector" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form method="POST" id="formIndexOcSector" action="" enctype="multipart/form-data"
                  data-sector-gastronomia-id="{{ (int) \App\Support\Compras\OrdencompraLegajoGastronomiaSupport::sectorGastronomiaId() }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Cambiar sector de legajo</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="index_oc_sector_nuevo">Sector</label>
                        <select name="sector_legajocompra_id" id="index_oc_sector_nuevo" class="form-control" required>
                            @foreach ($sectores as $sec)
                                <option value="{{ $sec->id }}">{{ $sec->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    @include('compras.ordencompra.partials.bloque_factura_legajo_sector', ['prefix' => 'index_oc'])
                    <div class="form-group">
                        <label for="index_oc_sector_obs">Observaci&oacute;n / comentario al &aacute;rbol</label>
                        <input type="text" name="observacion" id="index_oc_sector_obs" class="form-control" maxlength="255" placeholder="Motivo del traslado (tambi&eacute;n va al &aacute;rbol si aplica)">
                    </div>
                    <div class="form-group">
                        <label for="index_oc_sector_leyenda">Leyenda</label>
                        <textarea name="leyenda" id="index_oc_sector_leyenda" class="form-control" rows="2" maxlength="2000"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Registrar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="row oc-ui erp-ws-host">
    <div class="col-lg-12">
        @include('includes.mensaje')

        <div class="oc-header">
            <h1><i class="fa fa-shopping-cart"></i> Órdenes de compra</h1>
            <div class="oc-header-acciones">
                @include('includes.compras.boton-manual')
                @if (can('listar-legajo-compra', false) || can('listar-ordencompra', false))
                    <a href="{{ route('consultar_legajo_compra') }}"
                       class="btn btn-outline-light btn-sm"
                       title="Bandeja de pendientes, estados e histórico de legajos">
                        <i class="fa fa-folder-open"></i> Bandeja de legajos
                    </a>
                @endif
                @if (can('listar-kpi-compras', false))
                    <a href="{{ route('consultar_kpi_compras') }}"
                       class="btn btn-outline-success btn-sm"
                       title="Tablero de KPIs de proceso y productividad">
                        <i class="fas fa-chart-line"></i> KPIs
                    </a>
                @endif
                @include('includes.listado.filtros_toolbar', [
                    'formId' => 'form-filtros-ordencompra',
                    'filtroValor' => $filtros['valor'] ?? '',
                    'tieneCriterios' => OrdencompraListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                    'limpiarUrl' => route('consultar_ordencompra', OrdencompraListadoFiltros::paraQueryStringEmpresa($filtros ?? [])),
                    'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                    'toggleTarget' => '#panel-filtros-ordencompra',
                    'toggleId' => 'btn-toggle-filtros-ordencompra',
                    'inputId' => 'filtro_valor',
                    'nuevoRegistroUrl' => route('crear_ordencompra', $retornoListadoQuery),
                    'nuevoRegistroCan' => 'crear-ordencompra',
                    'nuevoRegistroLabel' => 'Nueva orden',
                ])
            </div>
        </div>

        <div class="oc-panel">
            <p class="oc-intro">
                Circuito de compra: de la requisición a la recepción, el legajo y el pago. Filtre por estado, empresa o texto; abra la OC en solapa sin salir del listado.
            </p>
            @if (!empty($alcanceSector))
                <p class="oc-alcance"><i class="fa fa-lock"></i> {{ $alcanceSector }}</p>
            @endif

            @include('compras.ordencompra.partials.resumen_index')
            @include('compras.ordencompra.partials.segmentos_estado')
            @include('compras.ordencompra.partials.filtros_externos')

            <form method="get" action="{{ route('consultar_ordencompra') }}" id="form-filtros-ordencompra" class="mb-0">
                @include('compras.ordencompra.partials.filtros_listado')
            </form>

            <div class="px-3 pt-2">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_ordencompra',
                    'queryparams' => $filtrosQuery ?? [],
                ])
            </div>

            <div class="table-responsive p-0">
                <table class="table table-hover oc-grilla mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Solicitante</th>
                            <th>Fecha</th>
                            <th>Empresa</th>
                            <th>Centro costo</th>
                            <th>Proveedor</th>
                            <th>Sector</th>
                            <th>Estado</th>
                            <th class="text-right">Σ ítems</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ordencompra as $row)
                            @php
                                $esSuspendidaFila = ($row->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA;
                            @endphp
                            <tr data-ws-id="{{ $row->id }}" @if($esSuspendidaFila) class="oc-fila-suspendida" @endif>
                                <td>
                                    <span class="oc-numero">{{ $row->numeroordencompra }}</span>
                                    @if (!empty($row->requisicion_id))
                                        <span class="oc-meta">Req. {{ $row->requisicion_id }}</span>
                                    @endif
                                </td>
                                <td>{{ $row->nombreusuario ?? '—' }}</td>
                                <td class="text-nowrap">{{ $row->fecha ? date('d/m/Y', strtotime($row->fecha)) : '—' }}</td>
                                <td>{{ $row->nombreempresa }}</td>
                                <td>{{ $row->nombrecentrocosto }}</td>
                                <td class="oc-proveedor">{{ $row->nombreproveedor }}</td>
                                <td>{{ $row->nombresector ?? '—' }}</td>
                                <td>
                                    @include('compras.ordencompra.partials.estado_badge', ['estado' => $row->estadoordencompra ?? ''])
                                </td>
                                <td class="oc-num">
                                    {{ number_format((float) ($row->monto_lineas ?? 0), 2, ',', '.') }}
                                </td>
                                <td>
                                    @include('compras.ordencompra.partials.acciones_grilla')
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="oc-vacio">
                                    <i class="fa fa-inbox"></i>
                                    <div class="oc-vacio-titulo">No hay órdenes para este filtro</div>
                                    Ajuste el estado, la empresa o el texto de búsqueda.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if (method_exists($ordencompra, 'links'))
            <div class="oc-footer">
                {{ $ordencompra->appends(array_merge($filtrosQuery ?? [], request()->only(['origen', 'vista'])))->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
