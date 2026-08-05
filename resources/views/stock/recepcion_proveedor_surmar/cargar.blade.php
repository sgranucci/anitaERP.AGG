@extends("theme.$theme.layout")
@section('titulo')
Recepción Surmar Nº {{ $recepcion->numerorecepcion }}
@endsection

@section('scripts')
<style>
    .surmar-wb thead th { background:#85C1E9; color:#17202A; }
    .surmar-wb .hora-piqueo { font-variant-numeric: tabular-nums; font-weight: 600; color: #1B4F72; }
    .surmar-wb-sticky {
        position: sticky; bottom: 0; z-index: 20;
        background: #fff; border-top: 2px solid #85C1E9;
        box-shadow: 0 -4px 12px rgba(23,32,42,.1); padding: .75rem 1rem;
    }
    .surmar-estado-vivo { font-size: .85rem; }
</style>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script>
window.SURMAR_RECEPCION = {
    id: {{ (int) $recepcion->id }},
    editable: @json((bool) $editable),
    lineas: @json($lineas),
    urls: {
        guardarLinea: @json(route('api_guardar_linea_recepcion_proveedor_surmar', $recepcion->id)),
        eliminarLinea: @json(url('stock/recepcion-proveedor-surmar/'.$recepcion->id.'/linea')),
        zpl: @json(url('stock/etiqueta-surmar')),
        token: @json(csrf_token()),
        carpetaBase: @json(rtrim(config('app.app_carpeta') ?: '', '/'))
    }
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor_surmar/cargar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor_surmar/cargar.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-barcode"></i>
                    Recepción Surmar Nº {{ $recepcion->numerorecepcion }}
                    @if ($recepcion->estado === 'BORRADOR')
                        <span class="badge badge-warning ml-2">Provisorio</span>
                    @elseif ($recepcion->estado === 'CONFIRMADA')
                        <span class="badge badge-success ml-2">Confirmada</span>
                    @else
                        <span class="badge badge-secondary ml-2">{{ $recepcion->estado }}</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('recepcion_proveedor_surmar') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Listado</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Fecha:</strong> {{ optional($recepcion->fecha)->format('d/m/Y') }}</div>
                    <div class="col-md-4"><strong>Proveedor:</strong> {{ $recepcion->proveedores->nombre ?? '—' }}</div>
                    <div class="col-md-3"><strong>Depósito:</strong> {{ $recepcion->depositos->nombre ?? $recepcion->depositos->descripcion ?? '—' }}</div>
                    <div class="col-md-2 surmar-estado-vivo text-right">
                        <span id="surmar-msg-vivo" class="text-muted">Listo para picar</span>
                    </div>
                </div>

                @if ($editable)
                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2"><strong>Nuevo ítem</strong> — al aceptar se graba al instante y se emite etiqueta</div>
                    <div class="card-body">
                        <input type="hidden" id="empresa_id" value="{{ $empresa_id }}">
                        <div class="form-row">
                            <div class="form-group col-md-4">
                                <label class="control-label">Artículo</label>
                                <div class="input-group input-group-sm">
                                    <input type="hidden" id="articulo_id" class="articulo_id">
                                    <input type="text" id="codigoarticulo" class="form-control codigoarticulo" placeholder="SKU" style="max-width:7rem;">
                                    <input type="text" id="descripcionarticulo" class="form-control descripcionarticulo" placeholder="Descripción" readonly>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary consultaarticulo" title="Consultar artículos (F1)"><i class="fa fa-search"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group col-md-2">
                                <label class="control-label">Lote</label>
                                <input type="text" id="lote_proveedor" class="form-control form-control-sm" maxlength="30" autocomplete="off">
                            </div>
                            <div class="form-group col-md-2">
                                <label class="control-label">Vto.</label>
                                <input type="date" id="fecha_vto" class="form-control form-control-sm">
                            </div>
                            <div class="form-group col-md-1">
                                <label class="control-label">Piezas</label>
                                <input type="number" step="0.01" id="cant_pieza" class="form-control form-control-sm" value="1">
                            </div>
                            <div class="form-group col-md-1">
                                <label class="control-label">Bruto</label>
                                <input type="number" step="0.01" id="peso_bruto" class="form-control form-control-sm">
                            </div>
                            <div class="form-group col-md-1">
                                <label class="control-label">Neto</label>
                                <input type="number" step="0.01" id="peso_neto" class="form-control form-control-sm">
                            </div>
                            <div class="form-group col-md-1 d-flex align-items-end">
                                <button type="button" id="btn-agregar-item-surmar" class="btn btn-primary btn-sm btn-block" title="Grabar ítem + etiqueta">
                                    <i class="fa fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" class="custom-control-input" id="imprimir_etiqueta" checked>
                            <label class="custom-control-label" for="imprimir_etiqueta">Imprimir etiqueta al grabar</label>
                        </div>
                    </div>
                </div>
                @endif

                <div class="table-responsive surmar-wb">
                    <table class="table table-sm table-bordered table-striped mb-0" id="tabla-lineas-surmar">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Hora piqueo</th>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th>Lote</th>
                                <th>Vto</th>
                                <th class="text-right">Piezas</th>
                                <th class="text-right">Bruto</th>
                                <th class="text-right">Neto</th>
                                <th>Etiqueta</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="surmar-wb-sticky d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <strong id="surmar-total-items">0</strong> ítems ·
                    Neto <strong id="surmar-total-neto">0.00</strong> kg
                </div>
                <div class="d-flex flex-wrap">
                    @if ($editable && can('confirmar-recepcion-proveedor-surmar', false))
                        <form action="{{ route('confirmar_recepcion_proveedor_surmar', $recepcion->id) }}" method="POST" class="d-inline mr-1"
                              onsubmit="return confirm('¿Confirmar recepción y generar stock?');">
                            @csrf
                            <button type="submit" class="btn btn-success btn-sm"><i class="fa fa-check"></i> Confirmar</button>
                        </form>
                    @endif
                    @if ($editable && can('anular-recepcion-proveedor-surmar', false))
                        <form action="{{ route('eliminar_recepcion_proveedor_surmar', $recepcion->id) }}" method="POST" class="d-inline"
                              onsubmit="return confirm('¿Eliminar borrador y etiquetas?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fa fa-trash"></i> Eliminar borrador</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'surmar-overlay',
    'tituloId' => 'surmar-overlay-titulo',
    'subtituloId' => 'surmar-overlay-subtitulo',
    'titulo' => 'Grabando ítem…',
    'subtitulo' => 'No cierre la página.',
])
@endsection
