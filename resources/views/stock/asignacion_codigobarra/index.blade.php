@extends("theme.$theme.layout")

@section('titulo')
    Asignar código de barras
@endsection

@section('styles')
<style>
    .ac-page { padding-bottom: 1rem; }
    .ac-cabecera {
        background: #fff;
        border-bottom: 1px solid #dee2e6;
        padding: 0.5rem 0 0.75rem;
        margin-bottom: 0.75rem;
    }
    .ac-cabecera label {
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.15rem;
        color: #495057;
    }
    .ac-card-actual {
        border: 1px solid #dee2e6;
        border-radius: 0.4rem;
        padding: 0.9rem 1rem;
        background: #fff;
        margin-bottom: 0.75rem;
    }
    .ac-card-actual .ac-sku {
        font-size: 0.85rem;
        color: #6c757d;
        font-family: monospace;
    }
    .ac-card-actual .ac-desc {
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1.3;
        margin: 0.25rem 0 0.4rem;
        word-break: break-word;
    }
    .ac-card-actual .ac-meta { font-size: 0.85rem; color: #6c757d; }
    .ac-progreso { font-size: 0.9rem; font-weight: 600; color: #1B4F72; }
    .ac-pickeo label {
        font-size: 0.8rem;
        font-weight: 700;
        margin-bottom: 0.2rem;
        color: #1B4F72;
    }
    .ac-pickeo input {
        font-size: 1.2rem;
        min-height: 3.1rem;
        font-weight: 600;
    }
    .ac-pickeo .btn { min-height: 3.1rem; min-width: 3.1rem; }
    .ac-lista-pendientes .ac-item {
        border-bottom: 1px solid #eee;
        padding: 0.45rem 0;
        font-size: 0.9rem;
    }
    .ac-lista-pendientes .ac-item.ac-actual {
        background: #eaf6f8;
        margin: 0 -0.5rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    .ac-vacio { text-align: center; color: #6c757d; padding: 2rem 1rem; }
    .tm-camara-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1055;
        background: #111;
        flex-direction: column;
        color: #fff;
    }
    .tm-camara-overlay.tm-camara-visible { display: flex; }
    .tm-camara-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.6rem 0.75rem;
        background: #1a1a1a;
    }
    .tm-camara-reader-wrap {
        flex: 1 1 auto;
        min-height: 220px;
        position: relative;
        overflow: hidden;
        background: #000;
    }
    .tm-camara-reader {
        width: 100%;
        height: 100%;
        min-height: 220px;
    }
    .tm-camara-reader video,
    .tm-camara-reader canvas {
        width: 100% !important;
        height: 100% !important;
        object-fit: cover;
    }
    .tm-camara-mira {
        position: absolute;
        left: 6%;
        right: 6%;
        top: 34%;
        height: 22%;
        border: 2px solid #4caf50;
        border-radius: 10px;
        pointer-events: none;
        box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.28);
        z-index: 2;
    }
    .tm-camara-footer { padding: 0.75rem; background: #1a1a1a; }
    .tm-camara-footer .btn { min-height: 2.75rem; }
    #ac_camara_preview {
        width: 100%;
        max-height: 40vh;
        object-fit: contain;
        background: #000;
        display: none;
    }
    #ac_camara_preview.tm-camara-preview-visible { display: block; }
    #ac_camara_reader_foto { min-height: 1px; }
    #ac_camara_codigos {
        display: none;
        margin: 0.5rem 0 0;
        max-height: 28vh;
        overflow-y: auto;
    }
    #ac_camara_codigos.tm-camara-codigos-visible { display: block; }
    #ac_camara_codigos button {
        display: block;
        width: 100%;
        margin-bottom: 0.35rem;
        text-align: left;
        font-family: monospace;
    }
    @media (min-width: 768px) {
        .ac-page-inner { max-width: 720px; margin: 0 auto; }
    }
</style>
@endsection

@section('scripts')
<script>
    window.AC_URLS = {
        pendientes: @json(urlAppDesdeRoute('asignacion_codigobarra_pendientes')),
        proveedores: @json(urlAppDesdeRoute('asignacion_codigobarra_proveedores')),
        guardar: @json(urlAppDesdeRoute('asignacion_codigobarra_guardar')),
        decodificarFoto: @json(urlAppDesdeRoute('asignacion_codigobarra_decodificar_foto')),
    };
