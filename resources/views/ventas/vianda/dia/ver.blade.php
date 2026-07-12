@extends("theme.$theme.layout")
@section('titulo')
    Vianda {{ $consumo->codigo_retiro }}
@endsection

@section('scripts')
<script>
window.VIANDA_DIA = {
    csrf: @json(csrf_token()),
};
</script>
@if ($puede_ver_formula ?? false)
<script>
    window.FORMULA_ARTICULO_ACCION = {
        urlResolverFormulaBase: @json(url('stock/formula-articulo/resolver-por-articulo')),
        urlFormulaBase: @json(url('stock/formula-articulo')),
        puedeVerFormula: true,
    };
</script>
<script src="{{ asset('assets/pages/scripts/includes/formula_articulo_accion.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/includes/formula_articulo_accion.js')) ?: time() }}" type="text/javascript"></script>
@endif
<script src="{{ asset('assets/pages/scripts/ventas/vianda/dia.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    $borrada = $consumo->estado === 'N';
    $volverUrl = $volver_url ?? route('viandas_dia_gastronomia', $filtrosQuery ?? []);
    $cardClass = $borrada ? 'card-danger' : 'card-info';
    $mostrarAccionesArticulo = $mostrar_acciones_articulo ?? false;
    $colSpanLineas = $mostrarAccionesArticulo ? 10 : 9;
    $colSpanMovimientos = $mostrarAccionesArticulo ? 6 : 5;
    $movimientosStock = $consumo->movimientos ?? collect();
    $reporteQuery = array_filter([
        'consultar' => 1,
        'fecha_desde' => optional($consumo->fecha)->format('Y-m-d'),
        'fecha_hasta' => optional($consumo->fecha)->format('Y-m-d'),
        'empresa_id' => $consumo->empresa_id ?: null,
        'centrocosto_id' => $consumo->centrocosto_id ?: null,
        'estado' => $consumo->estado !== 'A' ? $consumo->estado : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
@include('ventas.vianda.dia.partials.estilos_acciones_tabla')
<div class="row">
    <div class="col-lg-10 offset-lg-1">
        @include('includes.mensaje')
        <div class="card {{ $cardClass }}">
            <div class="card-header vianda-dia-card-header">
                <h3 class="card-title mb-0">
                    <i class="fa fa-utensils mr-1"></i> Vianda {{ $consumo->codigo_retiro }}
                    @if ($borrada)
                        <span class="badge badge-danger ml-2">Borrada</span>
                    @else
                        <span class="badge badge-success ml-2">Activa</span>
                    @endif
                </h3>
                <div class="vianda-dia-header-acciones">
                    @if (can('listar-reporte-vianda-gastronomia', false))
                        <a href="{{ route('consultar_reporte_vianda_gastronomia', $reporteQuery) }}"
                            class="btn btn-sm btn-vianda-header" title="Reporte por período y exportación">
                            <i class="fa fa-chart-bar"></i> Reporte
                        </a>
                    @endif
                    <a href="{{ $volverUrl }}" class="btn btn-sm btn-vianda-header">
                        <i class="fa fa-arrow-left"></i> Volver
                    </a>
                    @if (! $borrada)
                        <button type="button" class="btn btn-sm btn-vianda-header btn-vianda-header-solid js-vianda-reimprimir"
                            data-url="{{ route('viandas_dia_reimprimir', $consumo->id) }}"
                            data-codigo="{{ $consumo->codigo_retiro }}">
                            <i class="fa fa-print"></i> Reimprimir
                        </button>
                        @if ($puede_borrar)
                            <button type="button" class="btn btn-sm btn-vianda-header js-vianda-borrar"
                                data-url="{{ route('viandas_dia_borrar', $consumo->id) }}"
                                data-codigo="{{ $consumo->codigo_retiro }}"
                                data-redirect="{{ $volverUrl }}">
                                <i class="fa fa-trash text-danger"></i> Borrar
                            </button>
                        @endif
                    @endif
                </div>
            </div>

            <div class="card-body">
                @if ($borrada)
                    <div class="alert alert-danger py-2">
                        <strong>Vianda borrada</strong>
                        @if ($consumo->anulado_at)
                            el {{ $consumo->anulado_at->format('d/m/Y H:i') }}
                        @endif
                        @if ($consumo->anuladoPor)
                            por {{ $consumo->anuladoPor->nombre ?? $consumo->anuladoPor->name ?? ('usuario '.$consumo->anulado_usuario_id) }}
                        @endif
                        . El stock descargado fue devuelto.
                        @if ($consumo->anulado_motivo)
                            <div class="mt-1">Motivo: {{ $consumo->anulado_motivo }}</div>
                        @endif
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-2">
                            <tr><th style="width:150px;">Código de retiro</th><td><strong>{{ $consumo->codigo_retiro }}</strong></td></tr>
                            <tr><th>Fecha</th><td>{{ optional($consumo->fecha)->format('d/m/Y') }} {{ $consumo->hora }}</td></tr>
                            <tr><th>Jornada</th><td>{{ optional($consumo->fecha_jornada)->format('d/m/Y') ?: '—' }}</td></tr>
                            <tr><th>Empresa</th><td>{{ optional($consumo->empresa)->nombre }}</td></tr>
                            <tr><th>Terminal</th><td>{{ optional($consumo->terminal)->descripcion ?: '—' }}</td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless mb-2">
                            <tr>
                                <th style="width:150px;">Empleado</th>
                                <td>
                                    @if (($puede_ver_empleado ?? false) && (int) ($consumo->vianda_usuario_id ?? 0) > 0)
                                        <a href="{{ route('editar_vianda_usuario_gastronomia', ['id' => $consumo->vianda_usuario_id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-primary"
                                            title="Consultar empleado">
                                            {{ trim($consumo->login_usuario.' - '.$consumo->nombre_usuario, ' -') }}
                                        </a>
                                    @else
                                        {{ trim($consumo->login_usuario.' - '.$consumo->nombre_usuario, ' -') }}
                                    @endif
                                </td>
                            </tr>
                            <tr><th>Centro de costo</th><td>{{ optional($consumo->centrocosto)->nombre ?: '—' }}</td></tr>
                            <tr><th>Tipo de menú</th><td>{{ optional($consumo->tipoMenu)->nombre ?: '—' }}</td></tr>
                            <tr><th>Operador</th><td>{{ optional($consumo->operador)->nombre ?? optional($consumo->operador)->name ?? '—' }}</td></tr>
                            <tr><th>Ítems</th><td>{{ (int) $consumo->cantidad_items }} · Costo {{ number_format((float) $consumo->total_costo, 2, ',', '.') }} · Venta {{ number_format((float) $consumo->total_venta, 2, ',', '.') }}</td></tr>
                        </table>
                    </div>
                </div>

                <div class="card card-outline card-primary mb-0">
                    <div class="card-header p-0 border-bottom-0">
                        <ul class="nav nav-tabs" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#tab-vianda-lineas" role="tab">
                                    &Iacute;tems de la vianda
                                    @if ($consumo->lineas->isNotEmpty())
                                        <span class="badge badge-secondary">{{ $consumo->lineas->count() }}</span>
                                    @endif
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-vianda-movimientos" role="tab">
                                    Movimientos de stock
                                    @if ($movimientosStock->isNotEmpty())
                                        <span class="badge badge-secondary">{{ $movimientosStock->count() }}</span>
                                    @endif
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body tab-content p-0">
                        <div class="tab-pane fade show active" id="tab-vianda-lineas" role="tabpanel">
                            <p class="small text-muted px-3 pt-3 mb-2">Platos e insumos registrados en la comanda de vianda.</p>
                            <div class="table-responsive">
                                <table id="tabla-lineas" class="table table-striped table-bordered table-sm mb-0" style="font-size: 0.85rem;">
                                    <thead>
                                        <tr>
                                            <th style="width:40px;">#</th>
                                            <th>SKU</th>
                                            <th>Descripci&oacute;n</th>
                                            <th>Tipo</th>
                                            <th class="text-right">Cantidad</th>
                                            <th class="text-right">Costo unit.</th>
                                            <th class="text-right">Venta unit.</th>
                                            <th class="text-right">Subtotal costo</th>
                                            <th class="text-right">Subtotal venta</th>
                                            @if ($mostrarAccionesArticulo)
                                                <th class="text-nowrap vianda-ver-tabla-acciones" style="width:7rem;">Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($consumo->lineas as $linea)
                                            @php
                                                $cant = (float) $linea->cantidad;
                                                $precioCosto = (float) $linea->precio_costo_unitario;
                                                $precioVenta = (float) $linea->precio_venta_unitario;
                                                $articuloIdLinea = (int) ($linea->articulo_id ?? 0);
                                            @endphp
                                            <tr>
                                                <td>{{ $linea->orden }}</td>
                                                <td>
                                                    @include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', [
                                                        'sku' => $linea->sku,
                                                        'articuloId' => $articuloIdLinea,
                                                    ])
                                                </td>
                                                <td>
                                                    {{ $linea->descripcion }}
                                                    @if (trim((string) $linea->comentario) !== '')
                                                        <div class="small text-info"><i class="fa fa-comment"></i> {{ $linea->comentario }}</div>
                                                    @endif
                                                </td>
                                                <td>{{ $linea->tipoarticulo_nombre }}</td>
                                                <td class="text-right">{{ rtrim(rtrim(number_format($cant, 2, ',', '.'), '0'), ',') }}</td>
                                                <td class="text-right">{{ number_format($precioCosto, 2, ',', '.') }}</td>
                                                <td class="text-right">{{ number_format($precioVenta, 2, ',', '.') }}</td>
                                                <td class="text-right">{{ number_format($precioCosto * $cant, 2, ',', '.') }}</td>
                                                <td class="text-right">{{ number_format($precioVenta * $cant, 2, ',', '.') }}</td>
                                                @if ($mostrarAccionesArticulo)
                                                    <td class="text-nowrap vianda-ver-tabla-acciones">
                                                        @include('ventas.vianda.dia.partials.acciones_articulo_linea', [
                                                            'articuloId' => $articuloIdLinea,
                                                            'depositoId' => $deposito_platos_id ?? 0,
                                                            'volverUrl' => $volverUrl,
                                                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                                            'puede_ver_formula' => $puede_ver_formula ?? false,
                                                            'puede_ver_movimientos' => $puede_ver_movimientos ?? false,
                                                        ])
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr><td colspan="{{ $colSpanLineas }}" class="text-center text-muted py-3">Sin &iacute;tems.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="tab-pane fade" id="tab-vianda-movimientos" role="tabpanel">
                            <p class="small text-muted px-3 pt-3 mb-2">
                                Movimientos de stock generados al marchar la vianda. Cantidad negativa = salida del dep&oacute;sito.
                            </p>
                            <div class="table-responsive">
                                <table id="tabla-movimientos-vianda" class="table table-striped table-bordered table-sm mb-0" style="font-size: 0.85rem;">
                                    <thead>
                                        <tr>
                                            <th>Dep&oacute;sito</th>
                                            <th>SKU</th>
                                            <th>Art&iacute;culo</th>
                                            <th class="text-right">Entrada</th>
                                            <th class="text-right">Salida</th>
                                            @if ($mostrarAccionesArticulo)
                                                <th class="text-nowrap vianda-ver-tabla-acciones" style="width:7rem;">Acciones</th>
                                            @endif
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @forelse ($movimientosStock as $mov)
                                            @php
                                                $cantMov = (float) ($mov->cantidad ?? 0);
                                                $entrada = $cantMov > 0 ? $cantMov : null;
                                                $salida = $cantMov < 0 ? abs($cantMov) : null;
                                                $articuloIdMov = (int) ($mov->articulo_id ?? 0);
                                            @endphp
                                            <tr>
                                                <td>
                                                    @if ($mov->depositos)
                                                        {{ $mov->depositos->codigo }} — {{ $mov->depositos->nombre }}
                                                    @else
                                                        —
                                                    @endif
                                                </td>
                                                <td>
                                                    @include('ventas.gastronomia.facturas_dia.partials.link_sku_articulo', [
                                                        'sku' => $mov->articulos->sku ?? '—',
                                                        'articuloId' => $articuloIdMov,
                                                    ])
                                                </td>
                                                <td>{{ $mov->articulos->descripcion ?? '—' }}</td>
                                                <td class="text-right">
                                                    @if ($entrada !== null)
                                                        {{ rtrim(rtrim(number_format($entrada, 3, ',', '.'), '0'), ',') }}
                                                    @endif
                                                </td>
                                                <td class="text-right">
                                                    @if ($salida !== null)
                                                        {{ rtrim(rtrim(number_format($salida, 3, ',', '.'), '0'), ',') }}
                                                    @endif
                                                </td>
                                                @if ($mostrarAccionesArticulo)
                                                    <td class="text-nowrap vianda-ver-tabla-acciones">
                                                        @include('ventas.vianda.dia.partials.acciones_articulo_linea', [
                                                            'articuloId' => $articuloIdMov,
                                                            'depositoId' => (int) ($mov->deposito_id ?? 0),
                                                            'volverUrl' => $volverUrl,
                                                            'puede_ver_articulo' => $puede_ver_articulo ?? false,
                                                            'puede_ver_formula' => $puede_ver_formula ?? false,
                                                            'puede_ver_movimientos' => $puede_ver_movimientos ?? false,
                                                        ])
                                                    </td>
                                                @endif
                                            </tr>
                                        @empty
                                            <tr><td colspan="{{ $colSpanMovimientos }}" class="text-center text-muted py-3">Sin movimientos de stock.</td></tr>
                                        @endforelse
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                @if (trim((string) $consumo->observacion) !== '')
                    <div class="mt-3">
                        <label class="text-muted small mb-1">Observación de comanda</label>
                        <div class="border rounded p-2 bg-light">{{ $consumo->observacion }}</div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Modal borrar vianda --}}
<div class="modal fade" id="modal-vianda-borrar" tabindex="-1" role="dialog" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title mb-0"><i class="fa fa-trash mr-1"></i> Borrar vianda</h5>
                <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="mb-2">
                    Va a borrar la vianda <strong id="vianda-borrar-codigo">—</strong>.
                    Se marcará como <strong>borrada</strong> y se devolverá el stock descargado (plato e insumos).
                </p>
                <div class="form-group mb-0">
                    <label class="mb-1" for="vianda-borrar-motivo">Motivo (opcional)</label>
                    <textarea class="form-control form-control-sm" id="vianda-borrar-motivo" rows="2" maxlength="255"
                        placeholder="Ej. carga errónea, empleado duplicado…"></textarea>
                </div>
                <div class="alert alert-danger d-none mt-2 mb-0 py-2" id="vianda-borrar-error"></div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="vianda-borrar-confirmar">
                    <i class="fa fa-trash"></i> Borrar vianda
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
