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
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">F&oacute;rmulas de art&iacute;culos</h3>
                <div class="card-tools">
                    @if (can('crear-formula-articulo', false))
                    <a href="{{ route('crear_formula_articulo') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-fw fa-plus-circle"></i> Nuevo registro
                    </a>
                    @endif
                </div>
                <div class="d-md-flex justify-content-md-end">
                    <form action="{{ route('consultar_formula_articulo') }}" method="GET">
                        <div class="btn-group">
                            <input type="text" name="busqueda" class="form-control" placeholder="Búsqueda ..." value="{{ $busqueda ?? '' }}">
                            <button type="submit" class="btn btn-default">
                                <span class="fa fa-search"></span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            <div class="card-body table-responsive p-0">
                @include('includes.exportar-tabla', ['ruta' => 'listar_formula_articulo', 'busqueda' => $busqueda ?? ''])
                @php
                    $indexTieneRanura = config('app.empresa') === 'FRASLE' && \Illuminate\Support\Facades\Schema::hasColumn('formula_articulo_hijo', 'ranura');
                @endphp
                <table class="table table-striped table-bordered table-hover" id="tabla-paginada">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>C&oacute;d. f&oacute;rmula</th>
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
                            <td>{{ $data->id }}</td>
                            <td><small class="text-monospace">@if(! empty($data->codigo)){{ $data->codigo }}@else<span class="text-muted">&mdash;</span>@endif</small></td>
                            <td>
                                <small>
                                    @if (! empty($data->articulo_id))
                                        <a href="{{ route('editar_articulo', ['id' => $data->articulo_id]) }}" target="_blank" rel="noopener">{{ $data->articulo_sku ?? '' }}</a>
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
                                    @php
                                        $idxDep = $h->depositos ?? null;
                                        $idxDepStr = $idxDep ? trim(($idxDep->codigo ?? '').' '.($idxDep->nombre ?? '')) : '';
                                    @endphp
                                    <div class="mb-1 pb-1 @if(! $loop->last) border-bottom border-light @endif">
                                        @if($h->articulo_id)
                                            <div>
                                                <a href="{{ route('editar_articulo', ['id' => $h->articulo_id]) }}" target="_blank" rel="noopener">{{ $h->articulos->sku ?? '' }}</a>
                                                <span class="text-muted"> — {{ $h->articulos->descripcion ?? '' }}</span>
                                            </div>
                                            <div class="text-right text-nowrap"><small class="text-muted">Cant. {{ number_format((float) ($h->cantidad ?? 0), 2, ',', '.') }} · FC {{ number_format((float) ($h->factorcosto ?? 0), 2, ',', '.') }}</small></div>
                                            @if($idxDepStr !== '')
                                                <div><small class="text-muted">Dep.: {{ $idxDepStr }}</small></div>
                                            @endif
                                            @if($indexTieneRanura && ($h->ranura ?? '') !== '' && $h->ranura !== null)
                                                <div><small class="text-muted">Ranura: {{ $h->ranura }}</small></div>
                                            @endif
                                        @elseif($h->formula_hija_id)
                                            @php
                                                $subArt = optional($h->formula_hija)->articulos;
                                                $fhIdx = $h->formula_hija;
                                            @endphp
                                            <div>
                                                <a href="{{ route('editar_formula_articulo', ['id' => $h->formula_hija_id]) }}" target="_blank" rel="noopener">F&oacute;rmula #{{ $h->formula_hija_id }}</a>
                                                @if($subArt)
                                                    <span class="text-muted"> — {{ $subArt->sku ?? '' }} — {{ $subArt->descripcion ?? '' }}</span>
                                                @else
                                                    <span class="text-muted"> — <em>Sin art&iacute;culo cabecera en subf&oacute;rmula</em></span>
                                                @endif
                                            </div>
                                            <div class="text-right text-nowrap"><small class="text-muted">Cant. {{ number_format((float) ($h->cantidad ?? 0), 2, ',', '.') }} · FC {{ number_format((float) ($h->factorcosto ?? 0), 2, ',', '.') }}</small></div>
                                            @if($fhIdx && trim((string) ($fhIdx->codigo ?? '')) !== '')
                                                <div><small class="text-muted">C&oacute;d. subf.: {{ $fhIdx->codigo }}</small></div>
                                            @endif
                                            @if($fhIdx && trim((string) ($fhIdx->detalle ?? '')) !== '')
                                                <div class="text-muted"><small>{!! nl2br(e($fhIdx->detalle)) !!}</small></div>
                                            @endif
                                            @if($idxDepStr !== '')
                                                <div><small class="text-muted">Dep.: {{ $idxDepStr }}</small></div>
                                            @endif
                                            @if($indexTieneRanura && ($h->ranura ?? '') !== '' && $h->ranura !== null)
                                                <div><small class="text-muted">Ranura: {{ $h->ranura }}</small></div>
                                            @endif
                                        @endif
                                    </div>
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