</script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/depmae/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/vendor/html5-qrcode.min.js') }}?v={{ @filemtime(public_path('assets/vendor/html5-qrcode.min.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/asignacion_codigobarra/index.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/asignacion_codigobarra/index.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/asignacion_codigobarra/pickeo-camara.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/asignacion_codigobarra/pickeo-camara.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="row ac-page">
    <div class="col-12 ac-page-inner">
        @include('includes.mensaje')

        <div class="card card-outline card-primary mb-2">
            <div class="card-header py-2">
                <h3 class="card-title mb-0" style="font-size: 1.1rem;">
                    Asignar c&oacute;digo de barras
                </h3>
            </div>
            <div class="card-body py-2">
                <div class="ac-cabecera">
                    @include('stock.partials.campo_consulta_deposito', [
                        'prefix' => 'ac',
                        'label' => 'Depósito',
                        'depositoId' => '',
                        'codigo' => '',
                        'descripcion' => '',
                        'required' => true,
                        'inputName' => 'deposito_id',
                        'inputId' => 'deposito_ac_id',
                    ])
                    <button type="button" id="ac_btn_cargar" class="btn btn-info btn-block mt-2">
                        <i class="fa fa-refresh"></i> Cargar art&iacute;culos sin c&oacute;digo de barras
                    </button>
                </div>

                <div id="ac_panel_trabajo" style="display:none;">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span id="ac_progreso" class="ac-progreso"></span>
                        <button type="button" id="ac_btn_saltar" class="btn btn-sm btn-outline-secondary">
                            Siguiente sin grabar
                        </button>
                    </div>
                    <div id="ac_resumen_carga" class="alert alert-info py-2 mb-2" style="display:none;"></div>

                    <div class="ac-card-actual" id="ac_card_actual">
                        <div class="ac-sku" id="ac_actual_sku"></div>
                        <div class="ac-desc" id="ac_actual_desc"></div>
                        <div class="ac-meta">
                            Saldo: <strong id="ac_actual_saldo"></strong>
                            <span id="ac_actual_proveedor_wrap" class="d-block mt-1"></span>
                        </div>
                    </div>

                    <div class="ac-pickeo mb-3">
                        <label for="ac_pickeo_codigo">Le&eacute; el c&oacute;digo de barras</label>
                        <div class="d-flex align-items-stretch" style="gap: 0.4rem;">
                            <button type="button" id="ac_btn_camara" class="btn btn-success flex-shrink-0" title="Cámara">
                                <i class="fa fa-camera"></i>
                                <span class="d-none d-sm-inline"> Cámara</span>
                            </button>
                            <input type="file" id="ac_camara_foto" accept="image/*" capture="environment"
                                class="d-none" tabindex="-1">
                            <input type="text" id="ac_pickeo_codigo" class="form-control" autocomplete="off"
                                autocorrect="off" autocapitalize="off" spellcheck="false"
                                inputmode="numeric" placeholder="Código de barras">
                            <button type="button" id="ac_btn_guardar" class="btn btn-primary flex-shrink-0">
                                Guardar
                            </button>
                        </div>
                        <small class="text-muted d-block mt-1">
                            C&aacute;mara en vivo (HTTPS). Si no hay v&iacute;nculo proveedor, te pedir&aacute; elegirlo al grabar.
                        </small>
                    </div>

                    <div id="ac_estado" class="text-muted small mb-2"></div>

                    <details class="mb-2">
                        <summary class="text-muted" style="cursor:pointer;">Ver cola pendiente</summary>
                        <div id="ac_lista_pendientes" class="ac-lista-pendientes mt-2"></div>
                    </details>
                </div>

                <div id="ac_vacio" class="ac-vacio" style="display:none;">
                    No hay art&iacute;culos con saldo y sin c&oacute;digo de barras en este dep&oacute;sito.
                </div>
            </div>
        </div>
    </div>
</div>

<div id="ac_camara_overlay" class="tm-camara-overlay" aria-modal="true" aria-labelledby="ac_camara_titulo">
    <div class="tm-camara-toolbar">
        <strong id="ac_camara_titulo">Leer c&oacute;digo de barras</strong>
        <button type="button" id="ac_camara_cerrar" class="btn btn-outline-light btn-sm">Cerrar</button>
    </div>
    <div class="tm-camara-reader-wrap">
        <div id="ac_camara_reader" class="tm-camara-reader"></div>
        <div id="ac_camara_mira" class="tm-camara-mira d-none" aria-hidden="true"></div>
    </div>
    <img id="ac_camara_preview" alt="Foto del código">
    <div id="ac_camara_reader_foto"></div>
    <div class="tm-camara-footer">
        <div id="ac_camara_feedback" class="small mb-2">Apunt&aacute; al c&oacute;digo de barras…</div>
        <div id="ac_camara_codigos"></div>
        <div id="ac_camara_foto_wrap" class="d-none">
            <button type="button" id="ac_btn_otra_foto" class="btn btn-warning btn-block">
                <i class="fa fa-camera"></i> Sacar foto
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="ac_modal_proveedor" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header py-2">
                <h5 class="modal-title">Elegir proveedor</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="small text-muted mb-2">
                    Este art&iacute;culo no tiene v&iacute;nculo en art&iacute;culo proveedor. Eleg&iacute; uno para grabar el c&oacute;digo.
                </p>
                <input type="search" id="ac_prov_buscar" class="form-control mb-2" placeholder="Buscar proveedor…" autocomplete="off">
                <select id="ac_prov_select" class="form-control" size="8"></select>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" id="ac_prov_confirmar" class="btn btn-primary">Confirmar y guardar</button>
            </div>
        </div>
    </div>
</div>

@include('includes.stock.modalconsultadeposito')
@endsection
