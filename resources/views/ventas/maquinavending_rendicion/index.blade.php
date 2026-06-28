@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones vending
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
@if (session('url_comprobante_pdf'))
<script>
(function () {
    var url = @json(session('url_comprobante_pdf'));
    if (url) {
        window.open(url, '_blank', 'noopener');
    }
})();
</script>
@endif
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Rendiciones de m&aacute;quinas vending</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.ventas.boton-manual-vending')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-mv-rendicion',
                        'filtroValor' => $filtros['filtro_valor'] ?? '',
                        'tieneCriterios' => \App\Support\Ventas\MaquinavendingRendicionListadoFiltros::tieneCriteriosAplicados($filtros ?? []),
                        'limpiarUrl' => route('consultar_maquinavending_rendicion_gastronomia'),
                        'placeholder' => 'Nº cierre, máquina, PV…',
                        'toggleTarget' => '#panel-filtros-mv-rendicion',
                        'toggleId' => 'btn-toggle-filtros-mv-rendicion',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_maquinavending_rendicion_gastronomia'),
                        'nuevoRegistroCan' => 'crear-maquinavending-rendicion-gastronomia',
                    ])
                </div>
            </div>
            <div class="card-body border-bottom py-2 mb-0 bg-light">
                <p class="small text-muted mb-0">
                    El <strong>N&ordm; cierre</strong> es correlativo por empresa. Tras registrar en Ventas se replica a Anita (<code>rendgastro</code>).
                    La presentaci&oacute;n en caja es un paso aparte (men&uacute; Caja &rarr; Rendiciones vending).
                    Mientras no est&eacute; presentada en caja puede <strong>editar o eliminar</strong> desde el listado.
                </p>
            </div>
            <form method="get" action="{{ route('consultar_maquinavending_rendicion_gastronomia') }}" id="form-filtros-mv-rendicion" class="mb-0">
                @include('ventas.maquinavending_rendicion.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_maquinavending_rendicion',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>N&ordm; cierre <span class="font-weight-normal text-muted" style="font-size:10px;">(empresa)</span></th>
                            <th>Fecha</th>
                            <th>Jornada</th>
                            <th>Empresa</th>
                            <th>M&aacute;quina</th>
                            <th>PV</th>
                            <th class="text-right">Total ventas</th>
                            <th class="text-right">Total cobrado</th>
                            <th>Caja</th>
                            <th>Anita</th>
                            <th>Usuario</th>
                            <th class="width80" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($coleccion as $fila)
                        <tr>
                            <td><strong>#{{ (int) $fila->numero_cierre }}</strong></td>
                            <td>{{ $fila->fecha_rendicion?->format('d/m/Y H:i') }}</td>
                            <td>{{ $fila->fecha_jornada?->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $fila->nombreempresa }}</td>
                            <td>{{ $fila->maquina_nombre }}</td>
                            <td>{{ $fila->puntoventa_codigo ?: '—' }}</td>
                            <td class="text-right">${{ number_format((float) $fila->total_ventas, 2, ',', '.') }}</td>
                            <td class="text-right">${{ number_format((float) $fila->total_cobrado, 2, ',', '.') }}</td>
                            <td>
                                @if ($fila->rendicionCaja)
                                    <span class="badge badge-success" title="{{ $fila->rendicionCaja->codigo }}">Presentada</span>
                                @else
                                    <span class="badge badge-warning">Pendiente</span>
                                @endif
                            </td>
                            <td>
                                @if ($fila->anita_sincronizado_en)
                                    <span class="badge badge-info" title="{{ $fila->anita_sincronizado_en->format('d/m/Y H:i') }}">OK</span>
                                @else
                                    <span class="badge badge-secondary">—</span>
                                @endif
                            </td>
                            <td>{{ optional($fila->usuario)->nombre }}</td>
                            <td class="text-nowrap">
                                @php
                                    $puedeModificarVentas = \App\Support\Ventas\MaquinavendingRendicionPermiso::puedeModificar($fila);
                                    $motivoBloqueoVentas = \App\Support\Ventas\MaquinavendingRendicionPermiso::mensajeBloqueoModificacion($fila);
                                    $puedeEditarVentas = can('editar-maquinavending-rendicion-gastronomia', false)
                                        || can('actualizar-maquinavending-rendicion-gastronomia', false);
                                    $puedeBorrarVentas = can('borrar-maquinavending-rendicion-gastronomia', false);
                                @endphp
                                @if ($puedeModificarVentas && $puedeEditarVentas)
                                <a href="{{ route('editar_maquinavending_rendicion_gastronomia', ['id' => $fila->id]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Editar rendici&oacute;n">
                                    <i class="fa fa-edit"></i>
                                </a>
                                @elseif ($puedeEditarVentas)
                                <span class="btn-accion-tabla text-muted tooltipsC"
                                      title="{{ $motivoBloqueoVentas }}">
                                    <i class="fa fa-edit"></i>
                                </span>
                                @endif
                                @if (can('ver-comprobante-maquinavending-rendicion-gastronomia', false))
                                <a href="{{ route('maquinavending_rendicion_comprobante', ['id' => $fila->id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Imprimir comprobante rendici&oacute;n" target="_blank" rel="noopener">
                                    <i class="fa fa-print"></i>
                                </a>
                                @endif
                                @if ($puedeModificarVentas && $puedeBorrarVentas)
                                <form action="{{ route('eliminar_maquinavending_rendicion_gastronomia', ['id' => $fila->id]) }}"
                                      class="d-inline form-eliminar" method="POST">
                                    @csrf @method('delete')
                                    <button type="submit" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar rendici&oacute;n">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @elseif ($puedeBorrarVentas)
                                <span class="btn-accion-tabla text-muted tooltipsC"
                                      title="{{ $motivoBloqueoVentas }}">
                                    <i class="fa fa-times-circle"></i>
                                </span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="12" class="text-center text-muted p-4">Sin rendiciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($coleccion instanceof \Illuminate\Pagination\LengthAwarePaginator && $coleccion->hasPages())
            <div class="card-footer clearfix">
                @if ($coleccion->total() > 0)
                    <span class="float-left text-muted small pt-2">
                        Mostrando {{ $coleccion->firstItem() }}&ndash;{{ $coleccion->lastItem() }} de {{ $coleccion->total() }}
                    </span>
                @endif
                {{ $coleccion->appends($filtrosQuery ?? [])->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
