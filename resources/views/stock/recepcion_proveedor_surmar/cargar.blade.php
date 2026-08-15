@extends("theme.$theme.layout")
@section('titulo')
Recepción Surmar Nº {{ $recepcion->numerorecepcion }}
@endsection

@section('scripts')
<style>
    .surmar-wb thead th { background:#85C1E9; color:#17202A; }
    .surmar-wb .hora-carga { font-variant-numeric: tabular-nums; font-weight: 600; color: #1B4F72; }
    .surmar-wb-sticky {
        position: sticky; bottom: 0; z-index: 20;
        background: #fff; border-top: 2px solid #85C1E9;
        box-shadow: 0 -4px 12px rgba(23,32,42,.1); padding: .75rem 1rem;
    }
    .surmar-estado-vivo { font-size: .85rem; }
    #tabla-oc-pendientes-surmar tbody tr.js-oc-elegida { background: #D6EAF8; }
    #tabla-oc-pendientes-surmar tbody tr { cursor: pointer; }
    /* Renglón principal de recepción (totales = suma etiquetas, solo lectura visual) */
    #tabla-items-recepcion-surmar tbody tr.surmar-item-principal td.surmar-total-derivado {
        color: #6c757d;
        font-variant-numeric: tabular-nums;
        background: #f4f6f7;
    }
    #tabla-items-recepcion-surmar tbody tr.surmar-item-principal.surmar-item-extra {
        background: #FFF8E7;
    }
    #tabla-items-recepcion-surmar tbody tr.surmar-item-etiquetas > td {
        padding: 0;
        border-top: 0;
        background: #fafafa;
    }
    #tabla-items-recepcion-surmar tbody tr.surmar-item-etiquetas.d-none { display: none !important; }
    #tabla-items-recepcion-surmar .surmar-etiquetas-inner {
        margin: 0;
        border: 0;
    }
    #tabla-items-recepcion-surmar .surmar-etiquetas-inner thead th {
        background: #D6EAF8;
        color: #17202A;
        font-weight: 600;
    }
    #tabla-items-recepcion-surmar td.text-center .btn-accion-tabla { margin: 0 1px; vertical-align: middle; }
    /* Botón etiquetas del alta: misma altura que los inputs */
    #surmar-nuevo-item-campos > .form-group { margin-bottom: 0.5rem; }
    #surmar-nuevo-item-campos .surmar-btn-etiqueta-wrap .control-label {
        display: block;
        margin-bottom: 0.25rem;
        line-height: 1.5;
        visibility: hidden;
        user-select: none;
    }
    #surmar-nuevo-item-campos .surmar-btn-etiqueta-wrap #btn-agregar-item-surmar {
        height: calc(1.8125rem + 2px);
        line-height: 1;
        padding-top: 0;
        padding-bottom: 0;
    }
