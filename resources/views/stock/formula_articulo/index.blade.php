@extends("theme.$theme.layout")
@section('titulo')
F&oacute;rmulas de art&iacute;culos
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/index.js') }}" type="text/javascript"></script>
<script type="text/javascript">
$(document).ready(function () {
    $('.eliminar-formula').on('click', function () {
        if (!confirm('¿Eliminar esta fórmula?')) {
            return;
        }
        var url = $(this).data('url');
        var token = $('meta[name="csrf-token"]').attr('content') || @json(csrf_token());
        $.ajax({
            url: url,
            type: 'POST',
            dataType: 'json',
            data: {
                _token: token,
                _method: 'DELETE'
            },
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': token
            },
            success: function (r) {
                if (r.mensaje === 'ok') {
                    location.reload();
                } else {
                    alert(r.error || 'No se pudo eliminar');
                }
            },
            error: function (xhr) {
                var msg = 'Error de red';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    msg = xhr.responseJSON.message;
                } else if (xhr.responseJSON && xhr.responseJSON.error) {
                    msg = xhr.responseJSON.error;
                } else if (xhr.status === 419) {
                    msg = 'Sesión expirada o token CSRF inválido. Actualice la página e intente de nuevo.';
                } else if (xhr.status === 403) {
                    msg = 'Sin permiso para eliminar.';
                }
                alert(msg);
            }
        });
    });
});
</script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @if (! empty($sinFormulasCargadas ?? false))
        <div class="alert alert-info">
            No hay f&oacute;rmulas en el ERP. Cree registros con <strong>Nuevo registro</strong> o cargue los datos seg&uacute;n el procedimiento definido para esta instalaci&oacute;n.
        </div>
        @endif
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">F&oacute;rmulas de art&iacute;culos</h3>
                <div class="card-tools">
                    @if (can('crear-formula-articulo', false))
                    <a href="{{ route('crear_formula_articulo') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                    @if (can('actualizar-formula-articulo', false))
                    <form action="{{ route('vincular_formula_articulo_por_codigo') }}" method="POST" class="d-inline" onsubmit="return confirm('Vincular cada f\u00f3rmula con el art\u00edculo cuyo SKU coincide (c\u00f3digo Anita \u2192 V0000, ej. 365 \u2192 V0365) y actualizar articulo.formula.\n\n\u00bfContinuar?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-success btn-sm" title="Vincular fórmulas con artículos por código → SKU V####">
                            <i class="fa fa-fw fa-link"></i> Vincular con artículos
                        </button>
                    </form>
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end">
                    <form action="{{ route('consultar_formula_articulo') }}" method="GET">
                        <div class="btn-group">
                            @if (! empty($opcionalesHabilitados))
                                @php $opcSel = $conOpcionales ?? ''; @endphp
                                <select name="con_opcionales" class="form-control" title="Filtrar por ítems opcionales">
                                    <option value="" {{ $opcSel === '' ? 'selected' : '' }}>Todas (con/sin opcionales)</option>
                                    <option value="si" {{ $opcSel === 'si' ? 'selected' : '' }}>Solo con opcionales</option>
                                    <option value="no" {{ $opcSel === 'no' ? 'selected' : '' }}>Solo sin opcionales</option>
                                </select>
                            @endif
                            <input type="text" name="busqueda" class="form-control" placeholder="Búsqueda ..." value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla-queryparams', [
                    'ruta' => 'listar_formula_articulo',
                    'queryparams' => [
                        'busqueda' => $busqueda ?? '',
                        'con_opcionales' => $conOpcionales ?? '',
                    ],
                ])
                @php
                    use App\Support\Stock\FormulaArticuloNumero;
                    $indexTieneRanura = config('app.empresa') === 'FRASLE' && \Illuminate\Support\Facades\Schema::hasColumn('formula_articulo_hijo', 'ranura');
                    $indexGastOpc = ! empty($opcionalesHabilitados);
                    $indexMostrarCodigo = FormulaArticuloNumero::mostrarCodigo();
                @endphp
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>{{ FormulaArticuloNumero::etiquetaColumnaPrimaria() }}</th>
                            @unless ($indexMostrarCodigo)
                            <th>C&oacute;d. f&oacute;rmula</th>
                            @endunless
                            <th>Art&iacute;culo</th>
                            <th class="text-right">Cant. unidad</th>
                            <th>Estado</th>
                            <th>Detalle</th>
                            <th>&Iacute;tems</th>
                            <th class="width40" data-orderable="false"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($formulas as $data)
                        <tr>
                            <td>
                                @if ($indexMostrarCodigo)
                                    <small class="text-monospace">{{ FormulaArticuloNumero::paraFormula($data) }}</small>
                                    @include('stock.formula_articulo.partials.costo_total_index', ['formula' => $data])
                                @else
                                    {{ $data->id }}
                                @endif
                            </td>
                            @unless ($indexMostrarCodigo)
                            <td><small class="text-monospace">@if(! empty($data->codigo)){{ $data->codigo }}@else<span class="text-muted">&mdash;</span>@endif</small>@include('stock.formula_articulo.partials.costo_total_index', ['formula' => $data])</td>
                            @endunless
                            <td>
                                <small>
                                    @if (! empty($data->articulo_id))
                                        <a href="{{ route('editar_articulo', ['id' => $data->articulo_id, 'origen' => 'modal_consulta']) }}" class="text-primary" target="_blank" rel="noopener">{{ $data->articulo_sku ?? '' }}</a>
                                        — {{ $data->articulo_descripcion ?? '' }}
                                    @else
                                        <span class="text-muted">Sin art&iacute;culo cabecera</span>
                                    @endif
                                </small>
                            </td>
                            <td class="text-right"><small>{{ number_format((float) ($data->cantidadunidad ?? 0), 2, ',', '.') }}</small></td>
                            <td><small>{{ $data->estado }}</small></td>
                            <td class="small text-wrap" style="max-width: 14rem;">
                                @if(($data->detalle ?? '') !== '')
                                    {!! nl2br(e($data->detalle)) !!}
                                @else
                                    <span class="text-muted">&mdash;</span>
                                @endif
                            </td>
                            <td class="small">
                                @forelse ($data->formula_articulo_hijos ?? [] as $h)
                                    @include('stock.formula_articulo.partials.index_linea_item', [
                                        'h' => $h,
                                        'loop' => $loop,
                                        'indexGastOpc' => $indexGastOpc,
                                        'indexTieneRanura' => $indexTieneRanura,
                                    ])
                                @empty
                                    <span class="text-muted">&mdash;</span>
                                @endforelse
                            </td>
                            <td>
                                @if (can('editar-formula-articulo', false))
                                <a href="{{ route('editar_formula_articulo', ['id' => $data->id]) }}" class="btn-accion-tabla tooltipsC" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                                @endif
                                @if (can('borrar-formula-articulo', false))
                                <button type="button" class="btn-accion-tabla eliminar-formula tooltipsC text-danger" title="Eliminar" data-url="{{ route('eliminar_formula_articulo', ['id' => $data->id]) }}">
                                    <i class="fa fa-trash"></i>
                                </button>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
                <div class="px-3 py-2">
                    {{ $formulas->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
