@extends("theme.$theme.layout")

@section('titulo')
    Transferencia de mercadería
@endsection

@section('styles')
<style>
    .tm-page {
        padding-bottom: 5.5rem;
    }
    .tm-cabecera {
        position: sticky;
        top: 0;
        z-index: 1020;
        background: #fff;
        border-bottom: 1px solid #dee2e6;
        padding: 0.5rem 0;
        margin: 0 -0.5rem 0.75rem;
        padding-left: 0.5rem;
        padding-right: 0.5rem;
    }
    .tm-cabecera label {
        font-size: 0.75rem;
        font-weight: 600;
        margin-bottom: 0.15rem;
        color: #495057;
    }
    .tm-cabecera .form-control,
    .tm-cabecera .btn {
        font-size: 1rem;
        min-height: 2.75rem;
    }
    .tm-deposito-campo .form-control {
        font-size: 1rem;
        min-height: 2.75rem;
    }
    .tm-deposito-campo .btn {
        min-height: 2.75rem;
        min-width: 2.75rem;
    }
    .tm-filtro {
        position: sticky;
        top: 0;
        z-index: 1015;
        background: #f8f9fa;
        padding: 0.5rem 0;
        margin-bottom: 0.5rem;
    }
    .tm-filtro input {
        font-size: 1.05rem;
        min-height: 2.75rem;
    }
    .tm-item {
        border: 1px solid #dee2e6;
        border-radius: 0.35rem;
        padding: 0.6rem 0.75rem;
        margin-bottom: 0.5rem;
        background: #fff;
    }
    .tm-item.tm-sin-erp {
        border-left: 4px solid #dc3545;
        opacity: 0.85;
    }
    .tm-item .tm-desc {
        font-size: 0.95rem;
        font-weight: 600;
        line-height: 1.25;
        margin-bottom: 0.25rem;
        word-break: break-word;
    }
    .tm-item .tm-meta {
        font-size: 0.8rem;
        color: #6c757d;
    }
    .tm-item .tm-saldo {
        font-size: 1.1rem;
        font-weight: 700;
        color: #17a2b8;
    }
    .tm-item input.tm-cant {
        font-size: 1.25rem;
        font-weight: 700;
        text-align: center;
        min-height: 2.75rem;
        max-width: 6rem;
    }
    .tm-barra {
        position: fixed;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 1030;
        background: #fff;
        border-top: 1px solid #dee2e6;
        padding: 0.6rem 0.75rem;
        box-shadow: 0 -2px 8px rgba(0,0,0,0.08);
    }
    .tm-barra .btn-transferir {
        font-size: 1.1rem;
        font-weight: 700;
        min-height: 3rem;
        width: 100%;
    }
    .tm-vacio {
        text-align: center;
        color: #6c757d;
        padding: 2rem 1rem;
    }
    .tm-alerta-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 1060;
        background: rgba(0, 0, 0, 0.45);
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .tm-alerta-overlay.tm-alerta-visible {
        display: flex;
    }
    .tm-alerta-banner {
        max-width: min(92vw, 540px);
        width: 100%;
        border-radius: 0.5rem;
        box-shadow: 0 8px 32px rgba(0, 0, 0, 0.25);
    }
    .tm-alerta-banner .alert {
        margin-bottom: 0;
        border-width: 2px;
        font-size: 1rem;
    }
    .tm-alerta-banner .tm-alerta-titulo {
        font-size: 1.15rem;
        font-weight: 700;
        margin-bottom: 0.5rem;
    }
    .tm-alerta-banner .tm-alerta-texto {
        line-height: 1.45;
        white-space: pre-wrap;
        word-break: break-word;
    }
    @media (min-width: 768px) {
        .tm-lista {
            max-width: 720px;
            margin: 0 auto;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    window.TM_URLS = {
        inventario: @json(route('transferencia_mercaderia_inventario')),
        preferencias: @json(route('transferencia_mercaderia_preferencias')),
        guardar: @json(route('transferencia_mercaderia_guardar')),
        destinatarios: @json(route('transferencia_mercaderia_destinatarios')),
        validarLineaContable: @json(route('transferencia_mercaderia_validar_linea_contable')),
        saldoArticulo: @json(route('transferencia_mercaderia_saldo_articulo')),
        articuloConsultaUrl: {!! json_encode(route('editar_articulo', ['id' => '__ID__', 'origen' => 'modal_consulta', 'vista' => 'consulta'])) !!},
    };
</script>
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/transferencia_mercaderia/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<meta name="csrf-token" content="{{ csrf_token() }}">
<div class="row tm-page">
    <div class="col-12">
        @include('includes.mensaje')

        <div class="card card-outline card-primary mb-2">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0" style="font-size: 1.1rem;">
                    Transferencia de stock
                </h3>
                @if (can('listar-transferencias-pendientes', false))
                    <a href="{{ route('transferencia_mercaderia_pendientes') }}" class="btn btn-warning btn-sm">
                        Pendientes
                        @if (($pendientesCount ?? 0) > 0)
                            <span class="badge badge-light">{{ $pendientesCount }}</span>
                        @endif
                    </a>
                @endif
                @include('includes.stock.boton-manual-recepcion-movstock')
            </div>
            <div class="card-body py-2">
                <div class="tm-cabecera">
                    <div class="form-row">
                        <div class="col-12 mb-2">
                            @include('includes.form-empresa-asignada', [
                                'empresa_query' => $empresa_query,
                                'empresa_id' => $empresa_id ?? null,
                                'col_label' => 'col-12',
                                'col_input' => 'col-12',
                            ])
                        </div>
                        @include('stock.partials.campo_consulta_deposito', [
                            'prefix' => 'salida',
                            'label' => 'Depósito salida',
                            'depositoId' => optional($depSalida)->id ?? '',
                            'codigo' => optional($depSalida)->codigo ?? '',
                            'descripcion' => optional($depSalida)->nombre ?? '',
                            'required' => false,
                        ])
                        <div class="form-group col-12 mb-2" id="tm_panel_bien_origen" style="display:none;">
                            <label for="bien_uso_origen_id">Bien de uso origen</label>
                            <select id="bien_uso_origen_id" class="form-control" data-placeholder="Bien de uso">
                                <option value="">— Seleccionar bien —</option>
                                @foreach ($bienesUsoActivos as $bien)
                                    <option value="{{ $bien->id }}"
                                        @if ((int) ($defaults['bien_uso_origen_id'] ?? optional($bienUsoOrigen)->id) === (int) $bien->id) selected @endif>
                                        {{ $bien->etiqueta() }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Se desasigna stock del bien y se ingresa al dep&oacute;sito destino al confirmar.</small>
                        </div>
                        @include('stock.partials.campo_consulta_deposito', [
                            'prefix' => 'entrada',
                            'label' => 'Depósito entrada',
                            'depositoId' => optional($depEntrada)->id ?? '',
                            'codigo' => optional($depEntrada)->codigo ?? '',
                            'descripcion' => optional($depEntrada)->nombre ?? '',
                        ])
                        <div class="form-group col-12 mb-2" id="tm_panel_bien_destino" style="display:none;">
                            <label for="bien_uso_destino_id">Bien de uso destino</label>
                            <select id="bien_uso_destino_id" class="form-control" data-placeholder="Bien de uso">
                                <option value="">— Seleccionar bien —</option>
                                @foreach ($bienesUsoActivos as $bien)
                                    <option value="{{ $bien->id }}"
                                        @if ((int) ($defaults['bien_uso_destino_id'] ?? optional($bienUsoDestino)->id) === (int) $bien->id) selected @endif>
                                        {{ $bien->etiqueta() }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">El movimiento de entrada quedar&aacute; asociado a este bien (sin dep&oacute;sito destino).</small>
                        </div>
                        <div class="form-group col-12 mb-2">
                            <label for="tipotransaccion_stock_id">Tipo de transacción</label>
                            <select id="tipotransaccion_stock_id" class="form-control" required>
                                <option value="">— Seleccionar —</option>
                                @foreach ($tipotransacciones as $t)
                                    <option value="{{ $t->id }}"
                                        data-requiere-aprobacion="{{ $t->requiere_aprobacion ? '1' : '0' }}"
                                        data-destino-bien-uso="{{ $t->destino_bien_uso ? '1' : '0' }}"
                                        data-origen-bien-uso="{{ $t->origen_bien_uso ? '1' : '0' }}"
                                        data-maneja-contabilidad="{{ $t->maneja_contabilidad ? '1' : '0' }}"
                                        @if ((int) ($defaults['tipotransaccion_stock_id'] ?? 0) === (int) $t->id) selected @endif>
                                        {{ $t->nombre }}
                                        @if ($t->origen_bien_uso)
                                            (origen: bien de uso)
                                        @endif
                                        @if ($t->destino_bien_uso)
                                            (destino: bien de uso)
                                        @endif
                                        @if ($t->requiere_aprobacion)
                                            (requiere aprobación)
                                        @endif
                                        @if ($t->maneja_contabilidad)
                                            (contabilidad)
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-12 mb-2" id="tm_panel_centrocosto" style="display:none;">
                            <label for="centrocosto_destino_id">Centro de costo destino</label>
                            <select id="centrocosto_destino_id" class="form-control">
                                <option value="">— Seleccionar —</option>
                                @foreach ($centrocosto_query as $cc)
                                    <option value="{{ $cc->id }}"
                                        @if ((int) ($defaults['centrocosto_destino_id'] ?? auth()->user()->centrocosto_id ?? 0) === (int) $cc->id) selected @endif>
                                        {{ $cc->codigo }} — {{ $cc->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Requerido para tipos de transferencia que generan asiento contable.</small>
                        </div>
                        <div class="form-group col-12 mb-2" id="tm_panel_destinatario" style="display:none;">
                            <label for="usuario_destino_id">Usuario que recibe / aprueba</label>
                            <select id="usuario_destino_id" class="form-control">
                                <option value="">— Encargado del depósito destino —</option>
                            </select>
                            <small class="text-muted" id="tm_destinatario_ayuda">Por defecto se usa el administrador principal del depósito de entrada.</small>
                        </div>
                        <div class="form-group col-12 mb-2">
                            <button type="button" id="tm_btn_agregar_articulo" class="btn btn-outline-primary btn-block">
                                <i class="fa fa-search"></i> Agregar artículo (modal)
                            </button>
                        </div>
                        <div class="form-group col-12 mb-0">
                            <button type="button" id="tm_btn_cargar" class="btn btn-info btn-block">
                                <i class="fa fa-refresh"></i> Cargar stock del depósito de salida
                            </button>
                        </div>
                    </div>
                </div>

                <div class="tm-filtro" id="tm_panel_filtro" style="display: none;">
                    <input type="search" id="tm_filtro_desc" class="form-control"
                        placeholder="SKU o descripción…" autocomplete="off">
                </div>

                <div id="tm_estado" class="text-muted small mb-2"></div>
                <div id="tm_lista" class="tm-lista"></div>
            </div>
        </div>
    </div>
</div>

<div id="tm_alerta_overlay" class="tm-alerta-overlay" role="alertdialog" aria-modal="true" aria-labelledby="tm_alerta_titulo">
    <div class="tm-alerta-banner">
        <div class="alert alert-danger mb-0">
            <div id="tm_alerta_titulo" class="tm-alerta-titulo">No se pudo completar la operación</div>
            <div id="tm_alerta_texto" class="tm-alerta-texto"></div>
            <button type="button" id="tm_alerta_cerrar" class="btn btn-danger btn-block mt-3">
                Entendido
            </button>
        </div>
    </div>
</div>

<div class="tm-barra">
    <button type="button" id="tm_btn_transferir" class="btn btn-success btn-transferir" disabled>
        Transferir (0)
    </button>
</div>

@include('includes.stock.modalconsultadeposito')
@include('includes.stock.modalconsultaarticulo')
@endsection
