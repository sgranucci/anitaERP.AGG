@extends("theme.$theme.layout")
@section('titulo')
Generar órdenes de compra desde requisición
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/articulo/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/presupuesto/partidagasto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/presupuesto/capex/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/requisicion/wizard-desde-requisicion.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Compras\OrdencompraEstados;

    $ocJsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
    $monedasJson = json_encode(
        $moneda_query->map(fn ($m) => ['id' => (int) $m->id, 'abrev' => (string) ($m->abreviatura ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $proveedoresJson = json_encode(
        $proveedor_query->map(fn ($p) => ['id' => (int) $p->id, 'codigo' => (string) ($p->codigo ?? ''), 'nombre' => (string) ($p->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $condicionesCompraJson = json_encode(
        $condicioncompra_query->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => (string) ($c->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $condicionesEntregaJson = json_encode(
        $condicionentrega_query->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => (string) ($c->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $condicionesPagoJson = json_encode(
        $condicionpago_query->map(fn ($c) => ['id' => (int) $c->id, 'nombre' => (string) ($c->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $transportesJson = json_encode(
        $transporte_query->map(fn ($t) => ['id' => (int) $t->id, 'nombre' => (string) ($t->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $formapagosJson = json_encode(
        $formapago_query->map(fn ($f) => ['id' => (int) $f->id, 'nombre' => (string) ($f->nombre ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $centrocostoDefaultDestino = (int) (auth()->user()->centrocosto_id ?? 1);
    $monedaPesoId = (int) (
        optional($moneda_query->firstWhere('abreviatura', '$'))->id
        ?? optional($moneda_query->firstWhere('abreviatura', 'ARS'))->id
        ?? 1
    );
    $tratamientosJson = json_encode(
        collect($tratamiento_enum)->map(fn ($t) => ['nombre' => (string) ($t['nombre'] ?? '')])->values()->all(),
        $ocJsonFlags
    );
    $wizardMetaJson = json_encode([
        'requisicion_id' => (int) $wizardRequisicionId,
        'post_url' => route('requisicion_generar_multiples_oc', ['id' => (int) $wizardRequisicionId]),
        'plantilla_url' => route('ordencompra_plantilla_requisicion'),
        'opciones_url' => route('ordencompra_opciones_precio_linea'),
        'cotizacion_url' => route('ordencompra_cotizacion_moneda_fecha'),
        'sugerir_cuotas_url' => route('ordencompra_sugerir_cuotas'),
        'calcular_totales_url' => route('ordencompra_calcular_totales'),
        'volver_url' => route('solo_consulta_requisicion', ['id' => (int) $wizardRequisicionId]),
        'index_oc_url' => route('consultar_ordencompra'),
        'csrf' => csrf_token(),
        'moneda_peso_id' => $monedaPesoId,
        'centrocosto_default_id' => $centrocostoDefaultDestino,
    ], $ocJsonFlags);
@endphp

<script type="application/json" id="oc-wizard-meta">{!! $wizardMetaJson !!}</script>
<script type="application/json" id="oc-wizard-monedas">{!! $monedasJson !!}</script>
<script type="application/json" id="oc-wizard-proveedores">{!! $proveedoresJson !!}</script>
<script type="application/json" id="oc-wizard-condicionescompra">{!! $condicionesCompraJson !!}</script>
<script type="application/json" id="oc-wizard-condicionesentrega">{!! $condicionesEntregaJson !!}</script>
<script type="application/json" id="oc-wizard-condicionespago">{!! $condicionesPagoJson !!}</script>
<script type="application/json" id="oc-wizard-transportes">{!! $transportesJson !!}</script>
<script type="application/json" id="oc-wizard-formapagos">{!! $formapagosJson !!}</script>
<script type="application/json" id="oc-wizard-tratamientos">{!! $tratamientosJson !!}</script>

<style>
    /* Layout del wizard "ítems primero" */
    #wizard-oc-root .wizard-oc-articulos-card {
        border-top: 4px solid #007bff;
    }
    #wizard-oc-root .wizard-oc-cabecera-card {
        border-top: 4px solid #6c757d;
    }
    #wizard-oc-root .wizard-oc-grupos-card {
        border-top: 4px solid #28a745;
    }
    #wizard-oc-articulos table.wizard-oc-tabla tr.wizard-oc-fila-item td {
        vertical-align: middle;
    }
    #wizard-oc-articulos .wizard-oc-origen-pill {
        font-size: 0.78rem;
        line-height: 1.1;
    }
    #wizard-oc-articulos .wizard-oc-origen-pill.sin-origen {
        background-color: #f8d7da;
        color: #842029;
    }
    #wizard-oc-articulos .wizard-oc-origen-pill.con-origen {
        background-color: #d1e7dd;
        color: #0f5132;
    }
    #wizard-oc-grupos-resumen .grupo-resumen-fila {
        cursor: pointer;
    }
    #wizard-oc-grupos-resumen .grupo-resumen-fila:hover {
        background-color: #f1f5f9;
    }
    #wizard-oc-grupos-resumen .grupo-resumen-fila.activa {
        background-color: #e7f1ff;
    }
    #wizard-oc-cabeceras .tab-pane .card-body {
        padding-top: 0.75rem;
    }
    #wizard-oc-cabeceras .wz-grupo-totales-line {
        white-space: nowrap;
        overflow-x: auto;
        font-size: 0.8125rem;
        line-height: 1.35;
    }
    .wizard-oc-tabla-comprobantes td,
    .wizard-oc-tabla-archivos td {
        vertical-align: middle;
    }
</style>

{{-- Inputs ocultos compartidos: el modal de consulta de proveedor reutilizado escribe acá; nuestro JS lee y propaga al grupo activo. --}}
<input type="hidden" id="proveedor_id" value="">
<input type="hidden" id="codigoproveedor" value="">
<input type="hidden" id="nombreproveedor" value="">

<div class="row" id="wizard-oc-root">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">
                    Generar órdenes de compra desde requisición #{{ (int) $wizardRequisicionId }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('solo_consulta_requisicion', ['id' => (int) $wizardRequisicionId]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver a la requisición
                    </a>
                    <a href="{{ route('consultar_ordencompra') }}" class="btn btn-outline-light btn-sm">
                        <i class="fa fa-list"></i> Listar OC
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-info">
                    <strong>Cómo funciona:</strong> primero elija el <em>origen de precio</em> de cada ítem (lista del proveedor, presupuesto o precio cargado en la requisición). En base a esa elección, el sistema agrupa los ítems en una o más órdenes de compra. Cada OC detectada aparece en la sección inferior, donde podrá ajustar la cabecera, agregar comprobantes a venir y archivos asociados. Antes de generar se le pedirá una confirmación final.
                </div>

                <div class="card wizard-oc-articulos-card mb-3" id="wizard-oc-articulos">
                    <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa fa-list-ul mr-1"></i> Ítems de la requisición
                            <small class="text-muted ml-2">elija el origen del precio por ítem</small>
                        </h5>
                        <span class="badge badge-info" id="wizard-oc-articulos-resumen">— ítems</span>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered wizard-oc-tabla" id="wizard-oc-tabla-articulos">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 4%;">#</th>
                                        <th style="width: 9%;">Artículo</th>
                                        <th style="width: 14%;">Descripción</th>
                                        <th style="width: 6%;">Cant.</th>
                                        <th style="width: 8%;">Precio</th>
                                        <th style="width: 5%;">Mon.</th>
                                        <th style="width: 6%;">Cotiz.</th>
                                        <th style="width: 8%;">F. entrega</th>
                                        <th style="width: 8%;">CC destino</th>
                                        <th style="width: 11%;">Partida presup.</th>
                                        <th style="width: 9%;">CAPEX</th>
                                        <th style="width: 12%;">Origen / Proveedor</th>
                                    </tr>
                                </thead>
                                <tbody id="wizard-oc-tabla-articulos-body">
                                    <tr><td colspan="12" class="text-center text-muted py-3">Cargando ítems…</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card wizard-oc-grupos-card mb-3" id="wizard-oc-grupos">
                    <div class="card-header py-2 d-flex flex-wrap justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fa fa-layer-group mr-1"></i> Órdenes de compra a generar
                            <small class="text-muted ml-2">se agrupan por proveedor y condiciones de compra/entrega</small>
                        </h5>
                        <span class="badge badge-success" id="wizard-oc-grupos-cantidad">0 órdenes</span>
                    </div>
                    <div class="card-body">
                        <div id="wizard-oc-grupos-vacio" class="text-muted small">
                            Aún no se eligió el origen de precio en ningún ítem. Cuando comience a elegir orígenes, se mostrarán acá los grupos de OC detectados.
                        </div>
                        <div class="table-responsive d-none" id="wizard-oc-grupos-resumen-wrap">
                            <table class="table table-sm mb-0" id="wizard-oc-grupos-resumen">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 4%;">#</th>
                                        <th>Proveedor</th>
                                        <th>Condición compra</th>
                                        <th>Condición entrega</th>
                                        <th class="text-right">Ítems</th>
                                        <th class="text-right">Comp. a venir</th>
                                        <th class="text-right">Archivos</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div id="wizard-oc-lineas-sin-origen-aviso" class="alert alert-warning mt-2 d-none">
                            <strong><span class="cant"></span> ítem(es) sin origen de precio.</strong>
                            Si genera con esta configuración, esos ítems quedarán cerrados sin OC y la requisición pasará a "GENERO ORDEN COMPRA".
                        </div>
                    </div>
                </div>

                <div class="card wizard-oc-cabecera-card mb-3">
                    <div class="card-header py-2">
                        <h5 class="mb-0"><i class="fa fa-cogs mr-1"></i> Datos compartidos por todas las OC</h5>
                    </div>
                    <div class="card-body py-2">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-lg-4 col-form-label requerido" for="wz_empresa_id">Empresa</label>
                                    <div class="col-lg-8">
                                        @include('includes.form-empresa-asignada-control', [
                                            'empresa_query' => $empresa_query,
                                            'id' => 'wz_empresa_id',
                                            'name' => 'wz_empresa_id',
                                        ])
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-lg-4 col-form-label requerido">Fecha doc.</label>
                                    <div class="col-lg-4">
                                        <input type="date" id="wz_fecha" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                    </div>
                                    <label class="col-lg-2 col-form-label requerido">F. entrega</label>
                                    <div class="col-lg-2 pr-2">
                                        <input type="date" id="wz_fechaentrega" class="form-control form-control-sm" value="{{ date('Y-m-d') }}">
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-lg-4 col-form-label requerido">Centro de costo</label>
                                    <div class="col-lg-8">
                                        <select id="wz_centrocosto_id" class="form-control form-control-sm">
                                            @foreach ($centrocosto_query as $cc)
                                                <option value="{{ $cc->id }}" {{ (int) $cc->id === $centrocostoDefaultDestino ? 'selected' : '' }}>
                                                    {{ $cc->codigo }} — {{ $cc->nombre }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-lg-4 col-form-label requerido">Tratamiento</label>
                                    <div class="col-lg-8">
                                        <select id="wz_tratamiento" class="form-control form-control-sm">
                                            @foreach ($tratamiento_enum as $t)
                                                <option value="{{ $t['nombre'] }}" {{ ($t['nombre'] ?? '') === 'NO ANTICIPADA' ? 'selected' : '' }}>{{ $t['nombre'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row mb-2">
                                    <label class="col-lg-3 col-form-label">Comentario</label>
                                    <div class="col-lg-9">
                                        <input type="text" id="wz_comentario" class="form-control form-control-sm" maxlength="255">
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-lg-3 col-form-label requerido">Detalle</label>
                                    <div class="col-lg-9">
                                        <textarea id="wz_detalle" rows="3" class="form-control form-control-sm" maxlength="2000"></textarea>
                                    </div>
                                </div>
                                <div class="form-group row mb-2">
                                    <label class="col-lg-3 col-form-label">Descuento general (%)</label>
                                    <div class="col-lg-3">
                                        <input type="number" step="0.01" min="0" id="wz_descuento" class="form-control form-control-sm" placeholder="0.00">
                                    </div>
                                    <small class="form-text text-muted col-lg-6">Se aplica al neto antes del IVA por OC.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3 d-none" id="wizard-oc-cabeceras-card">
                    <div class="card-header py-2">
                        <h5 class="mb-0">
                            <i class="fa fa-folder-open mr-1"></i> Cabecera por orden de compra
                            <small class="text-muted ml-2">datos específicos, comprobantes y archivos de cada OC</small>
                        </h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs" role="tablist" id="wizard-oc-cabeceras-tabs"></ul>
                        <div class="tab-content border border-top-0 p-2 bg-white" id="wizard-oc-cabeceras"></div>
                    </div>
                </div>

                <div class="text-right">
                    <button type="button" class="btn btn-success btn-lg" id="wizard-oc-btn-generar" disabled>
                        <i class="fa fa-bolt"></i>
                        Generar OCs (<span id="wizard-oc-btn-generar-cantidad">0</span>)
                    </button>
                    <p class="small text-danger text-right mt-2 mb-0" id="wizard-oc-btn-generar-hint" style="min-height: 1.25rem;"></p>
                </div>
            </div>
        </div>

        {{-- Plantilla para una pestaña de OC --}}
        <template id="wizard-oc-tab-template">
            <div class="tab-pane fade wizard-oc-tab-pane" role="tabpanel">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 col-form-label">Proveedor</label>
                                <div class="col-lg-8">
                                    <div class="input-group input-group-sm">
                                        <input type="text" class="form-control wz-grupo-proveedor-nombre" readonly>
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-secondary wz-grupo-proveedor-buscar" title="Cambiar proveedor"><i class="fa fa-search"></i></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 col-form-label">Condición compra</label>
                                <div class="col-lg-8">
                                    <select class="form-control form-control-sm wz-grupo-condicioncompra"></select>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 col-form-label">Condición entrega</label>
                                <div class="col-lg-8">
                                    <select class="form-control form-control-sm wz-grupo-condicionentrega"></select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 col-form-label">Transporte</label>
                                <div class="col-lg-8">
                                    <select class="form-control form-control-sm wz-grupo-transporte"></select>
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 col-form-label">Lugar entrega</label>
                                <div class="col-lg-8">
                                    <input type="text" class="form-control form-control-sm wz-grupo-lugarentrega" maxlength="255">
                                </div>
                            </div>
                            <div class="form-group row mb-2">
                                <label class="col-lg-4 col-form-label">Comentario</label>
                                <div class="col-lg-8">
                                    <input type="text" class="form-control form-control-sm wz-grupo-comentario" maxlength="255" placeholder="Sobrescribe el comentario compartido">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-1 mb-2">
                        <div class="col-12">
                            <div class="wz-grupo-totales-line border-top pt-1 text-muted" title="Estimado según ítems de esta OC, fecha de documento y % de descuento general (datos compartidos).">
                                <span class="wz-grupo-totales-text">—</span>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-12">
                            <div class="card border-secondary">
                                <div class="card-header py-1 d-flex flex-wrap justify-content-between align-items-center">
                                    <strong><i class="fa fa-receipt mr-1"></i> Comprobantes a venir</strong>
                                    <button type="button" class="btn btn-danger btn-sm wz-grupo-btn-agregar-comprobante"><i class="fa fa-plus"></i> Agregar comprobante</button>
                                </div>
                                <div class="card-body p-2">
                                    <table class="table table-bordered table-sm wizard-oc-tabla-comprobantes mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Tipo</th>
                                                <th>Vencimiento</th>
                                                <th class="text-right">Monto</th>
                                                <th>Mon.</th>
                                                <th>Detalle</th>
                                                <th>Cuotas</th>
                                                <th data-orderable="false"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="wz-grupo-comprobantes-body"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-2">
                        <div class="col-md-12">
                            <div class="card border-secondary">
                                <div class="card-header py-1 d-flex flex-wrap justify-content-between align-items-center">
                                    <strong><i class="fa fa-paperclip mr-1"></i> Archivos asociados</strong>
                                    <div>
                                        <input type="file" class="d-none wz-grupo-archivo-input" multiple>
                                        <button type="button" class="btn btn-danger btn-sm wz-grupo-btn-agregar-archivo"><i class="fa fa-plus"></i> Agregar archivos</button>
                                    </div>
                                </div>
                                <div class="card-body p-2">
                                    <table class="table table-bordered table-sm wizard-oc-tabla-archivos mb-0">
                                        <thead class="thead-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Nombre</th>
                                                <th class="text-right">Tamaño</th>
                                                <th data-orderable="false"></th>
                                            </tr>
                                        </thead>
                                        <tbody class="wz-grupo-archivos-body">
                                            <tr class="wz-grupo-archivos-vacio"><td colspan="4" class="text-center text-muted small">No hay archivos adjuntos.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>

        {{-- Modales compartidos --}}
        @include('compras.ordencompra._modales_comprobantes')

        {{-- Modal de origen de precio (reutiliza la misma lógica que el CRUD) --}}
        <div class="modal fade" id="modalOcOrigenPrecio" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header py-2">
                        <h5 class="modal-title">Origen del precio de la línea</h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p class="small text-muted mb-2" id="modalOcOrigenPrecioSubtitulo"></p>
                        <div id="modalOcOrigenPrecioCargando" class="text-center text-muted py-3 d-none">Cargando opciones…</div>
                        <div id="modalOcOrigenPrecioError" class="alert alert-danger d-none"></div>
                        <div id="modalOcOrigenPrecioOpciones"></div>
                    </div>
                    <div class="modal-footer py-2">
                        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal de confirmación final --}}
        <div class="modal fade" id="modalWizardConfirmGenerar" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title">Confirmar generación de OCs</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Cerrar"><span aria-hidden="true">&times;</span></button>
                    </div>
                    <div class="modal-body">
                        <p>Se van a generar <strong><span id="wz-confirm-cantidad">0</span></strong> orden(es) de compra a partir de la requisición #{{ (int) $wizardRequisicionId }}.</p>
                        <ul class="small text-muted" id="wz-confirm-detalle"></ul>
                        <div class="alert alert-warning small" id="wz-confirm-sin-origen-aviso" style="display:none;">
                            <strong><span class="cant"></span></strong> ítem(es) sin origen de precio quedarán cerrados en la requisición.
                        </div>
                        <p class="mb-0">¿Confirma la generación?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-success" id="wz-confirm-aceptar"><i class="fa fa-check"></i> Generar</button>
                    </div>
                </div>
            </div>
        </div>

        {{-- Modal de resultados --}}
        <div class="modal fade" id="modalWizardResultados" tabindex="-1" role="dialog" aria-hidden="true" data-backdrop="static" data-keyboard="false">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title"><i class="fa fa-check-circle mr-1"></i> Órdenes de compra generadas</h5>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Se generaron las siguientes órdenes de compra:</p>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered mb-0">
                                <thead class="thead-light">
                                    <tr>
                                        <th style="width: 18%;">Nº OC</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody id="wz-resultados-body"></tbody>
                            </table>
                        </div>
                        <div id="wz-resultados-advertencias" class="alert alert-warning mt-2 d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <a href="{{ route('solo_consulta_requisicion', ['id' => (int) $wizardRequisicionId]) }}" class="btn btn-outline-secondary">Volver a la requisición</a>
                        <a href="{{ route('consultar_ordencompra') }}" class="btn btn-primary"><i class="fa fa-list"></i> Ir al listado de OC</a>
                    </div>
                </div>
            </div>
        </div>

        @include('includes.stock.modalconsultaarticulo')
        @include('includes.presupuesto.modalconsultapartidagasto', ['centrocosto_query' => $centrocosto_query ?? null])
        @include('includes.presupuesto.modalconsultacapex', ['centrocosto_query' => $centrocosto_query ?? null])
        @include('includes.compras.modalconsultaproveedor')
    </div>
</div>
@endsection
