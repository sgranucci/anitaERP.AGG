@extends("theme.$theme.layout")
@section('titulo')
    Rendiciones vending (caja)
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}"></script>
<script>
    function eliminarRendicionMaquinavending(event) {
        if (!confirm('¿Eliminar esta presentación de rendición vending en caja?\n\nLa rendición en Ventas quedará nuevamente pendiente de presentar.')) {
            event.preventDefault();
        }
    }
</script>
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
        @if (!empty($errores))
        <div class="alert alert-danger">
            @foreach ((array) $errores as $err)
                <div>{{ $err }}</div>
            @endforeach
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Presentaciones rendici&oacute;n vending en caja</h3>
                <div class="card-tools">
                    @include('includes.ventas.boton-manual-vending')
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-rendicion-mv-caja',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => \App\Support\Caja\RendicionMaquinavendingCajaListadoFiltros::tieneCriteriosUsuario($filtros ?? []),
                        'limpiarUrl' => route('rendicionmaquinavending'),
                        'placeholder' => 'Código, ID rendición Ventas…',
                        'toggleTarget' => '#panel-filtros-rendicion-mv-caja',
                        'toggleId' => 'btn-toggle-filtros-rendicion-mv-caja',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_rendicionmaquinavending'),
                        'nuevoRegistroCan' => 'crear-rendicion-maquinavending-caja',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('rendicionmaquinavending') }}" id="form-filtros-rendicion-mv-caja" class="mb-0">
                @include('caja.rendicionmaquinavending.partials.filtros_listado')
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_rendicionmaquinavending',
                    'queryparams' => $filtrosQuery ?? [],
                ])
                <table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>C&oacute;digo</th>
                            <th>Fecha caja</th>
                            <th>Empresa</th>
                            <th>Caja</th>
                            <th>N&ordm; cierre Ventas</th>
                            <th>M&aacute;quina</th>
                            <th class="text-right">Cobrado</th>
                            <th class="width80"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($rendiciones as $row)
                        <tr>
                            <td>{{ $row->id }}</td>
                            <td>{{ $row->codigo }}</td>
                            <td>{{ $row->fecharendicion?->format('d/m/Y H:i') }}</td>
                            <td>{{ $row->empresa?->nombre }}</td>
                            <td>{{ $row->caja?->nombre }}</td>
                            <td>#{{ (int) ($row->maquinavendingRendicion?->numero_cierre ?? 0) }}</td>
                            <td>{{ $row->maquinavending?->nombre }}</td>
                            <td class="text-right">${{ number_format((float) $row->totalcobrado, 2, ',', '.') }}</td>
                            <td class="text-nowrap">
                                @if (can('editar-rendicion-maquinavending-caja', false) && \App\Support\Caja\RendicionMaquinavendingCajaPermiso::puedeActualizarPorFecha($row))
                                <a href="{{ route('editar_rendicionmaquinavending', ['id' => $row->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar"><i class="fa fa-edit"></i></a>
                                @endif
                                <a href="{{ route('imprimir_rendicion_maquinavending', ['id' => $row->id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Imprimir comprobante caja" target="_blank" rel="noopener">
                                    <i class="fa fa-print"></i>
                                </a>
                                @if (
                                    ($row->maquinavending_rendicion_id ?? 0) > 0
                                    && can('ver-comprobante-maquinavending-rendicion-gastronomia', false)
                                )
                                <a href="{{ route('maquinavending_rendicion_comprobante', ['id' => $row->maquinavending_rendicion_id, 'inline' => 1]) }}"
                                   class="btn-accion-tabla tooltipsC" title="Comprobante rendici&oacute;n Ventas" target="_blank" rel="noopener">
                                    <i class="fa fa-file-pdf-o text-danger"></i>
                                </a>
                                @endif
                                @if (can('borrar-rendicion-maquinavending-caja', false) && \App\Support\Caja\RendicionMaquinavendingCajaPermiso::puedeEliminar($row))
                                <form action="{{ route('eliminar_rendicionmaquinavending', ['id' => $row->id]) }}" class="d-inline form-eliminar" method="POST">
                                    @csrf @method('delete')
                                    <button type="submit" onclick="eliminarRendicionMaquinavending(event)" class="btn-accion-tabla eliminar tooltipsC" title="Eliminar presentaci&oacute;n en caja">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                </form>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="9" class="text-center text-muted p-4">Sin presentaciones registradas.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if ($rendiciones instanceof \Illuminate\Pagination\LengthAwarePaginator)
            <div class="card-footer clearfix">{{ $rendiciones->appends($filtrosQuery ?? [])->links() }}</div>
            @endif
        </div>
    </div>
</div>
@endsection