</style>
<script>
@php
    $ocCfg = $recepcion->ordencompras ?? null;
    $puedeConsultarOcCfg = $ocCfg && (can('editar-ordencompra', false) || can('listar-ordencompra', false));
    $urlConsultarOcCfg = $ocCfg
        ? route('editar_ordencompra', ['id' => $ocCfg->id, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '';
@endphp
window.seteoSalidaPrograma = @json(\App\Support\Configuracion\SeteoSalidaProgramaSupport::STOCK_ETIQUETA_SURMAR);
window.seteoSalidaConfigurarUrl = @json(route('configurar_salida', ['programa' => ':programa']));
window.SURMAR_RECEPCION = {
    id: {{ (int) $recepcion->id }},
    editable: @json((bool) $editable),
    fechaRecepcion: @json(optional($recepcion->fecha)->format('Y-m-d')),
    certificadoSenasa: @json((string) ($recepcion->certificado_senasa ?? '')),
    proveedorNombre: @json((string) ($proveedorNombreEtiqueta ?? '')),
    destinoEtiquetaDefault: @json(config('recepcion_anita_surmar.etiqueta_destino_default', 'impresora')),
    lineas: @json($lineas),
    lineasOc: @json($lineasOc ?? []),
    unidadesmedida: @json($unidadesmedida ?? []),
    separaDefaultId: {{ (int) ($separaDefaultId ?? 2) }},
    puedeConsultarOc: @json((bool) $puedeConsultarOcCfg),
    entregaSemanal: @json(\App\Support\Compras\OrdencompraUiConfigSupport::entregaSemanal()),
    ordencompraId: {{ (int) ($recepcion->ordencompra_id ?? 0) }},
    numeroOrdencompra: {{ (int) (optional($recepcion->ordencompras)->numeroordencompra ?? 0) }},
    urls: {
        guardarLinea: @json(route('api_guardar_linea_recepcion_proveedor_surmar', $recepcion->id)),
        actualizarLinea: @json(url('stock/recepcion-proveedor-surmar/'.$recepcion->id.'/linea')),
        eliminarLinea: @json(url('stock/recepcion-proveedor-surmar/'.$recepcion->id.'/linea')),
        previewEtiqueta: @json(url('stock/recepcion-proveedor-surmar/'.$recepcion->id.'/etiqueta')),
        zpl: @json(url('stock/etiqueta-surmar')),
        pdfEtiqueta: @json(url('stock/etiqueta-surmar')),
        imprimirSalida: @json(route('imprimir_salida_etiqueta_surmar')),
        estadoSalida: @json(route('estado_salida_etiqueta_surmar')),
        consultarOc: @json($urlConsultarOcCfg),
        entregasSemanales: @json(url('compras/ordencompra-articulo')),
        entregasSemanalesOrden: @json(
            ($recepcion->ordencompra_id ?? null)
                ? route('ordencompra_entregas_semanales', ['id' => $recepcion->ordencompra_id])
                : ''
        ),
        token: @json(csrf_token()),
        carpetaBase: @json(rtrim(config('app.app_carpeta') ?: '', '/'))
    }
};
</script>
<script src="{{ asset('assets/pages/scripts/configuracion/salida.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/configuracion/configurar_salida.js') }}" type="text/javascript"></script>
@if ($editable)
<script src="{{ asset('assets/pages/scripts/stock/depmae/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
@endif
<script src="{{ asset('assets/pages/scripts/stock/recepcion_proveedor_surmar/cargar.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/recepcion_proveedor_surmar/cargar.js')) ?: time() }}"></script>
@endsection

@section('contenido')
@php
    $oc = $recepcion->ordencompras;
    $puedeConsultarOc = $oc && (can('editar-ordencompra', false) || can('listar-ordencompra', false));
    $solapaActiva = ($solapa ?? 'items') === 'encabezado' ? 'encabezado' : 'items';
    $dep = $recepcion->depositos;
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-truck"></i>
                    Recepción Surmar Nº {{ $recepcion->numerorecepcion }}
                    @if ($recepcion->estado === 'BORRADOR')
                        <span class="badge badge-warning ml-2">Provisorio</span>
                    @elseif ($recepcion->estado === 'CONFIRMADA')
                        <span class="badge badge-success ml-2">Confirmada</span>
                    @else
                        <span class="badge badge-secondary ml-2">{{ $recepcion->estado }}</span>
                    @endif
                    @include('includes.configurar-salida')
                </h3>
                <div class="card-tools">
                    <a href="#" onclick="return configurarSalida();" class="btn btn-outline-secondary btn-sm mr-1" title="Zebra en red o cola CUPS">
                        <i class="fa fa-fw fa-cog"></i> Configura salida
                    </a>
                    <a href="{{ route('recepcion_proveedor_surmar') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Listado</a>
                </div>
            </div>
            <div class="card-body">
                <div class="row mb-2">
                    <div class="col-md-2"><strong>Fecha:</strong> {{ optional($recepcion->fecha)->format('d/m/Y') }}</div>
                    <div class="col-md-2">
                        <strong>OC:</strong>
                        @if ($oc)
                            @if ($puedeConsultarOc)
                                <a href="{{ route('editar_ordencompra', ['id' => $oc->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                                   class="text-primary" target="_blank">{{ $oc->numeroordencompra }}</a>
                            @else
                                {{ $oc->numeroordencompra }}
                            @endif
                        @else
                            —
                        @endif
                    </div>
                    <div class="col-md-4"><strong>Proveedor:</strong> {{ $recepcion->proveedores->nombre ?? '—' }}</div>
                    <div class="col-md-2"><strong>Depósito:</strong> {{ $recepcion->depositos->nombre ?? $recepcion->depositos->descripcion ?? '—' }}</div>
                    <div class="col-md-2 surmar-estado-vivo text-right">
                        <span id="surmar-msg-vivo" class="text-muted">Listo para cargar ítems</span>
                    </div>
                </div>

                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas mb-3">
                    <ul class="nav nav-tabs" id="tabs-recepcion-surmar" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link {{ $solapaActiva === 'encabezado' ? 'active' : '' }}"
                               id="tab-encabezado-link"
                               data-toggle="tab"
                               href="#tab-encabezado"
                               role="tab">
                                <i class="fa fa-info-circle"></i> Encabezado / SENASA
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link {{ $solapaActiva === 'items' ? 'active' : '' }}"
                               id="tab-items-link"
                               data-toggle="tab"
                               href="#tab-items"
                               role="tab">
                                <i class="fa fa-list"></i> Ítems / etiquetas
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade {{ $solapaActiva === 'encabezado' ? 'show active' : '' }}"
                         id="tab-encabezado" role="tabpanel">
                        @if ($editable)
                        <form action="{{ route('actualizar_encabezado_recepcion_proveedor_surmar', $recepcion->id) }}"
                              method="POST" id="form-encabezado-surmar" class="form-horizontal" autocomplete="off">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresa_id }}">
                            <div class="card card-outline card-info mb-3">
                                <div class="card-header py-2"><strong>Datos de cabecera</strong></div>
                                <div class="card-body">
                                    <div class="form-group row">
                                        <label class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                                        <div class="col-lg-3">
                                            <input type="date" name="fecha" class="form-control surmar-enc-nav"
                                                   value="{{ old('fecha', optional($recepcion->fecha)->format('Y-m-d')) }}" required>
                                        </div>
                                    </div>
                                    @include('stock.partials.campo_consulta_deposito', [
                                        'prefix' => 'recepcion_surmar_enc',
                                        'layout' => 'form_row',
                                        'inputName' => 'deposito_id',
                                        'inputId' => 'deposito_id',
                                        'depositoId' => old('deposito_id', $recepcion->deposito_id),
                                        'codigo' => old('deposito_codigo', $dep->codigo ?? ''),
                                        'descripcion' => old('deposito_descripcion', $dep->nombre ?? $dep->descripcion ?? ''),
                                        'col_label' => 'col-lg-4 control-label text-right pr-2 requerido',
                                        'col_input' => 'col-lg-6',
                                        'codigoExtraClass' => 'surmar-enc-nav',
                                    ])
                                    <div class="form-group row">
                                        <label class="col-lg-4 control-label text-right pr-2">Observación</label>
                                        <div class="col-lg-6">
                                            <textarea name="observacion" class="form-control" rows="2">{{ old('observacion', $recepcion->observacion) }}</textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            @include('stock.recepcion_proveedor_surmar.partials.form_datos_senasa', [
                                'certificado_senasa' => $recepcion->certificado_senasa,
                                'tropa' => $recepcion->tropa,
                                'temperatura_ingreso' => $recepcion->temperatura_ingreso,
                                'destino_senasa' => $recepcion->destino_senasa ?: 'Consumo interno',
                                'camara' => $recepcion->camara,
                                'nro_establecimiento' => $recepcion->nro_establecimiento,
                            ])
                            <div class="text-right mb-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-save"></i> Guardar encabezado
                                </button>
                                <button type="submit" class="btn btn-outline-primary" name="volver_solapa" value="items">
                                    Ir a ítems <i class="fa fa-arrow-right"></i>
                                </button>
                            </div>
                        </form>
                        @else
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2"><strong>Datos SENASA</strong></div>
                            <div class="card-body">
                                <dl class="row mb-0">
                                    <dt class="col-sm-3">Certificado</dt><dd class="col-sm-9">{{ $recepcion->certificado_senasa ?: '—' }}</dd>
                                    <dt class="col-sm-3">Tropa</dt><dd class="col-sm-9">{{ $recepcion->tropa ?: '—' }}</dd>
                                    <dt class="col-sm-3">Temperatura</dt><dd class="col-sm-9">{{ $recepcion->temperatura_ingreso !== null ? $recepcion->temperatura_ingreso : '—' }}</dd>
                                    <dt class="col-sm-3">Destino</dt><dd class="col-sm-9">{{ $recepcion->destino_senasa ?: '—' }}</dd>
                                    <dt class="col-sm-3">Cámara</dt><dd class="col-sm-9">{{ $recepcion->camara ?: '—' }}</dd>
                                    <dt class="col-sm-3">Establecimiento</dt><dd class="col-sm-9">{{ $recepcion->nro_establecimiento ?: '—' }}</dd>
                                    <dt class="col-sm-3">Observación</dt><dd class="col-sm-9">{{ $recepcion->observacion ?: '—' }}</dd>
                                </dl>
                            </div>
                        </div>
                        @endif
                    </div>

                    <div class="tab-pane fade {{ $solapaActiva === 'items' ? 'show active' : '' }}"
                         id="tab-items" role="tabpanel">
                        @if (!empty($lineasOc) || $editable)
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2">
                                <strong>Líneas OC pendientes</strong>
                                <span class="text-muted small ml-1">— si la OC tiene varios artículos, elija uno con <em>Elegir</em>, etiquete ese ítem y luego el siguiente artículo</span>
                            </div>
                            <div class="card-body p-0 table-responsive">
                                <table class="table table-sm table-bordered table-hover mb-0 surmar-wb" id="tabla-oc-pendientes-surmar">
                                    <thead>
                                        <tr>
                                            <th></th>
                                            <th>SKU</th>
                                            <th>Descripción</th>
                                            <th class="text-right">Pedida</th>
                                            <th class="text-right">Recibida</th>
                                            <th class="text-right">Pendiente</th>
                                            <th class="text-right">Peso unit.</th>
                                            <th class="text-right">Peso tot.</th>
                                            <th class="text-right">Precio</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                        @endif

                        @if ($editable)
                        <div class="card card-outline card-info mb-3">
                            <div class="card-header py-2 d-flex flex-wrap align-items-center justify-content-between">
                                <div>
                                    <strong>Nuevo ítem</strong>
                                    <span class="text-muted small ml-1">— elija una línea OC con <em>Elegir</em>, o un artículo fuera de la OC</span>
                                </div>
                                <button type="button" id="btn-articulo-extra-surmar" class="btn btn-outline-warning btn-sm"
                                    title="Cargar un artículo que no está en la orden de compra">
                                    <i class="fa fa-plus"></i> Artículo fuera de OC
                                </button>
                            </div>
                            <div class="card-body">
                                <input type="hidden" id="ordencompra_articulo_id" value="">
                                <input type="hidden" id="precio_oc" value="">
                                <div class="form-row" id="surmar-nuevo-item-campos">
                                    <div class="form-group col-md-3 tm-articulo-campo">
                                        <label class="control-label">Artículo</label>
                                        <div class="d-flex flex-nowrap align-items-center w-100" style="gap:4px;">
                                            <input type="hidden" id="articulo_id" class="articulo_id" value="">
                                            <button type="button" title="Consulta artículos (F1) — también para EXTRA fuera de OC" class="btn-accion-tabla consultaarticulo tooltipsC flex-shrink-0">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" id="codigoarticulo" class="form-control form-control-sm codigoarticulo surmar-item-nav flex-shrink-0"
                                                   placeholder="SKU" autocomplete="off" style="max-width:7rem;">
                                            <input type="text" id="descripcionarticulo" class="form-control form-control-sm descripcionarticulo"
                                                   placeholder="OC o consulta…" readonly style="min-width:0;flex:1 1 auto;">
                                        </div>
                                        <small class="form-text text-muted mb-0" id="surmar-origen-articulo">Sin artículo</small>
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="control-label">Lote</label>
                                        <input type="text" id="lote_proveedor" class="form-control form-control-sm surmar-item-nav" maxlength="30" autocomplete="off">
                                    </div>
                                    <div class="form-group col-md-2">
                                        <label class="control-label">Vto.</label>
                                        <input type="date" id="fecha_vto" class="form-control form-control-sm surmar-item-nav">
                                    </div>
                                    <div class="form-group col-md-1">
                                        <label class="control-label">Piezas</label>
                                        <input type="number" step="0.01" id="cant_pieza" class="form-control form-control-sm surmar-item-nav" value="1">
                                    </div>
                                    <div class="form-group col-md-1">
                                        <label class="control-label">Bruto</label>
                                        <input type="number" step="0.01" id="peso_bruto" class="form-control form-control-sm surmar-item-nav">
                                    </div>
                                    <div class="form-group col-md-1">
                                        <label class="control-label">Tara</label>
                                        <input type="number" step="0.01" id="peso_tara" class="form-control form-control-sm surmar-item-nav" value="0" title="Peso del bin/carro">
                                    </div>
                                    <div class="form-group col-md-1">
                                        <label class="control-label">Neto</label>
                                        <input type="number" step="0.01" id="peso_neto" class="form-control form-control-sm surmar-item-nav">
                                    </div>
                                    <div class="form-group col-md-1 surmar-btn-etiqueta-wrap">
                                        <label class="control-label" aria-hidden="true">&nbsp;</label>
                                        <button type="button" id="btn-agregar-item-surmar" class="btn btn-primary btn-sm btn-block" title="Abrir etiquetas proveedor">
                                            <i class="fa fa-tags"></i>
                                        </button>
                                    </div>
                                </div>
                                <p class="small text-muted mb-0">
                                    <strong>De la OC:</strong> pulse <em>Elegir</em> en la grilla superior.
                                    <strong>Fuera de OC (EXTRA):</strong> use «Artículo fuera de OC» o la lupa / F1 / SKU (se desvincula la línea OC).
                                    Luego complete lote/pesos y abra etiquetas (<i class="fa fa-tags"></i>).
                                </p>
                            </div>
                        </div>
                        @endif

                        <div class="d-flex flex-wrap align-items-center justify-content-between mb-2">
                            <div>
                                <strong>Ítems cargados</strong>
                                <span class="text-muted small ml-1">— totales grisados = suma de etiquetas (solo visualización). Colapse para ver la recepción ítem a ítem.</span>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button type="button" id="btn-colapsar-todos-bloques" class="btn btn-outline-secondary" title="Colapsar etiquetas">
                                    <i class="fa fa-compress"></i> Colapsar
                                </button>
                                <button type="button" id="btn-expandir-todos-bloques" class="btn btn-outline-secondary" title="Expandir etiquetas">
                                    <i class="fa fa-expand"></i> Expandir
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive mb-2">
                            <table class="table table-sm table-bordered table-hover mb-0 surmar-wb" id="tabla-items-recepcion-surmar">
                                <thead>
                                    <tr>
                                        <th style="width:2.5rem;"></th>
                                        <th>Tipo</th>
                                        <th>SKU</th>
                                        <th>Descripción</th>
                                        <th class="text-right">Etiquetas</th>
                                        <th class="text-right" title="Suma de piezas de las etiquetas">Piezas</th>
                                        <th class="text-right" title="Suma bruto etiquetas">Bruto</th>
                                        <th class="text-right" title="Suma tara etiquetas">Tara</th>
                                        <th class="text-right" title="Suma neto etiquetas">Neto</th>
                                        <th class="text-center">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="surmar-lineas-vacio" class="alert alert-light border text-muted d-none mb-0">
                            Sin ítems aún. Elija una línea OC o un artículo fuera de OC y abra etiquetas.
                        </div>
                    </div>
                </div>
            </div>

            <div class="surmar-wb-sticky d-flex flex-wrap align-items-center justify-content-between">
                <div>
                    <span id="surmar-total-items">0 ítem(s) / 0 etiqueta(s)</span> ·
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
@if ($editable)
    @include('includes.stock.modalconsultadeposito')
    @include('includes.stock.modalconsultaarticulo')
@endif
@if (\App\Support\Compras\OrdencompraUiConfigSupport::entregaSemanal())
    @include('compras.ordencompra.partials.modal_entrega_semanal', ['soloLectura' => true])
    @include('compras.ordencompra.partials.modal_entrega_semanal_resumen')
@endif
@include('stock.recepcion_proveedor_surmar.partials.modal_etiqueta_proveedor')
@include('includes.proceso_overlay_aviso', [
    'overlayId' => 'surmar-overlay',
    'tituloId' => 'surmar-overlay-titulo',
    'subtituloId' => 'surmar-overlay-subtitulo',
    'titulo' => 'Grabando ítem…',
    'subtitulo' => 'No cierre la página.',
])
@endsection
