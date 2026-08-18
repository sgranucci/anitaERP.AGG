@extends("theme.$theme.layout")
@section('titulo')
    Diseñar informe {{ $data->codigo }}
@endsection

@section('styles')
<style>
.rd-layout { display: grid; grid-template-columns: 1.1fr 1fr; gap: 1rem; }
@media (max-width: 991px) { .rd-layout { grid-template-columns: 1fr; } }
.rd-tree {
    border: 1px solid #dee2e6; border-radius: .25rem; background: #fff;
    max-height: 620px; overflow: auto; font-size: 13px;
}
.rd-tree-item {
    display: flex; align-items: center; gap: .4rem;
    padding: .35rem .6rem; cursor: pointer; border-bottom: 1px solid #f0f0f0;
    border-left: 3px solid transparent;
}
.rd-tree-item:hover { background: #f4f9fc; }
.rd-tree-item.active { background: #D6EAF8; border-left-color: #2471A3; }
.rd-tree-item .rd-indent { display: inline-block; width: 0; }
.rd-badge-tipo {
    font-size: 10px; text-transform: uppercase; letter-spacing: .02em;
    padding: .1rem .35rem; border-radius: 2px; background: #eef2f5; color: #34495e;
    white-space: nowrap;
}
.rd-badge-tipo.cuentas { background: #d5f5e3; color: #196f3d; }
.rd-badge-tipo.total { background: #fdebd0; color: #9a7d0a; }
.rd-badge-tipo.formula { background: #e8daef; color: #6c3483; }
.rd-badge-tipo.texto { background: #ebedef; color: #566573; }
.rd-nombre { flex: 1; min-width: 0; }
.rd-nombre.negrita { font-weight: 700; color: #1B4F72; }
.rd-meta { color: #7f8c8d; font-size: 11px; white-space: nowrap; }
.rd-help-box {
    background: #f8f9fa; border-left: 4px solid #85C1E9;
    padding: .75rem 1rem; margin-bottom: 1rem; font-size: 13px;
}
.rd-cuenta-row { font-size: 13px; }
.rd-empty { color: #95a5a6; padding: 2rem; text-align: center; }
</style>
@endsection

@section('scripts')
@php
    // @json() de Blade parte los argumentos por comas: rutas con 3 parámetros
    // o arrays literales con 3 claves deben resolverse antes en variables.
    $rdUrlActualizarColumna = route('actualizar_columna_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__', 'columnaId' => '__CID__']);
    $rdUrlEliminarColumna = route('eliminar_columna_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__', 'columnaId' => '__CID__']);
    $rdLayoutsPayload = $layouts_payload ?? ['sistema' => [], 'informe' => [], 'layout_default_id' => null, 'tipos_columna' => []];
@endphp
<script>
window.rdConfig = {
    reporteId: {{ (int) $data->id }},
    estructura: @json($estructura),
    tiposRubro: @json($tiposRubro),
    tiposRubroAyuda: @json($tiposRubroAyuda),
    urls: {
        estructura: @json(route('estructura_reporte_definible', ['id' => $data->id])),
        guardarRubro: @json(route('guardar_rubro_reporte_definible', ['id' => $data->id])),
        actualizarRubro: @json(route('actualizar_rubro_reporte_definible', ['id' => $data->id, 'rubroId' => '__RID__'])),
        eliminarRubro: @json(route('eliminar_rubro_reporte_definible', ['id' => $data->id, 'rubroId' => '__RID__'])),
        cuentasRubro: @json(route('cuentas_rubro_reporte_definible', ['id' => $data->id, 'rubroId' => '__RID__'])),
        guardarCuenta: @json(route('guardar_cuenta_reporte_definible', ['id' => $data->id, 'rubroId' => '__RID__'])),
        eliminarCuenta: @json(route('eliminar_cuenta_reporte_definible', ['id' => $data->id, 'cuentaId' => '__CID__'])),
        leerCuenta: @json(url(config('app.app_carpeta').'/contable/cuentacontable/leercuentacontableporcodigo')),
        preview: @json(route('preview_reporte_definible', ['id' => $data->id])),
        layouts: @json(route('layouts_reporte_definible', ['id' => $data->id])),
        clonarLayout: @json(route('clonar_layout_reporte_definible', ['id' => $data->id])),
        crearLayout: @json(route('crear_layout_reporte_definible', ['id' => $data->id])),
        actualizarLayout: @json(route('actualizar_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__'])),
        eliminarLayout: @json(route('eliminar_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__'])),
        defaultLayout: @json(route('default_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__'])),
        agregarColumna: @json(route('guardar_columna_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__'])),
        actualizarColumna: @json($rdUrlActualizarColumna),
        eliminarColumna: @json($rdUrlEliminarColumna),
        reordenarColumnas: @json(route('reordenar_columnas_layout_reporte_definible', ['id' => $data->id, 'layoutId' => '__LID__'])),
        eliReglas: @json(route('eli_reglas_reporte_definible', ['id' => $data->id])),
        guardarEliRegla: @json(route('guardar_eli_regla_reporte_definible', ['id' => $data->id])),
        actualizarEliRegla: @json(route('actualizar_eli_regla_reporte_definible', ['id' => $data->id, 'reglaId' => '__RID__'])),
        eliminarEliRegla: @json(route('eliminar_eli_regla_reporte_definible', ['id' => $data->id, 'reglaId' => '__RID__'])),
        guardarParticipacion: @json(route('guardar_participacion_reporte_definible', ['id' => $data->id])),
        eliminarParticipacion: @json(route('eliminar_participacion_reporte_definible', ['id' => $data->id, 'partId' => '__PID__'])),
        guardarAlerta: @json(route('guardar_alerta_reporte_definible', ['id' => $data->id])),
        eliminarAlerta: @json(route('eliminar_alerta_reporte_definible', ['id' => $data->id, 'alertaId' => '__AID__'])),
        guardarSuscripcion: @json(route('guardar_suscripcion_reporte_definible', ['id' => $data->id])),
        actualizarSuscripcion: @json(route('actualizar_suscripcion_reporte_definible', ['id' => $data->id, 'suscripcionId' => '__SID__'])),
        eliminarSuscripcion: @json(route('eliminar_suscripcion_reporte_definible', ['id' => $data->id, 'suscripcionId' => '__SID__'])),
        probarSuscripcion: @json(route('probar_suscripcion_reporte_definible', ['id' => $data->id, 'suscripcionId' => '__SID__'])),
        guardarNota: @json(route('guardar_nota_reporte_definible', ['id' => $data->id])),
        actualizarNota: @json(route('actualizar_nota_reporte_definible', ['id' => $data->id, 'notaId' => '__NID__'])),
        eliminarNota: @json(route('eliminar_nota_reporte_definible', ['id' => $data->id, 'notaId' => '__NID__'])),
        historialNota: @json(route('historial_nota_reporte_definible', ['id' => $data->id, 'notaId' => '__NID__'])),
        validar: @json(route('validar_reporte_definible', ['id' => $data->id])),
        diffVersion: @json(route('diff_version_reporte_definible', ['id' => $data->id])),
        coberturaAdd: @json(route('agregar_cuentas_cobertura_reporte_definible', ['id' => $data->id])),
    },
    csrf: @json(csrf_token()),
    puedeActualizar: @json((bool) $puede_actualizar),
    rubroInicial: {{ (int) request('rubro', 0) }},
    empresaIds: @json($empresa_query->pluck('id')->map(fn($v)=>(int)$v)->values()),
    layoutsPayload: @json($rdLayoutsPayload),
    eliReglas: @json($eli_reglas ?? []),
    participaciones: @json($participaciones ?? []),
    alertas: @json($alertas_payload ?? []),
    suscripciones: @json($suscripciones_payload ?? []),
    notas: @json($notas_payload ?? []),
    huerfanas: @json($cobertura['huerfanas'] ?? []),
    tiposColumnaLayout: @json($tipos_columna_layout ?? []),
};
</script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/reporte_definible/disenador.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/reporte_definible/layouts.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/contable/reporte_definible/beyond.js') }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-project-diagram"></i>
                    Diseñar: {{ $data->codigo }} — {{ $data->nombre }}
                </h3>
                <div class="card-tools">
                    @if ($puede_ejecutar)
                        <a href="{{ route('ejecutar_reporte_definible', ['id' => $data->id]) }}" class="btn btn-success btn-sm">
                            <i class="fa fa-play"></i> Ejecutar
                        </a>
                    @endif
                    <a href="{{ route('reporte_definible') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-reply-all"></i> Catálogo
                    </a>
                </div>
            </div>
            <div class="card-body">
                @include('includes.form-error')
                @include('includes.mensaje')

                <div class="rd-help-box">
                    <strong>Cómo se lee la estructura (estilo FSV).</strong>
                    El árbol de la izquierda es el informe tal como se imprimirá:
                    la sangría indica el nivel; cada rubro puede sumar cuentas,
                    totalizar hijos, mostrar solo texto o (próximamente) una fórmula.
                    Seleccione un rubro para ver / asignar cuentas a la derecha.
                    La misma cuenta puede figurar en más de un rubro.
                </div>

                @include('includes.tabs-activas-estilos')
                <div class="tabs-activas mb-3">
                    <ul class="nav nav-tabs" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link active" data-toggle="tab" href="#tab-estructura" role="tab">
                                <i class="fa fa-sitemap"></i> Estructura
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-cobertura" role="tab">
                                <i class="fa fa-chart-pie"></i> Cobertura plan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-preview" role="tab">
                                <i class="fa fa-eye"></i> Preview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-layouts" role="tab">
                                <i class="fa fa-columns"></i> Layouts
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-consolidacion" role="tab">
                                <i class="fa fa-sitemap"></i> Consolidación IC
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-versiones" role="tab">
                                <i class="fa fa-history"></i> Versiones
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-cabecera" role="tab">
                                <i class="fa fa-info-circle"></i> Cabecera
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-acceso" role="tab">
                                <i class="fa fa-user-lock"></i> Acceso
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-alertas" role="tab">
                                <i class="fa fa-bell"></i> Alertas
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-distribucion" role="tab">
                                <i class="fa fa-paper-plane"></i> Distribución
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" data-toggle="tab" href="#tab-notas" role="tab">
                                <i class="fa fa-sticky-note"></i> Notas al pie
                            </a>
                        </li>
                    </ul>
                </div>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-estructura" role="tabpanel">
                        @if ((int) ($impacto_publicaciones['cantidad'] ?? 0) > 0)
                            <div class="alert alert-warning py-2">
                                <i class="fa fa-stamp"></i>
                                Este informe tiene <strong>{{ $impacto_publicaciones['cantidad'] }}</strong>
                                resultado(s) publicado(s)
                                @if ($impacto_publicaciones['ultima'] ?? null)
                                    (el último, «{{ $impacto_publicaciones['ultima']->nombre }}»,
                                    del {{ $impacto_publicaciones['ultima']->created_at?->format('d/m/Y H:i') }})
                                @endif
                                . Los cambios en la definición <strong>no</strong> alteran esos documentos, pero
                                a partir de ahora una corrida nueva puede no reproducirlos.
                                <a href="{{ route('publicaciones_reporte_definible', ['id' => $data->id]) }}"
                                   class="text-primary" target="_blank" rel="noopener">Ver publicados</a>
                            </div>
                        @endif
                        <div class="rd-layout">
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h5 class="mb-0">Árbol del informe</h5>
                                    @if ($puede_actualizar)
                                        <button type="button" class="btn btn-outline-primary btn-sm" id="rd-btn-nuevo-rubro">
                                            <i class="fa fa-plus"></i> Rubro
                                        </button>
                                    @endif
                                </div>
                                <div class="rd-tree" id="rd-tree">
                                    <div class="rd-empty">Cargando estructura…</div>
                                </div>
                                <p class="small text-muted mt-2 mb-0">
                                    Tip: cree primero los rubros de nivel 1 (Activo, Pasivo…),
                                    luego agregue hijos con «Rubro hijo del seleccionado».
                                </p>
                            </div>
                            <div>
                                <div class="card card-outline card-info mb-3" id="rd-panel-rubro">
                                    <div class="card-header py-2">
                                        <strong>Rubro seleccionado</strong>
                                        <span class="text-muted small" id="rd-rubro-vacio"> — elija uno en el árbol</span>
                                    </div>
                                    <div class="card-body d-none" id="rd-rubro-form-wrap">
                                        <form id="rd-form-rubro" autocomplete="off">
                                            <input type="hidden" id="rd-rubro-id" value="">
                                            <div class="form-group">
                                                <label>Código línea</label>
                                                <input type="text" class="form-control form-control-sm" id="rd-codigo-linea" maxlength="20">
                                            </div>
                                            <div class="form-group">
                                                <label>Nombre</label>
                                                <input type="text" class="form-control form-control-sm" id="rd-nombre" maxlength="80" required>
                                            </div>
                                            <div class="form-group">
                                                <label>Tipo de rubro</label>
                                                <select class="form-control form-control-sm" id="rd-tipo">
                                                    @foreach ($tiposRubro as $k => $label)
                                                        <option value="{{ $k }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted" id="rd-tipo-ayuda"></small>
                                            </div>
                                            <div class="form-group d-none" id="rd-formula-wrap">
                                                <label>Fórmula</label>
                                                <input type="text" class="form-control form-control-sm" id="rd-formula"
                                                       placeholder="Ej. R001-R002">
                                            </div>
                                            <div class="form-group">
                                                <label>Set de cuentas (opcional)</label>
                                                <select class="form-control form-control-sm" id="rd-conjunto-id">
                                                    <option value="">— Ninguno —</option>
                                                    @foreach ($conjuntos ?? [] as $cj)
                                                        <option value="{{ $cj['id'] }}">
                                                            {{ $cj['codigo'] }} — {{ $cj['nombre'] }} ({{ $cj['cuentas_count'] }} cta)
                                                        </option>
                                                    @endforeach
                                                </select>
                                                <small class="form-text text-muted">
                                                    Las cuentas del set se suman a las del rubro al ejecutar.
                                                    <a href="{{ route('reporte_definible_conjunto') }}" target="_blank" rel="noopener">Administrar sets</a>
                                                </small>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <label>Lado presentación</label>
                                                    <select class="form-control form-control-sm" id="rd-lado-presentacion">
                                                        <option value="">Natural</option>
                                                        <option value="D">Debe</option>
                                                        <option value="H">Haber (invierte signo)</option>
                                                    </select>
                                                </div>
                                                <div class="form-group col-6">
                                                    <label class="d-block">&nbsp;</label>
                                                    <div class="custom-control custom-checkbox mt-2">
                                                        <input type="checkbox" class="custom-control-input" id="rd-ocultar-si-cero">
                                                        <label class="custom-control-label" for="rd-ocultar-si-cero">Ocultar si cero</label>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="form-row">
                                                <div class="form-group col-6">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="rd-negrita">
                                                        <label class="custom-control-label" for="rd-negrita">Negrita</label>
                                                    </div>
                                                </div>
                                                <div class="form-group col-6">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input" id="rd-subrayado">
                                                        <label class="custom-control-label" for="rd-subrayado">Subrayado</label>
                                                    </div>
                                                </div>
                                            </div>
                                            @if ($puede_actualizar)
                                                <div class="d-flex flex-wrap" style="gap:.5rem">
                                                    <button type="submit" class="btn btn-primary btn-sm">Guardar rubro</button>
                                                    <button type="button" class="btn btn-outline-primary btn-sm" id="rd-btn-hijo">
                                                        + Rubro hijo
                                                    </button>
                                                    <button type="button" class="btn btn-outline-danger btn-sm" id="rd-btn-borrar-rubro">
                                                        Eliminar
                                                    </button>
                                                </div>
                                            @endif
                                        </form>
                                    </div>
                                </div>

                                <div class="card card-outline card-secondary" id="rd-panel-cuentas">
                                    <div class="card-header py-2">
                                        <strong>Cuentas del rubro</strong>
                                        <span class="badge badge-info" id="rd-cuentas-count">0</span>
                                    </div>
                                    <div class="card-body">
                                        <p class="small text-muted" id="rd-cuentas-hint">
                                            Seleccione un rubro tipo «Suma de cuentas» para asignar cuentas del plan.
                                        </p>
                                        <div id="rd-cuentas-form" class="d-none mb-3">
                                            @if ($puede_actualizar)
                                                <div class="form-row align-items-end">
                                                    <div class="form-group col-md-4 mb-2">
                                                        <label class="small mb-0">Código desde</label>
                                                        <div class="input-group input-group-sm tm-cuentacontable-campo">
                                                            <input type="hidden" class="cuentacontable_id" id="rd_cuentacontable_id" value="">
                                                            <input type="text" class="form-control codigocuentacontable" id="rd_codigo_cuenta" placeholder="111010001">
                                                            <div class="input-group-append">
                                                                <button type="button" class="btn btn-outline-secondary consultacuentacontable" title="Buscar">
                                                                    <i class="fa fa-search"></i>
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <input type="hidden" id="empresa_id" value="{{ optional($empresa_query->first())->id }}">
                                                    </div>
                                                    <div class="form-group col-md-2 mb-2">
                                                        <label class="small mb-0">Hasta (rango)</label>
                                                        <input type="number" class="form-control form-control-sm" id="rd_codigo_hasta" min="1" placeholder="Opcional">
                                                    </div>
                                                    <div class="form-group col-md-3 mb-2">
                                                        <label class="small mb-0">Descripción</label>
                                                        <input type="text" class="form-control form-control-sm descripcioncuentacontable" id="rd_nombre_cuenta" readonly>
                                                    </div>
                                                    <div class="form-group col-md-2 mb-2">
                                                        <label class="small mb-0">Signo</label>
                                                        <select class="form-control form-control-sm" id="rd_signo">
                                                            <option value="1">+</option>
                                                            <option value="-1">−</option>
                                                        </select>
                                                    </div>
                                                    <div class="form-group col-md-1 mb-2">
                                                        <button type="button" class="btn btn-success btn-sm btn-block" id="rd-btn-add-cuenta" title="Agregar">
                                                            <i class="fa fa-plus"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered mb-0">
                                                <thead style="background:#85C1E9;color:#17202A;">
                                                    <tr>
                                                        <th>Cuenta</th>
                                                        <th>Nombre</th>
                                                        <th>Origen</th>
                                                        <th>Signo</th>
                                                        <th></th>
                                                    </tr>
                                                </thead>
                                                <tbody id="rd-cuentas-tbody">
                                                    <tr><td colspan="5" class="text-muted text-center">Sin cuentas</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-cobertura" role="tabpanel">
                        @include('contable.reporte_definible.partials.cobertura_plan', [
                            'cobertura' => $cobertura ?? null,
                            'puede_actualizar' => $puede_actualizar ?? false,
                        ])
                    </div>

                    <div class="tab-pane fade" id="tab-preview" role="tabpanel">
                        <div class="form-row align-items-end mb-3">
                            <div class="form-group col-md-2 mb-0">
                                <label class="small mb-0">Mes</label>
                                <select id="rd-preview-mes" class="form-control form-control-sm">
                                    @for ($m = 1; $m <= 12; $m++)
                                        <option value="{{ $m }}" @if ((int)date('n') === $m) selected @endif>{{ sprintf('%02d', $m) }}</option>
                                    @endfor
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-0">
                                <label class="small mb-0">Año</label>
                                <input type="number" id="rd-preview-anio" class="form-control form-control-sm" value="{{ date('Y') }}" min="2000" max="2100">
                            </div>
                            <div class="form-group col-md-4 mb-0">
                                <label class="small mb-0">Layout</label>
                                <select id="rd-preview-layout" class="form-control form-control-sm">
                                    <option value="">Default / FULL_GERENCIAL</option>
                                    @foreach ($layouts_disponibles ?? [] as $lay)
                                        <option value="{{ $lay->id }}">{{ $lay->codigo }} — {{ $lay->nombre }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group col-md-2 mb-0">
                                <button type="button" class="btn btn-info btn-sm btn-block" id="rd-btn-preview">
                                    <i class="fa fa-play"></i> Calcular
                                </button>
                            </div>
                        </div>
                        <div id="rd-preview-adv" class="small text-muted mb-2"></div>
                        <div class="table-responsive" style="max-height:480px;overflow:auto">
                            <table class="table table-sm table-hover mb-0" id="rd-preview-table">
                                <thead style="background:#85C1E9;color:#17202A;" id="rd-preview-thead"></thead>
                                <tbody id="rd-preview-tbody">
                                    <tr><td class="text-muted text-center">Pulse Calcular para ver saldos de prueba.</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-layouts" role="tabpanel">
                        <div class="rd-help-box">
                            <strong>Layouts de columnas (estilo Report Painter).</strong>
                            Cloná un preset de sistema al informe, o creá uno vacío.
                            Solo layouts del informe son editables: renombrar, default, columnas (Actual / YTD / Año ant. / Offset / Plan / Var / % / fórmula).
                            Al ejecutar se elige el layout o se usa el marcado como default.
                        </div>
                        <div class="row">
                            <div class="col-md-5">
                                <h6>Presets de sistema</h6>
                                <div class="table-responsive mb-3">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr><th>Código</th><th>Nombre</th><th></th></tr>
                                        </thead>
                                        <tbody id="rd-layouts-sistema"></tbody>
                                    </table>
                                </div>
                                <h6>Layouts del informe</h6>
                                @if ($puede_actualizar)
                                    <div class="form-row align-items-end mb-2">
                                        <div class="form-group col-4 mb-0">
                                            <label class="small mb-0">Código</label>
                                            <input type="text" id="rd-layout-nuevo-codigo" class="form-control form-control-sm" maxlength="40" placeholder="MI_LAYOUT">
                                        </div>
                                        <div class="form-group col-5 mb-0">
                                            <label class="small mb-0">Nombre</label>
                                            <input type="text" id="rd-layout-nuevo-nombre" class="form-control form-control-sm" maxlength="80">
                                        </div>
                                        <div class="form-group col-3 mb-0">
                                            <button type="button" class="btn btn-outline-primary btn-sm btn-block" id="rd-btn-crear-layout">Crear</button>
                                        </div>
                                    </div>
                                @endif
                                <div class="table-responsive">
                                    <table class="table table-sm table-bordered mb-0">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr><th>Código</th><th>Nombre</th><th>Def</th><th></th></tr>
                                        </thead>
                                        <tbody id="rd-layouts-informe"></tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-md-7">
                                <div class="card card-outline card-info">
                                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                        <strong id="rd-layout-detalle-titulo">Columnas</strong>
                                        <span class="text-muted small" id="rd-layout-detalle-hint">Elegí un layout del informe</span>
                                    </div>
                                    <div class="card-body">
                                        <div id="rd-layout-detalle-vacio" class="rd-empty py-3">Seleccioná un layout del informe para editar columnas.</div>
                                        <div id="rd-layout-detalle" class="d-none">
                                            @if ($puede_actualizar)
                                                <div class="form-row align-items-end mb-3">
                                                    <div class="form-group col-md-4 mb-0">
                                                        <label class="small mb-0">Nombre layout</label>
                                                        <input type="text" id="rd-layout-nombre" class="form-control form-control-sm" maxlength="80">
                                                    </div>
                                                    <div class="form-group col-md-3 mb-0">
                                                        <button type="button" class="btn btn-primary btn-sm" id="rd-btn-guardar-layout">Guardar</button>
                                                        <button type="button" class="btn btn-outline-success btn-sm" id="rd-btn-default-layout" title="Default del informe">Default</button>
                                                    </div>
                                                    <div class="form-group col-md-5 mb-0 text-right">
                                                        <select id="rd-col-tipo" class="form-control form-control-sm d-inline-block" style="width:55%">
                                                            @foreach (($tipos_columna_layout ?? []) as $tk => $tl)
                                                                <option value="{{ $tk }}">{{ $tl }}</option>
                                                            @endforeach
                                                        </select>
                                                        <button type="button" class="btn btn-success btn-sm" id="rd-btn-add-columna">+ Columna</button>
                                                    </div>
                                                </div>
                                            @endif
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered mb-0">
                                                    <thead style="background:#85C1E9;color:#17202A;">
                                                        <tr>
                                                            <th style="width:60px">Orden</th>
                                                            <th>Key</th>
                                                            <th>Etiqueta</th>
                                                            <th>Tipo</th>
                                                            <th style="min-width:140px">Meta</th>
                                                            <th style="width:90px"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="rd-layout-columnas"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-consolidacion" role="tabpanel">
                        <div class="rd-help-box">
                            <strong>Consolidación 2.0.</strong>
                            Con 2+ empresas y «Consolidar»: se aplica % de participación por empresa,
                            eliminaciones IC (todas o por pareja) y, si se elige, TC de cierre.
                            Sin filas de %, cada empresa entra al 100%.
                        </div>

                        <h6 class="mt-2">Participación %</h6>
                        @if ($puede_actualizar)
                            <div class="form-row align-items-end mb-2">
                                <div class="form-group col-md-3 mb-0">
                                    <label class="small mb-0">Empresa</label>
                                    <select id="rd-part-empresa" class="form-control form-control-sm">
                                        <option value="">—</option>
                                        @foreach ($empresa_query as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->codigo ?? $emp->id }} — {{ $emp->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">%</label>
                                    <input type="number" id="rd-part-pct" class="form-control form-control-sm" min="0" max="100" step="0.01" value="100">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">Vigente desde</label>
                                    <input type="date" id="rd-part-desde" class="form-control form-control-sm">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">Hasta</label>
                                    <input type="date" id="rd-part-hasta" class="form-control form-control-sm">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <button type="button" class="btn btn-primary btn-sm btn-block" id="rd-btn-add-part">Guardar %</button>
                                </div>
                            </div>
                        @endif
                        <div class="table-responsive mb-4">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr><th>Empresa</th><th>%</th><th>Vigencia</th><th></th></tr>
                                </thead>
                                <tbody id="rd-part-tbody"></tbody>
                            </table>
                        </div>

                        <h6>Eliminaciones IC</h6>
                        @if ($puede_actualizar)
                            <div class="form-row align-items-end mb-3">
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">Nombre</label>
                                    <input type="text" id="rd-eli-nombre" class="form-control form-control-sm" maxlength="80" placeholder="Ctas. intercompany">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">Código desde</label>
                                    <input type="number" id="rd-eli-desde" class="form-control form-control-sm" min="1">
                                </div>
                                <div class="form-group col-md-1 mb-0">
                                    <label class="small mb-0">Hasta</label>
                                    <input type="number" id="rd-eli-hasta" class="form-control form-control-sm" min="1">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">Ámbito</label>
                                    <select id="rd-eli-ambito" class="form-control form-control-sm">
                                        <option value="todas">Todas las empresas</option>
                                        <option value="pareja">Pareja</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-2 mb-0 rd-eli-pareja d-none">
                                    <label class="small mb-0">Empresa A</label>
                                    <select id="rd-eli-emp-a" class="form-control form-control-sm">
                                        <option value="">—</option>
                                        @foreach ($empresa_query as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->codigo ?? $emp->id }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2 mb-0 rd-eli-pareja d-none">
                                    <label class="small mb-0">Empresa B</label>
                                    <select id="rd-eli-emp-b" class="form-control form-control-sm">
                                        <option value="">—</option>
                                        @foreach ($empresa_query as $emp)
                                            <option value="{{ $emp->id }}">{{ $emp->codigo ?? $emp->id }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-1 mb-0">
                                    <button type="button" class="btn btn-primary btn-sm btn-block" id="rd-btn-add-eli">
                                        <i class="fa fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                        @endif
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered">
                                <thead style="background:#85C1E9;color:#17202A;">
                                    <tr>
                                        <th>Nombre</th>
                                        <th>Cuentas</th>
                                        <th>Ámbito</th>
                                        <th>Activo</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="rd-eli-tbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-versiones" role="tabpanel">
                        <p class="text-muted small">
                            Publicar congela la estructura actual (rubros/cuentas). Restaurar reemplaza el árbol actual.
                            Versión actual: <strong>{{ (int)($data->version_actual ?? 0) }}</strong>
                            · estado <strong>{{ $data->estado_publicacion ?? 'borrador' }}</strong>
                            @if ($data->publicado_at)
                                · última publicación {{ $data->publicado_at->format('d/m/Y H:i') }}
                            @endif
                        </p>
                        <div class="mb-2">
                            <button type="button" class="btn btn-outline-info btn-sm" id="rd-btn-validar">
                                <i class="fa fa-check-double"></i> Validar definición
                            </button>
                            <pre id="rd-validar-out" class="small mt-2 d-none" style="max-height:160px;overflow:auto;background:#f8f9fa;padding:.5rem"></pre>
                        </div>
                        @if ($puede_actualizar)
                            <form method="post" action="{{ route('publicar_version_reporte_definible', $data->id) }}" class="form-inline mb-3">
                                @csrf
                                <input type="text" name="nombre" class="form-control form-control-sm mr-2" placeholder="Nombre opcional" maxlength="120">
                                <button type="submit" class="btn btn-primary btn-sm"
                                        onclick="return confirm('¿Publicar versión de la estructura actual?');">
                                    <i class="fa fa-check"></i> Publicar versión
                                </button>
                            </form>
                        @endif
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Versión</th>
                                    <th>Nombre</th>
                                    <th>Fecha</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($versiones ?? [] as $ver)
                                    <tr>
                                        <td>{{ $ver->version }}</td>
                                        <td>{{ $ver->nombre }}</td>
                                        <td>{{ optional($ver->created_at)->format('d/m/Y H:i') }}</td>
                                        <td>
                                            @if ($puede_actualizar)
                                                <button type="button" class="btn btn-outline-info btn-sm rd-diff-ver" data-id="{{ $ver->id }}">Diff</button>
                                                <form method="post" class="d-inline" action="{{ route('restaurar_version_reporte_definible', ['id' => $data->id, 'versionId' => $ver->id]) }}"
                                                      onsubmit="return confirm('¿Restaurar versión {{ $ver->version }}? Se reemplaza la estructura actual.');">
                                                    @csrf
                                                    <button type="submit" class="btn btn-outline-warning btn-sm">Restaurar</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">Sin versiones publicadas.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="tab-cabecera" role="tabpanel">
                        <form method="post" action="{{ route('actualizar_reporte_definible', ['id' => $data->id]) }}" class="form-horizontal" id="form-cabecera-rd">
                            @csrf
                            @method('PUT')
                            @include('contable.reporte_definible.partials.form_cabecera', [
                                'data' => $data,
                                'tiposReporte' => $tiposReporte,
                            ])
                            @if ($puede_actualizar)
                                <button type="submit" class="btn btn-primary">
                                    <i class="fa fa-save"></i> Actualizar cabecera
                                </button>
                            @endif
                        </form>
                    </div>

                    <div class="tab-pane fade" id="tab-acceso" role="tabpanel">
                        <div class="card card-outline card-info">
                            <div class="card-header py-2"><strong>ACL</strong></div>
                            <div class="card-body">
                                <p class="text-muted small mb-2">Sin usuarios = acceso libre (según permisos). Con usuarios = solo esos.</p>
                                @if ($puede_actualizar)
                                    <form method="post" action="{{ route('sync_accesos_reporte_definible', ['id' => $data->id]) }}">
                                        @csrf
                                        <select name="usuario_ids[]" id="rd-acceso-usuarios" class="form-control" multiple size="10">
                                            @foreach ($usuariosAcl ?? [] as $u)
                                                <option value="{{ $u->id }}" {{ in_array((int) $u->id, $aclUsuarios ?? [], true) ? 'selected' : '' }}>
                                                    {{ $u->nombre }} ({{ $u->usuario }})
                                                </option>
                                            @endforeach
                                        </select>
                                        <p class="text-muted small mt-1 mb-0">Ctrl o Cmd para marcar varios. Vaciar la selección deja el informe abierto por rol.</p>
                                        <button type="submit" class="btn btn-primary btn-sm mt-2">Guardar ACL</button>
                                    </form>
                                @elseif (count($aclUsuarios ?? []) === 0)
                                    <p class="text-muted mb-0">Sin restricción (acceso abierto por rol).</p>
                                @else
                                    <ul class="small mb-0">
                                        @foreach ($usuariosAcl ?? [] as $u)
                                            @if (in_array((int) $u->id, $aclUsuarios ?? [], true))
                                                <li>{{ $u->nombre }} ({{ $u->usuario }})</li>
                                            @endif
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-alertas" role="tabpanel">
                        <div class="rd-help-box">
                            Alertas post-corrida (aparecen como avisos al ejecutar).
                            La <strong>ecuación contable</strong> valida que una expresión entre códigos de línea dé cero:
                            por ejemplo <code>R001-(R050+R080)</code> para Activo = Pasivo + Patrimonio Neto.
                            El umbral es la tolerancia en pesos (0 usa 0,01).
                        </div>
                        @if ($puede_actualizar)
                            <div class="form-row align-items-end mb-2">
                                <div class="form-group col-md-3 mb-0">
                                    <label class="small mb-0">Tipo</label>
                                    <select id="rd-alerta-tipo" class="form-control form-control-sm">
                                        @foreach ($tipos_alerta ?? [] as $tk => $tl)
                                            <option value="{{ $tk }}">{{ $tl }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-3 mb-0 rd-alerta-ecuacion-campo" style="display:none;">
                                    <label class="small mb-0">Ecuación</label>
                                    <input type="text" id="rd-alerta-expresion" class="form-control form-control-sm"
                                           placeholder="R001-(R050+R080)">
                                </div>
                                <div class="form-group col-md-3 mb-0 rd-alerta-ecuacion-campo" style="display:none;">
                                    <label class="small mb-0">Etiqueta</label>
                                    <input type="text" id="rd-alerta-etiqueta" class="form-control form-control-sm"
                                           placeholder="Activo = Pasivo + PN">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <label class="small mb-0">Umbral</label>
                                    <input type="number" id="rd-alerta-umbral" class="form-control form-control-sm" step="0.01" value="10">
                                </div>
                                <div class="form-group col-md-2 mb-0">
                                    <button type="button" class="btn btn-primary btn-sm btn-block" id="rd-btn-add-alerta">Agregar</button>
                                </div>
                            </div>
                        @endif
                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr><th>Tipo</th><th>Ecuación / etiqueta</th><th>Umbral</th><th>Activo</th><th></th></tr>
                            </thead>
                            <tbody id="rd-alerta-tbody"></tbody>
                        </table>
                    </div>

                    <div class="tab-pane fade" id="tab-distribucion" role="tabpanel">
                        <div class="rd-help-box">
                            El informe sale solo por mail el día y la hora que indiques, sin que nadie tenga que entrar
                            a ejecutarlo. El período se corre en cada envío: con <strong>mes anterior</strong>, el envío
                            del 5 de marzo trae febrero. Los filtros que se guardan son los de la pantalla de ejecución
                            (empresas, layout, base de saldo); si querés otros, ejecutá el informe como lo necesitás y
                            volvé a capturarlos desde acá.
                        </div>

                        @if ($puede_actualizar)
                            <div class="card card-outline card-primary">
                                <div class="card-header py-2">
                                    <strong id="rd-susc-titulo">Nuevo envío</strong>
                                    <button type="button" class="btn btn-outline-secondary btn-sm float-right d-none"
                                            id="rd-btn-susc-cancelar">Cancelar edición</button>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" id="rd-susc-id" value="">
                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label class="small mb-0">Nombre del envío</label>
                                            <input type="text" id="rd-susc-nombre" class="form-control form-control-sm"
                                                   maxlength="160" placeholder="Balance mensual a Dirección">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="small mb-0">Cada cuánto</label>
                                            <select id="rd-susc-periodicidad" class="form-control form-control-sm">
                                                @foreach ($periodicidades_suscripcion ?? [] as $k => $label)
                                                    <option value="{{ $k }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2" id="rd-susc-diames-wrap">
                                            <label class="small mb-0">Día del mes</label>
                                            <input type="number" id="rd-susc-dia-mes" class="form-control form-control-sm"
                                                   min="1" max="28" value="5">
                                        </div>
                                        <div class="form-group col-md-2 d-none" id="rd-susc-diasemana-wrap">
                                            <label class="small mb-0">Día</label>
                                            <select id="rd-susc-dia-semana" class="form-control form-control-sm">
                                                @foreach ($dias_semana_suscripcion ?? [] as $k => $label)
                                                    <option value="{{ $k }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-2">
                                            <label class="small mb-0">Hora</label>
                                            <input type="time" id="rd-susc-hora" class="form-control form-control-sm" value="07:00">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-4">
                                            <label class="small mb-0">Período de cada envío</label>
                                            <select id="rd-susc-periodo" class="form-control form-control-sm">
                                                @foreach ($periodos_relativos_suscripcion ?? [] as $k => $label)
                                                    <option value="{{ $k }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="small mb-0">Adjunto</label>
                                            <select id="rd-susc-formato" class="form-control form-control-sm">
                                                @foreach ($formatos_suscripcion ?? [] as $k => $label)
                                                    <option value="{{ $k }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-5">
                                            <label class="small mb-0">Mails destino (separados por coma)</label>
                                            <input type="text" id="rd-susc-destinatarios" class="form-control form-control-sm"
                                                   placeholder="direccion@empresa.com, contador@estudio.com">
                                        </div>
                                    </div>

                                    <div class="form-row">
                                        <div class="form-group col-md-7">
                                            <label class="small mb-0">Mensaje en el cuerpo del mail (opcional)</label>
                                            <textarea id="rd-susc-mensaje" class="form-control form-control-sm" rows="2"
                                                      maxlength="2000" placeholder="Cierre provisorio, sujeto a ajuste de inflación."></textarea>
                                        </div>
                                        <div class="form-group col-md-5">
                                            <div class="custom-control custom-checkbox mt-4">
                                                <input type="checkbox" class="custom-control-input" id="rd-susc-publicar">
                                                <label class="custom-control-label small" for="rd-susc-publicar">
                                                    Publicar el resultado al enviarlo (queda reimprimible idéntico)
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="rd-susc-solo-alertas">
                                                <label class="custom-control-label small" for="rd-susc-solo-alertas">
                                                    Enviar solo si la corrida trae avisos
                                                </label>
                                            </div>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" class="custom-control-input" id="rd-susc-activo" checked>
                                                <label class="custom-control-label small" for="rd-susc-activo">Activo</label>
                                            </div>
                                        </div>
                                    </div>

                                    <button type="button" class="btn btn-primary btn-sm" id="rd-btn-susc-guardar">
                                        <i class="fa fa-save"></i> Guardar envío
                                    </button>
                                    <span class="small text-muted ml-2">
                                        Se guardan los filtros de ejecución actuales del informe.
                                    </span>
                                </div>
                            </div>
                        @endif

                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Envío</th>
                                    <th>Cuándo</th>
                                    <th>Qué manda</th>
                                    <th>Destinatarios</th>
                                    <th>Última corrida</th>
                                    <th style="width:110px;"></th>
                                </tr>
                            </thead>
                            <tbody id="rd-susc-tbody"></tbody>
                        </table>
                        <p class="small text-muted mb-0">
                            El envío programado lo dispara <code>contable:distribuir-reportes-definibles</code>, que corre
                            cada hora. «Probar» manda el mail ahora mismo con la configuración guardada.
                        </p>
                    </div>

                    <div class="tab-pane fade" id="tab-notas" role="tabpanel">
                        <div class="rd-help-box">
                            Las notas son las aclaraciones al pie del estado: «criterio de valuación», «incluye ajuste
                            por inflación hasta…», «saldo en litigio». Se cuelgan de una línea del informe y salen como
                            llamada numerada en pantalla, PDF y Excel. Cada edición <strong>versiona</strong>: el texto
                            anterior queda guardado, así un balance publicado el año pasado conserva la nota que llevaba
                            en ese momento. Con la vigencia por período se puede escribir una nota que solo aparezca en
                            los cierres de un rango de meses.
                        </div>

                        @if ($puede_actualizar)
                            <div class="card card-outline card-primary">
                                <div class="card-header py-2">
                                    <strong id="rd-nota-titulo">Nueva nota</strong>
                                    <button type="button" class="btn btn-outline-secondary btn-sm float-right d-none"
                                            id="rd-btn-nota-cancelar">Cancelar edición</button>
                                </div>
                                <div class="card-body">
                                    <input type="hidden" id="rd-nota-id" value="">
                                    <div class="form-row">
                                        <div class="form-group col-md-6">
                                            <label class="small mb-0">Línea del informe</label>
                                            <select id="rd-nota-rubro" class="form-control form-control-sm">
                                                <option value="">Nota general (sin línea)</option>
                                                @foreach ($notas_lineas ?? [] as $linea)
                                                    <option value="{{ $linea['rubro_id'] }}">{{ $linea['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="small mb-0">Vigente desde (AAAAMM)</label>
                                            <input type="text" id="rd-nota-desde" class="form-control form-control-sm"
                                                   maxlength="7" placeholder="202601">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="small mb-0">Vigente hasta (AAAAMM)</label>
                                            <input type="text" id="rd-nota-hasta" class="form-control form-control-sm"
                                                   maxlength="7" placeholder="202612">
                                        </div>
                                    </div>
                                    <div class="form-row">
                                        <div class="form-group col-md-9">
                                            <label class="small mb-0">Texto de la nota</label>
                                            <textarea id="rd-nota-texto" class="form-control form-control-sm" rows="3"
                                                      maxlength="4000"
                                                      placeholder="Los bienes de uso se valúan a costo de adquisición reexpresado."></textarea>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label class="small mb-0">Orden</label>
                                            <input type="number" id="rd-nota-orden" class="form-control form-control-sm" step="1">
                                            <div class="custom-control custom-checkbox mt-2">
                                                <input type="checkbox" class="custom-control-input" id="rd-nota-activo" checked>
                                                <label class="custom-control-label small" for="rd-nota-activo">
                                                    Mostrar en el informe
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-primary btn-sm" id="rd-btn-nota-guardar">
                                        <i class="fa fa-save"></i> Guardar nota
                                    </button>
                                    <span class="small text-muted ml-2">
                                        Vigencia vacía = la nota sale en todos los períodos.
                                    </span>
                                </div>
                            </div>
                        @endif

                        <table class="table table-sm table-bordered">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th style="width:220px;">Línea</th>
                                    <th>Nota</th>
                                    <th style="width:110px;">Vigencia</th>
                                    <th style="width:70px;">Versión</th>
                                    <th style="width:70px;">Muestra</th>
                                    <th style="width:110px;"></th>
                                </tr>
                            </thead>
                            <tbody id="rd-nota-tbody"></tbody>
                        </table>
                        <p class="small text-muted mb-0">
                            La numeración de las llamadas la arma el informe al ejecutarse, siguiendo el orden en que
                            aparecen las líneas: no hace falta mantenerla a mano.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Modal nuevo rubro --}}
<div class="modal fade" id="rd-modal-nuevo" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <form id="rd-form-nuevo">
                <div class="modal-header">
                    <h5 class="modal-title">Nuevo rubro</h5>
                    <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="rd-nuevo-parent-id" value="">
                    <p class="small text-muted" id="rd-nuevo-parent-hint"></p>
                    <div class="form-group">
                        <label>Nombre</label>
                        <input type="text" class="form-control" id="rd-nuevo-nombre" required maxlength="80">
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select class="form-control" id="rd-nuevo-tipo">
                            @foreach ($tiposRubro as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Agregar</button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Modal historial de una nota --}}
<div class="modal fade" id="rd-modal-nota-historial" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Historial de la nota</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <p class="small text-muted">
                    Cada fila es el texto tal como estaba escrito en ese momento. La versión vigente es la que sale hoy
                    en el informe; las anteriores quedan para justificar un balance ya presentado.
                </p>
                <table class="table table-sm table-bordered mb-0">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th style="width:70px;">Versión</th>
                            <th>Texto</th>
                            <th style="width:110px;">Vigencia</th>
                            <th style="width:150px;">Quién / cuándo</th>
                        </tr>
                    </thead>
                    <tbody id="rd-nota-historial-tbody"></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

@include('includes.contable.modalconsultacuentacontable')
@endsection
