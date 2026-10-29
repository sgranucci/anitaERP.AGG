@extends("theme.$theme.layout")
@section('titulo')
	@if (!empty($esConsulta))
		Consulta de OT
	@else
		Órdenes de trabajo
	@endif
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
    var $tr = $(this).closest('tr');
    var codigo = $.trim($tr.find('.codigo').text());
    var ordentrabajo_id = $(this).data('id') || codigo;
    if (!confirm('Esta por borrar la OT ' + codigo)) {
        return;
    }
    var token = $("meta[name='csrf-token']").attr("content") || $('#csrf_token').val();
    $.post("{{ url('ventas/ordenestrabajo/borrarOt') }}", {
        ordentrabajo_id: ordentrabajo_id,
        _token: token
    })
    .done(function (data) {
        if (!data || data.mensaje != 'ok') {
            alert((data && data.mensaje) ? data.mensaje : 'No se pudo borrar la OT ' + codigo);
            return;
        }
        alert('Orden de trabajo Número: ' + codigo + '\nBorrada correctamente.');
        $tr.remove();
    })
    .fail(function (xhr) {
        var msg = 'No se pudo borrar la OT ' + codigo;
        if (xhr.responseJSON && xhr.responseJSON.mensaje) {
            msg = xhr.responseJSON.mensaje;
        }
        alert(msg);
    });
}
</script>
@endsection

@section('contenido')
@php
    use App\Support\Ventas\OrdentrabajoListadoFiltros;
    $esConsulta = !empty($esConsulta);
    $rutaListado = $rutaListado ?? ($esConsulta ? 'consulta_ordentrabajo' : 'ordentrabajo');
    $filtrosQuery = $filtrosQuery ?? [];
    $limpiarUrl = route($rutaListado);
@endphp
<meta name="csrf-token" content="{{ csrf_token() }}">
<input type="hidden" id="csrf_token" value="{{ csrf_token() }}">
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $esConsulta ? 'Consulta de OT' : 'Órdenes de trabajo' }}</h3>
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
                        'nuevoRegistroUrl' => $esConsulta ? null : route('crear_ordentrabajo'),
                        'nuevoRegistroCan' => 'crear-ordenes-de-trabajo',
                        'nuevoRegistroLabel' => 'Nueva OT',
                    ])
                </div>
            </div>
            <form method="get" action="{{ route($rutaListado) }}" id="form-filtros-ordentrabajo" class="mb-0">
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
                            <th class="text-right">Pares</th>
                            <th class="width30">Estado</th>
                            <th class="width40 text-nowrap" data-orderable="false">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($ordentrabajo as $data)
                        @php
                            $codigoOt = str_pad($data->codigo, 4, '0', STR_PAD_LEFT);
                            $urlOt = route('editar_ordentrabajo', $esConsulta
                                ? ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']
                                : ['id' => $data->id]);
                        @endphp
                        <tr>
                            <td class="codigo">
                                @if (can('editar-ordenes-de-trabajo', false))
                                    @if ($esConsulta)
                                        <a href="{{ $urlOt }}" class="text-primary font-weight-bold" target="_blank" rel="noopener">{{ $codigoOt }}</a>
                                    @else
                                        <a href="{{ $urlOt }}" class="text-primary font-weight-bold">{{ $codigoOt }}</a>
                                    @endif
                                @else
                                    {{ $codigoOt }}
                                @endif
                            </td>
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
                            <td class="text-right">
                                @php
                                    $pares = 0.;
                                    if (isset($data->ordentrabajo_combinacion_talles)) {
                                        foreach ($data->ordentrabajo_combinacion_talles as $item) {
                                            $pares += $item->pedido_combinacion_talles->cantidad ?? 0;
                                        }
                                    }
                                @endphp
                                {{ number_format($pares, 0, ',', '.') }}
                            </td>
                            <td>
                                @php $ultimaTarea = ''; @endphp
                                @foreach ($data->ordentrabajo_tareas as $tarea)
                                    @php $ultimaTarea = $tarea->tareas->nombre ?? $ultimaTarea; @endphp
                                @endforeach
                                @if ($ultimaTarea !== '')
                                    <span class="badge" style="background:#D6EAF8;color:#1B4F72;">{{ $ultimaTarea }}</span>
                                @endif
                            </td>
                            <td class="text-nowrap">
                                @if (can('editar-ordenes-de-trabajo', false))
                                    @if ($esConsulta)
                                        <a href="{{ $urlOt }}" class="btn-accion-tabla tooltipsC" title="Consultar esta OT" target="_blank" rel="noopener">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @else
                                        <a href="{{ $urlOt }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                            <i class="fa fa-edit"></i>
                                        </a>
                                    @endif
                                @endif
                                @if (can('listar-ordenes-de-trabajo', false))
                                    <a href="#" onclick="return imprimeOt('{{ $data->codigo }}')" class="btn-accion-tabla tooltipsC" title="Imprimir la OT">
                                        <i class="fa fa-print"></i>
                                    </a>
                                    <a href="#" onclick="return pdfOt('{{ $data->codigo }}')" class="btn-accion-tabla tooltipsC" title="PDF de la OT">
                                        <i class="fas fa-file-pdf text-danger"></i>
                                    </a>
                                @endif
                                @if (! $esConsulta && can('borrar-ordenes-de-trabajo', false))
                                    <button type="button" class="btn-accion-tabla borraot tooltipsC" title="Eliminar este registro" data-id="{{ $data->id }}">
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
            <div class="card-footer clearfix d-flex flex-wrap align-items-center justify-content-between">
                <span class="text-muted small">
                    @if ($ordentrabajo->total() > 0)
                        {{ $ordentrabajo->firstItem() }}–{{ $ordentrabajo->lastItem() }} de {{ $ordentrabajo->total() }}
                    @else
                        Sin registros
                    @endif
                </span>
                {{ $ordentrabajo->appends($filtrosQuery)->links() }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection
