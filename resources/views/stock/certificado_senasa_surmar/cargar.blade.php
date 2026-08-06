@extends("theme.$theme.layout")
@section('titulo')
Cert. SENASA Surmar {{ $cert->etiqueta }}
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
    #lista-etiquetas-pendientes .badge { margin: 2px; }
</style>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script>
window.SURMAR_CERT_SENASA = {
    id: {{ (int) $cert->id }},
    editable: @json((bool) $editable),
    lineas: @json($lineas),
    urls: {
        guardarLinea: @json(route('api_guardar_linea_certificado_senasa_surmar', $cert->id)),
        eliminarLinea: @json(url('stock/certificado-senasa-surmar/'.$cert->id.'/linea')),
        resolverEtiqueta: @json(route('api_resolver_etiqueta_certificado_senasa_surmar')),
        token: @json(csrf_token()),
        carpetaBase: @json(rtrim(config('app.app_carpeta') ?: '', '/'))
    }
};
</script>
<script src="{{ asset('assets/pages/scripts/stock/certificado_senasa_surmar/cargar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/certificado_senasa_surmar/cargar.js')) ?: time() }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-certificate"></i>
                    Certificado SENASA {{ $cert->etiqueta }}
                    @if ($cert->estado === 'BORRADOR')
                        <span class="badge badge-warning ml-2">Provisorio</span>
                    @elseif ($cert->estado === 'CONFIRMADO')
                        <span class="badge badge-success ml-2">Confirmado</span>
                    @else
                        <span class="badge badge-secondary ml-2">{{ $cert->estado }}</span>
                    @endif
                </h3>
                <div class="card-tools">
                    <a href="{{ route('certificado_senasa_surmar') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Listado</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-3">
                    <div class="col-md-3"><strong>Fecha:</strong> {{ optional($cert->fecha)->format('d/m/Y') }}</div>
                    <div class="col-md-3"><strong>Cliente:</strong> {{ $cert->cliente->nombre ?? '—' }}</div>
                    <div class="col-md-3"><strong>Camión:</strong> {{ $cert->camion->dominio ?? '—' }}</div>
                    <div class="col-md-3"><strong>Remito AFIP:</strong> {{ $cert->cod_remito ?: '—' }}</div>
                </div>
                @if ($cert->mensaje_afip)
                    <div class="alert alert-warning py-2">{{ $cert->mensaje_afip }}</div>
                @endif

                @if ($editable)
                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-2"><strong>Nuevo ítem</strong> — picá etiquetas (ID) y aceptá para grabar</div>
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
                            <div class="form-group col-md-3">
                                <label class="control-label">Etiqueta (ID)</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" id="etiqueta_scan" class="form-control" placeholder="Escanear / ID" autocomplete="off">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary" id="btn-agregar-etiqueta"><i class="fa fa-plus"></i></button>
                                    </div>
                                </div>
                                <div id="lista-etiquetas-pendientes" class="mt-1"></div>
                            </div>
                            <div class="form-group col-md-1">
                                <label class="control-label">Kilos</label>
                                <input type="number" step="0.001" id="kilos" class="form-control form-control-sm" placeholder="auto">
                            </div>
                            <div class="form-group col-md-1">
                                <label class="control-label">Cajas</label>
                                <input type="number" step="0.001" id="cajas" class="form-control form-control-sm" placeholder="auto">
                            </div>
                            <div class="form-group col-md-1">
                                <label class="control-label">Tropa</label>
                                <input type="number" id="tropa" class="form-control form-control-sm">
                            </div>
                            <div class="form-group col-md-2 d-flex align-items-end">
                                <button type="button" class="btn btn-success btn-sm" id="btn-aceptar-linea">
                                    <i class="fa fa-check"></i> Aceptar ítem
                                </button>
                            </div>
                        </div>
                        <span id="surmar-msg-vivo" class="text-muted small">Listo para picar etiquetas</span>
                    </div>
                </div>
                @endif

                <div class="table-responsive surmar-wb">
                    <table class="table table-sm table-bordered" id="tabla-lineas-cert-senasa">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Hora</th>
                                <th>SKU</th>
                                <th>Descripción</th>
                                <th>codTipoProd</th>
                                <th class="text-right">Kilos</th>
                                <th class="text-right">Cajas</th>
                                <th>Etiquetas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div class="text-right text-muted small">
                    Ítems: <strong id="surmar-total-items">0</strong> ·
                    Kilos: <strong id="surmar-total-kilos">0.00</strong>
                </div>
            </div>
        </div>

        <div class="surmar-wb-sticky d-flex flex-wrap justify-content-between align-items-center">
            <div>
                @if ($cert->xml_path && can('listar-certificado-senasa-surmar', false))
                    <a href="{{ route('descargar_xml_certificado_senasa_surmar', $cert->id) }}" class="btn btn-outline-secondary btn-sm">
                        <i class="fa fa-download"></i> XML SENASA
                    </a>
                @endif
            </div>
            <div>
                @if ($editable && can('confirmar-certificado-senasa-surmar', false))
                    <form action="{{ route('confirmar_certificado_senasa_surmar', $cert->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Confirmar? Se enviará el remito cárnico a AFIP y se generará el XML SENASA.');">
                        @csrf
                        <button type="submit" class="btn btn-success">
                            <i class="fa fa-check-circle"></i> Confirmar (remito + XML)
                        </button>
                    </form>
                @endif
                @if ($cert->estado !== 'ANULADO' && can('anular-certificado-senasa-surmar', false))
                    <form action="{{ route('anular_certificado_senasa_surmar', $cert->id) }}" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Anular este certificado?');">
                        @csrf
                        <button type="submit" class="btn btn-outline-danger">
                            <i class="fa fa-times-circle"></i> Anular
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@include('includes.stock.modalconsultaarticulo')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'surmar-cert-overlay',
    'tituloId' => 'surmar-cert-overlay-titulo',
    'subtituloId' => 'surmar-cert-overlay-subtitulo',
    'titulo' => 'Procesando…',
    'subtitulo' => 'Espere; puede demorar según AFIP.',
])
@endsection
