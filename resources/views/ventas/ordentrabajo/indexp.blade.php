@extends("theme.$theme.layout")
@section('titulo')
	Órdenes de trabajo
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/includes/listado-filtros.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/ordentrabajo/imprimeOt.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/ordentrabajo/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/ordentrabajo/filtro.js')) ?: time() }}" type="text/javascript"></script>
<script>
$(function () {
    $(document).on('click', '.borraot', borraOt);
});

function borraOt() {
    var ordentrabajo_id = $(this).parents('tr').find('.codigo').html();
    var ptr = this;
    if (!confirm('Esta por borrar la OT ' + ordentrabajo_id)) {
        return;
    }
    var token = $("meta[name='csrf-token']").attr("content") || $('#csrf_token').val();
    $.post("{{ url('ventas/ordenestrabajo/borrarOt') }}", {
        ordentrabajo_id: ordentrabajo_id,
        _token: token
    }, function (data) {
        if (data.mensaje != 'ok') {
            alert(data.mensaje);
        } else {
            alert("Orden de trabajo Número: " + ordentrabajo_id + "\nBorrada: " + data.mensaje);
            $(ptr).parents('tr').remove();
        }
    });
}
</script>
@endsection

@section('contenido')
@php
    use App\Support\Ventas\OrdentrabajoListadoFiltros;
    $filtrosQuery = $filtrosQuery ?? [];
    $limpiarUrl = route('ordentrabajo');
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Órdenes de trabajo</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    @include('includes.listado.filtros_toolbar', [
                        'formId' => 'form-filtros-ordentrabajo',
                        'filtroValor' => $filtros['valor'] ?? '',
                        'tieneCriterios' => OrdentrabajoListadoFiltros::tieneCriteriosTexto($filtros ?? []),
                        'limpiarUrl' => $limpiarUrl,
                        'placeholder' => 'Búsqueda rápida (tolera errores de tipeo)…',
                        'toggleTarget' => '#panel-filtros-ordentrabajo',
                        'toggleId' => 'btn-toggle-filtros-ordentrabajo',
                        'inputId' => 'filtro_valor',
                        'nuevoRegistroUrl' => route('crear_ordentrabajo'),
                        'nuevoRegistroCan' => 'crear-ordenes-de-trabajo',
                        'nuevoRegistroLabel' => 'Nueva OT',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route('ordentrabajo') }}" id="form-filtros-ordentrabajo" class="mb-0">
                @include('ventas.ordentrabajo.partials.filtros_listado', [
                    'limpiarUrl' => $limpiarUrl,
                ])
            </form>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'lista_ordentrabajo',
                    'queryparams' => $filtrosQuery,
                ])
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th class="width20">Nro.OT</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Artículo</th>
                            <th>Combinación</th>
                            <th>Pares</th>
                            <th class="width30">Estado</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ordentrabajo as $data)
                        <tr>
                            <td class="codigo">{{ str_pad($data->codigo, 4, '0', STR_PAD_LEFT) }}</td>
                            <td class="fecha">{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
                            <td>
                                @php
                                    $clientes = [];
                                    if (isset($data->ordentrabajo_combinacion_talles)) {
                                        foreach ($data->ordentrabajo_combinacion_talles as $item) {
                                            $nombreCliente = $item->clientes->nombre ?? null;
                                            if ($nombreCliente !== null && ! in_array($nombreCliente, $clientes, true)) {
                                                $clientes[] = $nombreCliente;
                                            }
                                        }
                                    }
                                @endphp
                                {{ count($clientes) > 1 ? 'BOLETAS JUNTAS' : ($clientes[0] ?? '') }}
                            </td>
                            @php $pedidoCombinacionOt = $data->pedidoCombinacionVigente(); @endphp
                            <td>{{ $pedidoCombinacionOt?->articulos->descripcion ?? '' }}</td>
                            <td>{{ $pedidoCombinacionOt?->combinaciones->nombre ?? '' }}</td>
                            <td>
                                @php
                                    $pares = 0.;
                                    if (isset($data->ordentrabajo_combinacion_talles)) {
                                        foreach ($data->ordentrabajo_combinacion_talles as $item) {
                                            $pares += $item->pedido_combinacion_talles->cantidad ?? 0;
                                        }
                                    }
                                @endphp
                                {{ $pares }}
                            </td>
                            <td>
                                @php $ultimaTarea = ''; @endphp
                                @foreach ($data->ordentrabajo_tareas as $tarea)
                                    @php $ultimaTarea = $tarea->tareas->nombre ?? $ultimaTarea; @endphp
                                @endforeach
                                {{ $ultimaTarea }}
                            </td>
                            <td>
                                @if (can('editar-ordenes-de-trabajo', false))
                                    <a href="{{ route('editar_ordentrabajo', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                        <i class="fa fa-edit"></i>
                                    </a>
                                @endif
                                @if (can('listar-ordenes-de-trabajo', false))
                                    <a href="#" onclick="return imprimeOt('{{ $data->codigo }}')" class="btn-accion-tabla tooltipsC" title="Imprimir la OT">
                                        <i class="fa fa-print"></i>
                                    </a>
                                    <a href="#" onclick="return pdfOt('{{ $data->codigo }}')" class="btn-accion-tabla tooltipsC" title="PDF de la OT">
                                        <i class="fas fa-file-pdf text-danger"></i>
                                    </a>
                                @endif
                                @if (can('borrar-ordenes-de-trabajo', false))
                                    <button type="button" class="btn-accion-tabla borraot tooltipsC" title="Eliminar este registro">
                                        <i class="fa fa-times-circle text-danger"></i>
                                    </button>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay órdenes de trabajo para este filtro.</td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if (method_exists($ordentrabajo, 'links'))
            <div class="card-footer clearfix">
                {{ $ordentrabajo->appends($filtrosQuery)->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
