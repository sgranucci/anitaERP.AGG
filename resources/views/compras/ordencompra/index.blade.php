@extends("theme.$theme.layout")
@section('titulo')
Órdenes de compra
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/filtro.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/ordencompra/enviar-proveedor.js') }}" type="text/javascript"></script>
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
    $('.js-oc-index-abrir-sector').on('click', function () {
        $('#formIndexOcSector').attr('action', $(this).data('url'));
        var sid = $(this).data('sector-id');
        if (sid) {
            $('#index_oc_sector_nuevo').val(String(sid));
        }
        $('#index_oc_sector_obs').val('');
        $('#index_oc_sector_leyenda').val('');
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
            <form method="POST" id="formIndexOcSector" action="">
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
                    <div class="form-group">
                        <label for="index_oc_sector_obs">Observación</label>
                        <input type="text" name="observacion" id="index_oc_sector_obs" class="form-control" maxlength="255">
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

<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (!empty($sectorUsuario))
            <p class="text-muted small mb-2">Filtrado por su sector de legajo de compras asignado.</p>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Órdenes de compra</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.compras.boton-manual')
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
            <form method="get" action="{{ route('consultar_ordencompra') }}" id="form-filtros-ordencompra" class="mb-0">
                @include('compras.ordencompra.partials.filtros_listado')
            </form>
            @include('compras.ordencompra.partials.filtros_externos')
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_ordencompra',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
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
                        @foreach ($ordencompra as $row)
                            @php
                                $esSuspendidaFila = ($row->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA;
                            @endphp
                            <tr @if($esSuspendidaFila) class="table-secondary" @endif>
                                <td>{{ $row->numeroordencompra }}</td>
                                <td><small>{{ $row->nombreusuario ?? '' }}</small></td>
                                <td>{{ date('d/m/Y', strtotime($row->fecha)) }}</td>
                                <td>{{ $row->nombreempresa }}</td>
                                <td><small>{{ $row->nombrecentrocosto }}</small></td>
                                <td><small>{{ $row->nombreproveedor }}</small></td>
                                <td><small>{{ $row->nombresector ?? '—' }}</small></td>
                                <td>
                                    @include('compras.ordencompra.partials.estado_badge', ['estado' => $row->estadoordencompra ?? ''])
                                </td>
                                <td class="text-right text-nowrap">
                                    <small>{{ number_format((float) ($row->monto_lineas ?? 0), 2, ',', '.') }}</small>
                                </td>
                                <td>
                                    @if (can('editar-ordencompra', false))
                                        <a href="{{ route('editar_ordencompra', ['id' => $row->id] + $retornoListadoQuery) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                    @if (can('listar-ordencompra', false))
                                        <a href="{{ route('solo_consulta_ordencompra', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Solo consulta">
                                            <i class="fa fa-eye"></i>
                                        </a>
                                    @endif
                                    @if (can('listar-ordencompra', false) || can('editar-ordencompra', false))
                                        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Imprimir orden (PDF vertical)" target="_blank" rel="noopener noreferrer">
                                            <i class="fa fa-print"></i>
                                        </a>
                                        <a href="{{ route('imprimir_pdf_ordencompra', ['id' => $row->id, 'formato' => 'apaisado']) }}" class="btn-accion-tabla tooltipsC" title="PDF Legal apaisado" target="_blank" rel="noopener noreferrer">
                                            <i class="fa fa-arrows-alt-h"></i>
                                        </a>
                                    @endif
                                    @if (can('editar-ordencompra', false) && !empty($row->proveedor_id))
                                        <button type="button" class="btn-accion-tabla tooltipsC js-oc-enviar-proveedor text-success" title="Enviar OC al proveedor por email" data-ordencompra-id="{{ $row->id }}">
                                            <i class="fa fa-envelope"></i>
                                        </button>
                                    @endif
                                    @if (!empty($row->requisicion_id) && (can('editar-requisicion', false) || can('listar-requisicion', false)))
                                        <a href="{{ route('editar_requisicion', ['id' => $row->requisicion_id]) }}" class="btn-accion-tabla tooltipsC text-warning" title="Ver requisición" target="_blank" rel="noopener noreferrer">
                                            <i class="fa fa-link"></i>
                                        </a>
                                    @endif
                                    @if (can('actualizar-ordencompra', false))
                                        <button type="button" class="btn-accion-tabla tooltipsC js-oc-index-abrir-estado text-dark" title="Cambiar estado"
                                            data-url="{{ route('ordencompra_cambiar_estado', ['id' => $row->id]) }}"
                                            data-estado-actual="{{ $row->estadoordencompra }}">
                                            <i class="fa fa-random"></i>
                                        </button>
                                        <button type="button" class="btn-accion-tabla tooltipsC js-oc-index-abrir-sector text-dark" title="Cambiar sector"
                                            data-url="{{ route('ordencompra_cambiar_sector', ['id' => $row->id]) }}"
                                            data-sector-id="{{ $row->sector_legajocompra_id }}">
                                            <i class="fa fa-folder-open"></i>
                                        </button>
                                    @endif
                                    @if (($row->estadoordencompra ?? '') === \App\Support\Compras\OrdencompraEstados::SUSPENDIDA && can('actualizar-ordencompra', false))
                                        <form action="{{ route('ordencompra_reactivar', ['id' => $row->id]) }}" method="POST" class="d-inline" onsubmit="return confirm('¿Reactivar a PENDIENTE?');">
                                            @csrf
                                            <button type="submit" class="btn-accion-tabla tooltipsC text-warning" title="Reactivar">
                                                <i class="fa fa-undo"></i>
                                            </button>
                                        </form>
                                    @endif
                                    @if (can('borrar-ordencompra', false))
                                        <form action="{{ route('eliminar_ordencompra', ['id' => $row->id]) }}" class="d-inline form-eliminar" method="POST">
                                            @csrf
                                            @method('delete')
                                            <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar">
                                                <i class="fa fa-times-circle text-danger"></i>
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if (method_exists($ordencompra, 'links'))
            <div class="card-footer">
                {{ $ordencompra->appends(array_merge($filtrosQuery ?? [], request()->only(['origen', 'vista'])))->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
