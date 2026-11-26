@extends("theme.$theme.layout")
@section('titulo')
    Reporte de ahorros en compras
@endsection

@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/reportes/empresas_checkboxes.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/proveedor/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/contable/centrocosto/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/stock/usoarticulo/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/usoarticulo/consulta.js')) ?: time() }}"></script>
<script src="{{ asset('assets/pages/scripts/compras/ahorro_reporte/filtro.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/compras/ahorro_reporte/filtro.js')) ?: time() }}"></script>
<script src="{{ asset('assets/js/enter-campos.js') }}?v={{ @filemtime(public_path('assets/js/enter-campos.js')) ?: time() }}"></script>
<link rel="stylesheet" href="{{ asset('assets/css/tabla-ancha-reporte.css') }}?v={{ @filemtime(public_path('assets/css/tabla-ancha-reporte.css')) ?: time() }}">
<script src="{{ asset('assets/pages/scripts/admin/tabla-ancha-reporte.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/admin/tabla-ancha-reporte.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'ahorro-export-overlay',
    'tituloId' => 'ahorro-export-overlay-titulo',
    'subtituloId' => 'ahorro-export-overlay-subtitulo',
    'titulo' => 'Exportando…',
    'subtitulo' => 'Generando el archivo. No cierre la página.',
])
@php
    $colLabel = 'col-lg-2 control-label text-right pr-2';
    $colInput = 'col-lg-4';
    $fmt = fn ($n) => number_format((float) $n, 2, ',', '.');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Reporte de ahorros en compras</h3>
                <div class="card-tools d-flex flex-wrap align-items-center justify-content-end">
                    <a href="{{ route('reporte_ahorro_compras') }}" class="btn btn-outline-secondary btn-sm" title="Limpiar filtros">
                        <i class="fa fa-eraser"></i> Limpiar
                    </a>
                </div>
            </div>
            <form method="get" action="{{ route('reporte_ahorro_compras') }}" id="form-ahorro-reporte" class="mb-0" data-enter-campos="1">
                <div class="card-body pb-2">
                    <p class="text-muted small mb-3">
                        Ahorro = precio anterior &minus; precio nuevo, neto de IVA, convertido a pesos con la cotizaci&oacute;n BNA vigente de la fecha de la OC.
                        Un valor negativo es sobrecosto. % de ahorro = ahorro / (gasto + ahorro). OC suspendidas no se consideran.
                    </p>

                    @include('includes.reportes.asignacion_empresas_checkboxes', [
                        'empresa_query' => $empresa_query,
                        'empresa_ids_seleccionados' => $filtros['empresa_ids'] ?? [],
                        'reporte_clave' => 'ahorro_compras_reporte',
                        'mostrar_consolidar' => false,
                        'col_label' => 'col-lg-2 col-form-label text-right pr-2',
                        'id_prefix' => 'ahorro',
                    ])

                    <div class="form-group row">
                        <label for="desde" class="{{ $colLabel }} requerido">Desde fecha OC</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="desde" id="desde" class="form-control" style="max-width: 11.5rem;"
                                value="{{ $filtros['desde'] }}" required>
                        </div>
                        <label for="hasta" class="{{ $colLabel }} requerido">Hasta fecha OC</label>
                        <div class="{{ $colInput }}">
                            <input type="date" name="hasta" id="hasta" class="form-control" style="max-width: 11.5rem;"
                                value="{{ $filtros['hasta'] }}" required>
                        </div>
                    </div>

                    @include('compras.partials.campo_consulta_proveedor', [
                        'prefix' => 'ahorro',
                        'proveedorId' => $filtros['proveedor_id'] ?: '',
                        'codigo' => $proveedor->codigo ?? '',
                        'nombre' => $proveedor->nombre ?? '',
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-6',
                    ])
                    @include('contable.partials.campo_consulta_centrocosto', [
                        'prefix' => 'ahorro',
                        'layout' => 'form_row',
                        'label' => 'Centro de costo destino',
                        'centrocostoId' => $filtros['centrocosto_id'] ?: '',
                        'codigo' => $centrocosto->codigo ?? '',
                        'descripcion' => $centrocosto->nombre ?? '',
                        'required' => false,
                        'mostrar_editar' => false,
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-6',
                    ])
                    @include('stock.partials.campo_consulta_usoarticulo', [
                        'prefix' => 'ahorro',
                        'label' => 'Uso de art&iacute;culo',
                        'inputName' => 'usoarticulo_id',
                        'inputId' => 'usoarticulo_ahorro',
                        'usoarticuloId' => $filtros['usoarticulo_id'] ?: '',
                        'codigo' => $usoarticulo->id ?? '',
                        'nombre' => $usoarticulo->nombre ?? '',
                        'mostrar_editar' => false,
                        'col_label' => $colLabel,
                        'col_input' => 'col-lg-6',
                    ])

                    <div class="form-group row">
                        <label class="{{ $colLabel }}">Tipo de ahorro</label>
                        <div class="col-lg-6">
                            <div class="d-flex align-items-center" style="gap: 4px;">
                                <input type="hidden" name="tipoahorro_id" id="ahorro-tipo-id" value="{{ $filtros['tipoahorro_id'] ?: '' }}">
                                <button type="button" class="btn-accion-tabla flex-shrink-0" id="ahorro-tipo-lupa" title="Elegir tipo de ahorro">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" id="ahorro-tipo-nombre" class="form-control" readonly placeholder="Todos" value="{{ $tipoahorro->nombre ?? '' }}">
                                <button type="button" class="btn btn-link btn-sm text-danger" id="ahorro-tipo-quitar">Quitar</button>
                            </div>
                            <div class="list-group mt-1 d-none" id="ahorro-tipo-lista"></div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label for="comprador_id" class="{{ $colLabel }}">Comprador</label>
                        <div class="{{ $colInput }}">
                            <select name="comprador_id" id="comprador_id" class="form-control">
                                <option value="0">Todos</option>
                                @foreach ($compradores as $u)
                                    <option value="{{ $u->id }}" @selected((int) $filtros['comprador_id'] === (int) $u->id)>
                                        {{ $u->nombre ?: $u->usuario }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-lg-6 text-right">
                            <button type="submit" name="consultar" value="1" class="btn btn-primary btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        @if ($filas !== null)
            <div class="card card-info">
                <div class="card-header d-flex flex-wrap align-items-center justify-content-between">
                    <h3 class="card-title">
                        Resultado: {{ $filas->total() }} l&iacute;nea(s) con ahorro
                    </h3>
                    <div class="d-flex align-items-center">
                        <span class="mr-3">
                            Ahorro:
                            <strong class="{{ ($resumen['ahorro_pesos'] ?? 0) < 0 ? 'text-danger' : 'text-success' }}">
                                $ {{ $fmt($resumen['ahorro_pesos'] ?? 0) }}
                            </strong>
                            &middot; {{ $resumen['porcentaje'] !== null ? $fmt($resumen['porcentaje']).' %' : '—' }}
                        </span>
                        @include('includes.exportar-tabla-queryparams', [
                            'ruta' => 'listar_reporte_ahorro_compras',
                            'queryparams' => $filtrosQuery,
                            'variant' => 'compact',
                        ])
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="tabla-ancha-grilla" data-tabla-ancha>
                        <div class="tabla-ancha-scroll-top" hidden>
                            <div class="tabla-ancha-scroll-top-inner"></div>
                        </div>
                        <div class="tabla-ancha-wrap">
                            <table class="table table-sm table-bordered mb-0 tabla-ancha" id="tabla-paginada">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>OC</th>
                                        <th>Fecha</th>
                                        <th>Req.</th>
                                        <th>Proveedor</th>
                                        <th>Art&iacute;culo</th>
                                        <th>C. costo</th>
                                        <th>Comprador</th>
                                        <th class="text-right">Cant.</th>
                                        <th class="text-right">Precio ant.</th>
                                        <th class="text-right">Precio nuevo</th>
                                        <th>Mon.</th>
                                        <th class="text-right">Ahorro $</th>
                                        <th class="text-right">% l&iacute;nea</th>
                                        <th>Tipo de ahorro</th>
                                        <th>Origen</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @forelse ($filas as $f)
                                        @php
                                            $ahorroPesos = (float) ($f->ahorro_total_pesos ?? 0);
                                            $porc = $f->porc_ahorro;
                                        @endphp
                                        <tr>
                                            <td>{{ $f->numeroordencompra ?: $f->ordencompra_id }}</td>
                                            <td>{{ $f->fecha ? date('d/m/Y', strtotime($f->fecha)) : '' }}</td>
                                            <td>{{ $f->numerorequisicion }}</td>
                                            <td>{{ trim(($f->proveedor_codigo ?? '').' '.($f->proveedor_nombre ?? '')) }}</td>
                                            <td>{{ $f->sku }} {{ $f->articulo_descripcion }}</td>
                                            <td>{{ $f->centrocosto_codigo }}</td>
                                            <td>{{ $f->comprador_nombre }}</td>
                                            <td class="text-right">{{ $fmt($f->cantidad) }}</td>
                                            <td class="text-right">{{ $fmt($f->preciooriginal) }}</td>
                                            <td class="text-right">{{ $fmt($f->precio) }}</td>
                                            <td>{{ $f->moneda_abrev }}</td>
                                            <td class="text-right {{ $ahorroPesos < 0 ? 'text-danger' : '' }}">{{ $fmt($ahorroPesos) }}</td>
                                            <td class="text-right {{ $porc !== null && (float) $porc < 0 ? 'text-danger' : '' }}">
                                                {{ $porc !== null ? $fmt($porc).' %' : '—' }}
                                            </td>
                                            <td>{{ $f->tipoahorro_nombre }}</td>
                                            <td>{{ \App\Services\Compras\AhorroReporteService::origenTexto($f->origen_anterior) }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="15" class="text-center text-muted">No hay l&iacute;neas con ahorro en el per&iacute;odo.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                @if ($filas->hasPages())
                    <div class="card-footer d-flex justify-content-between align-items-center">
                        <small class="text-muted">{{ $filas->firstItem() }}–{{ $filas->lastItem() }} de {{ $filas->total() }}</small>
                        {{ $filas->links() }}
                    </div>
                @endif
            </div>
        @endif
    </div>
</div>
@include('includes.compras.modalconsultaproveedor')
@include('includes.contable.modalconsultacentrocosto')
@include('includes.stock.modalconsultausoarticulo')
<script>
(function () {
    var tipos = @json($tiposAhorro);
    document.addEventListener('DOMContentLoaded', function () {
        var $ = window.jQuery;
        if (!$) { return; }
        $('#ahorro-tipo-lupa').on('click', function () {
            var $l = $('#ahorro-tipo-lista').empty().removeClass('d-none');
            if (!tipos.length) { $l.append('<div class="list-group-item small text-muted">No hay tipos de ahorro activos.</div>'); }
            tipos.forEach(function (t) {
                $('<button type="button" class="list-group-item list-group-item-action py-1"></button>')
                    .text(t.nombre).attr('data-id', t.id).appendTo($l);
            });
        });
        $('#ahorro-tipo-lista').on('click', '[data-id]', function () {
            $('#ahorro-tipo-id').val($(this).data('id'));
            $('#ahorro-tipo-nombre').val($(this).text());
            $('#ahorro-tipo-lista').addClass('d-none').empty();
        });
        $('#ahorro-tipo-quitar').on('click', function () {
            $('#ahorro-tipo-id').val('');
            $('#ahorro-tipo-nombre').val('');
        });
    });
})();
</script>
@endsection
