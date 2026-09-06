@extends("theme.$theme.layout")
@section('titulo')
    Nueva suscripción
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/cuentacontable/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/compras/proveedor/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script>
(function () {
    // Dueño / responsable pueden ser de cualquier empresa asignada al usuario.
    window._consultaUsuarioOmitirFiltroEmpresaFijo = true;
    window._consultaUsuarioOmitirFiltroEmpresa = true;

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }
        if (typeof activa_eventos_consulta_cuentacontable === 'function') {
            activa_eventos_consulta_cuentacontable();
        }
        if (typeof activa_eventos_consultaproveedor === 'function') {
            activa_eventos_consultaproveedor();
        }
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
    });
})();
</script>
<script>
(function () {
    const monto = document.getElementById('inp-monto');
    const tolerancia = document.getElementById('inp-tolerancia');
    const tope = document.getElementById('txt-tope');

    function recalcularTope() {
        const m = parseFloat(monto.value);
        const t = parseFloat(tolerancia.value);
        if (isNaN(m) || m <= 0) {
            tope.value = '—';
            return;
        }
        const valor = m * (1 + (isNaN(t) ? 0 : t) / 100);
        tope.value = valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    monto.addEventListener('input', recalcularTope);
    tolerancia.addEventListener('input', recalcularTope);
    recalcularTope();

    // La tarjeta del maestro completa los últimos 4 y los deja fijos.
    const selTarjeta = document.getElementById('sel-tarjeta');
    const inpUlt4 = document.getElementById('inp-ult4');
    selTarjeta.addEventListener('change', function () {
        const ult4 = this.options[this.selectedIndex].dataset.ult4 || '';
        if (ult4) {
            inpUlt4.value = ult4;
            inpUlt4.readOnly = true;
        } else {
            inpUlt4.readOnly = false;
        }
    });
    selTarjeta.dispatchEvent(new Event('change'));

    // Quién autoriza según empresa + centro de costo (mapa del árbol SU).
    const aprobadoresMapa = @json($aprobadores_mapa ?? []);
    const selEmpresa = document.getElementById('empresa_id');
    const inpCcId = document.getElementById('centrocosto_id');
    const txtAprobador = document.getElementById('txt-aprobador');

    function refrescarAprobador() {
        const empresaId = selEmpresa ? String(selEmpresa.value || '') : '';
        const ccId = String(inpCcId.value || '');
        if (!ccId) {
            txtAprobador.value = 'Elegí el centro de costo para ver quién autoriza';
            return;
        }
        const nombre = aprobadoresMapa[empresaId + ':' + ccId] || '';
        if (nombre) {
            txtAprobador.value = nombre + ' — gerente del sector · nivel único';
        } else {
            txtAprobador.value = 'Sin gerente configurado para este centro de costo';
        }
    }

    function limpiarProveedor() {
        jQuery('#proveedor_id').val('');
        jQuery('#codigoproveedor').val('');
        jQuery('#nombreproveedor').val('');
    }

    if (window.jQuery) {
        jQuery(inpCcId).on('change', refrescarAprobador);
        jQuery(selEmpresa).on('change', function () {
            if (typeof limpiarCuentaContableEnContexto === 'function') {
                limpiarCuentaContableEnContexto(jQuery('.tm-cuentacontable-campo').first());
            } else {
                jQuery('#contrato_cuentacontable_id').val('');
                jQuery('#contrato_cuentacontable_codigo').val('');
                jQuery('#contrato_cuentacontable_nombre').val('');
            }
            limpiarProveedor();
            refrescarAprobador();
        });
    } else {
        inpCcId.addEventListener('change', refrescarAprobador);
        selEmpresa.addEventListener('change', refrescarAprobador);
    }
    refrescarAprobador();

    // Dropzone: el input file real queda oculto y se alimenta por arrastre o clic.
    const zona = document.getElementById('dropzone-suscripcion');
    const inputArchivos = document.getElementById('inp-archivos');
    const lista = document.getElementById('lista-archivos');
    const badge = document.getElementById('badge-archivos');

    function refrescarLista() {
        lista.innerHTML = '';
        const archivos = Array.from(inputArchivos.files);
        badge.textContent = archivos.length;
        archivos.forEach(function (f) {
            const li = document.createElement('li');
            li.className = 'list-group-item py-1 px-2 d-flex justify-content-between';
            li.innerHTML = '<span>' + f.name + '</span><small class="text-muted">' +
                (f.size / 1024).toFixed(0) + ' KB</small>';
            lista.appendChild(li);
        });
    }

    zona.addEventListener('click', () => inputArchivos.click());
    inputArchivos.addEventListener('change', refrescarLista);

    ['dragenter', 'dragover'].forEach(function (evento) {
        zona.addEventListener(evento, function (e) {
            e.preventDefault();
            zona.classList.add('bg-white', 'border-primary');
        });
    });
    ['dragleave', 'drop'].forEach(function (evento) {
        zona.addEventListener(evento, function (e) {
            e.preventDefault();
            zona.classList.remove('bg-white', 'border-primary');
        });
    });
    zona.addEventListener('drop', function (e) {
        inputArchivos.files = e.dataTransfer.files;
        refrescarLista();
    });
})();
</script>
@endsection

@section('contenido')
@php
    $ccId = (int) old('centrocosto_id', 0);
    $ccCodigo = old('centrocosto_codigo', '');
    $ccNombre = old('centrocosto_nombre', '');
    $cuentaId = (int) old('contrato_cuentacontable_id', 0);
    $cuentaCodigo = old('contrato_cuentacontable_codigo', '');
    $cuentaNombre = old('contrato_cuentacontable_nombre', '');
    $proveedorId = (int) old('proveedor_id', 0);
    $proveedorCodigo = old('codigoproveedor', '');
    $proveedorNombre = old('nombreproveedor', '');
    $ownerId = (int) old('suscripcion_owner_usuario_id', 0);
    $ownerCodigo = old('suscripcion_owner_usuario_codigo', '');
    $ownerNombre = old('suscripcion_owner_usuario_nombre', '');
    $respId = (int) old('contrato_responsable_id', 0);
    $respCodigo = old('contrato_responsable_usuario_codigo', '');
    $respNombre = old('contrato_responsable_usuario_nombre', '');
@endphp
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Nueva suscripción / OC abierta</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_suscripcion') }}" class="btn btn-outline-light btn-sm">← Volver al listado</a>
                </div>
            </div>
            <form method="post" action="{{ route('guardar_suscripcion') }}" enctype="multipart/form-data" id="form-suscripcion" autocomplete="off">
                @csrf

                <ul class="nav nav-tabs px-3 pt-2" role="tablist">
                    <li class="nav-item">
                        <a class="nav-link active" data-toggle="tab" href="#tab-datos" role="tab">Datos principales</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" data-toggle="tab" href="#tab-archivos" role="tab">
                            Archivos <span class="badge badge-secondary" id="badge-archivos">0</span>
                        </a>
                    </li>
                </ul>

                <div class="tab-content">
                    <div class="tab-pane fade show active" id="tab-datos" role="tabpanel">
                        <div class="card-body">
                            <p class="text-muted small">
                                Se genera una OC marcada como contrato, sin recepción, con cuenta del contrato.
                                Al enviar entra al árbol de <strong>Suscripciones</strong>: la autoriza el gerente
                                del sector en un nivel único (en Anita el N° de OC se asigna al crear).
                            </p>

                            <h5 class="mb-3">Datos principales</h5>
                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Suscripción <span class="text-danger">*</span></label>
                                    <input type="text" name="suscripcion_nombre" class="form-control" required maxlength="180"
                                        value="{{ old('suscripcion_nombre') }}" placeholder="Ej: Canva Pro">
                                </div>
                                <div class="form-group col-md-6">
                                    <label>Proveedor <span class="text-danger">*</span></label>
                                    <input type="hidden" id="proveedor_id" name="proveedor_id" class="proveedor_id"
                                           value="{{ $proveedorId ?: '' }}" required>
                                    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                                        <input type="text" class="form-control codigoproveedor" id="codigoproveedor"
                                               name="codigoproveedor" value="{{ $proveedorCodigo }}"
                                               placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;"
                                               title="Código + Enter; F1 o lupa">
                                        <button type="button" title="Consulta proveedores (F1)"
                                                class="btn-accion-tabla consultaproveedor tooltipsC flex-shrink-0">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" class="form-control nombreproveedor" id="nombreproveedor"
                                               name="nombreproveedor" value="{{ $proveedorNombre }}"
                                               placeholder="Nombre del proveedor" readonly style="min-width:0;flex:1 1 auto;">
                                    </div>
                                    <small class="text-muted">Código + Enter · <kbd>F1</kbd> o lupa</small>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Empresa <span class="text-danger">*</span></label>
                                    <select name="empresa_id" id="empresa_id" class="form-control" required>
                                        @foreach ($empresa_query as $emp)
                                            <option value="{{ $emp->id }}" @selected((int)old('empresa_id', $empresa_default) === (int)$emp->id)>{{ $emp->nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Área solicitante <span class="text-danger">*</span></label>
                                    <select name="suscripcion_area" class="form-control" required>
                                        @foreach ($areas as $area)
                                            <option value="{{ $area }}" @selected(old('suscripcion_area') === $area)>{{ $area }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-4">
                                    <label>Centro de costo <span class="text-danger">*</span></label>
                                    <div class="tm-centrocosto-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                        <input type="hidden" name="centrocosto_id" id="centrocosto_id" class="centrocosto_id" value="{{ $ccId ?: '' }}" required>
                                        <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" name="centrocosto_codigo" id="centrocosto_codigo"
                                               class="form-control codigocentrocosto" value="{{ $ccCodigo }}"
                                               placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;"
                                               title="Código + Enter; F1 o lupa">
                                        <input type="text" name="centrocosto_nombre" id="centrocosto_descripcion"
                                               class="form-control descripcioncentrocosto" value="{{ $ccNombre }}"
                                               placeholder="Descripción" readonly style="min-width:0;flex:1 1 auto;">
                                    </div>
                                    <small class="text-muted">Código + Enter · <kbd>F1</kbd> o lupa</small>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="text-muted">Árbol de aprobación</label>
                                <input type="text" class="form-control-plaintext border rounded px-2 bg-light" readonly
                                       id="txt-aprobador" value="Elegí el centro de costo para ver quién autoriza">
                                <small class="form-text text-muted">
                                    Suscripciones · nivel único · gerente del sector.
                                    Se configura en <a href="{{ route('aprobadores_suscripcion') }}" target="_blank">Compras › Suscripciones › Aprobadores</a>.
                                </small>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-6">
                                    <label>Cuenta contable <span class="text-danger">*</span></label>
                                    <div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                        <input type="hidden" name="contrato_cuentacontable_id" id="contrato_cuentacontable_id"
                                               class="cuentacontable_id" value="{{ $cuentaId ?: '' }}" required>
                                        <button type="button" title="Consulta cuenta contable (F1)" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        @if (can('crear-cuentas-contables', false) || can('listar-cuentas-contables', false))
                                            <a href="{{ $cuentaId > 0 ? route('editar_cuentacontable', ['id' => $cuentaId, 'origen' => 'modal_consulta', 'vista' => 'consulta']) : '#' }}"
                                               target="_blank" rel="noopener"
                                               class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $cuentaId > 0 ? '' : 'd-none' }}"
                                               title="Consultar cuenta contable en ABM">
                                                <i class="fa fa-edit"></i>
                                            </a>
                                        @endif
                                        <input type="text" name="contrato_cuentacontable_codigo" id="contrato_cuentacontable_codigo"
                                               class="codigocuentacontable form-control" value="{{ $cuentaCodigo }}"
                                               placeholder="Cód." autocomplete="off" style="width:6.85rem;flex-shrink:0;"
                                               title="Código + Enter; F1 o lupa">
                                        <input type="text" name="contrato_cuentacontable_nombre" id="contrato_cuentacontable_nombre"
                                               class="nombrecuentacontable form-control" value="{{ $cuentaNombre }}"
                                               placeholder="Descripción" readonly style="min-width:0;flex:1 1 auto;">
                                    </div>
                                    <small class="text-muted">
                                        Código + Enter · <kbd>F1</kbd> o lupa
                                        @if (can('crear-cuentas-contables', false))
                                            · <a href="{{ route('crear_cuentacontable') }}" target="_blank">+ Agregar ítem contable</a>
                                        @endif
                                    </small>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Dueño del servicio <span class="text-danger">*</span></label>
                                    <div class="tm-usuario-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                        <input type="hidden" name="suscripcion_owner_usuario_id" id="suscripcion_owner_usuario_id"
                                               class="usuario_id" value="{{ $ownerId ?: '' }}" required>
                                        <input type="text" name="suscripcion_owner_usuario_codigo" id="suscripcion_owner_usuario_codigo"
                                               class="usuario_codigo_arbol form-control" value="{{ $ownerCodigo }}"
                                               placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;"
                                               title="Login o ID; Enter valida; F1 consulta">
                                        <button type="button" title="Consulta usuarios (F1)" class="btn-accion-tabla consultausuario tooltipsC"
                                                data-omitir_filtro_empresa="1">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" name="suscripcion_owner_usuario_nombre" id="suscripcion_owner_usuario_nombre"
                                               class="nombreusuario form-control" value="{{ $ownerNombre }}"
                                               placeholder="Nombre" readonly style="min-width:0;flex:1 1 auto;">
                                    </div>
                                    <small class="form-text text-muted">A quién se le pregunta si sigue en uso.</small>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Solicitante</label>
                                    <input type="text" name="suscripcion_solicitante" class="form-control" maxlength="120"
                                        value="{{ old('suscripcion_solicitante') }}" placeholder="Ej: M. Gómez">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label>Tarjeta corporativa</label>
                                    <select name="suscripcion_tarjeta_id" class="form-control" id="sel-tarjeta">
                                        <option value="">Sin maestro (cargar 4 dígitos a mano)</option>
                                        @foreach ($tarjeta_query as $t)
                                            <option value="{{ $t->id }}" data-ult4="{{ $t->ult4 }}" @selected((int)old('suscripcion_tarjeta_id') === (int)$t->id)>
                                                {{ $t->etiqueta }} ••{{ $t->ult4 }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Últimos 4 <span class="text-danger">*</span></label>
                                    <input type="text" name="suscripcion_tarjeta_ult4" class="form-control" required maxlength="4" pattern="\d{4}"
                                        id="inp-ult4" value="{{ old('suscripcion_tarjeta_ult4') }}" placeholder="4821">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Monto período <span class="text-danger">*</span></label>
                                    <input type="number" step="0.01" min="0.01" name="suscripcion_monto_periodo" class="form-control"
                                        required id="inp-monto" value="{{ old('suscripcion_monto_periodo') }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Moneda <span class="text-danger">*</span></label>
                                    <select name="contrato_moneda_id" class="form-control" required>
                                        @foreach ($moneda_query as $m)
                                            <option value="{{ $m->id }}" @selected((int)old('contrato_moneda_id') === (int)$m->id)>
                                                {{ $m->nombre ?? $m->abreviatura ?? $m->id }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="form-group col-md-2">
                                    <label>Periodicidad <span class="text-danger">*</span></label>
                                    <select name="suscripcion_periodicidad" class="form-control" required>
                                        <option value="mensual" @selected(old('suscripcion_periodicidad', 'mensual') === 'mensual')>Mensual</option>
                                        <option value="anual" @selected(old('suscripcion_periodicidad') === 'anual')>Anual</option>
                                    </select>
                                </div>
                            </div>

                            <hr>
                            <h5 class="mb-3">Vigencia y control</h5>
                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Próx. renovación <span class="text-danger">*</span></label>
                                    <input type="date" name="proxima_renovacion" class="form-control" required
                                        value="{{ old('proxima_renovacion') }}">
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Tolerancia %</label>
                                    <input type="number" step="0.01" min="0" max="100" name="suscripcion_tolerancia_pct" class="form-control"
                                        id="inp-tolerancia" value="{{ old('suscripcion_tolerancia_pct', $tolerancia_default) }}">
                                    <small class="form-text text-muted">Tope autorizado = monto × (1 + tolerancia).</small>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Tope autorizado por cargo</label>
                                    <input type="text" class="form-control-plaintext border rounded px-2 bg-light font-weight-bold"
                                           id="txt-tope" readonly value="—">
                                    <small class="form-text text-muted">Por encima de este importe el cargo vuelve al gerente.</small>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Responsable contrato</label>
                                    <div class="tm-usuario-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                        <input type="hidden" name="contrato_responsable_id" id="contrato_responsable_id"
                                               class="usuario_id" value="{{ $respId ?: '' }}">
                                        <input type="text" name="contrato_responsable_usuario_codigo" id="contrato_responsable_usuario_codigo"
                                               class="usuario_codigo_arbol form-control" value="{{ $respCodigo }}"
                                               placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;"
                                               title="Login o ID; Enter valida; F1 consulta">
                                        <button type="button" title="Consulta usuarios (F1)" class="btn-accion-tabla consultausuario tooltipsC"
                                                data-omitir_filtro_empresa="1">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" name="contrato_responsable_usuario_nombre" id="contrato_responsable_usuario_nombre"
                                               class="nombreusuario form-control" value="{{ $respNombre }}"
                                               placeholder="Usuario actual si vacío" readonly style="min-width:0;flex:1 1 auto;">
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group col-md-3">
                                    <label>Renovación automática</label>
                                    <select name="contrato_auto_renovable" class="form-control">
                                        <option value="1" @selected(old('contrato_auto_renovable', '1') == '1')>Sí</option>
                                        <option value="0" @selected(old('contrato_auto_renovable') == '0')>No</option>
                                    </select>
                                </div>
                                <div class="form-group col-md-3">
                                    <label>Días preaviso (si auto)</label>
                                    <input type="number" min="0" max="365" name="contrato_dias_preaviso" class="form-control"
                                        value="{{ old('contrato_dias_preaviso', 15) }}">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane fade" id="tab-archivos" role="tabpanel">
                        <div class="card-body">
                            <label>Archivos de respaldo</label>
                            <div id="dropzone-suscripcion" class="border rounded text-center text-muted py-5 px-3 bg-light"
                                 style="border-style: dashed !important; cursor: pointer;">
                                <i class="fa fa-cloud-upload fa-2x d-block mb-2"></i>
                                Arrastrá acá la captura del plan o el mail de confirmación,
                                o <span class="text-primary">hacé clic para elegirlos</span>.
                                <small class="d-block mt-2">PDF · PNG · JPG</small>
                            </div>
                            <input type="file" name="nombrearchivos[]" id="inp-archivos" multiple accept=".pdf,.png,.jpg,.jpeg" class="d-none">
                            <ul class="list-group list-group-flush mt-3" id="lista-archivos"></ul>
                        </div>
                    </div>
                </div>

                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            Cargado por <strong>{{ optional(auth()->user())->nombre }}</strong>
                            · {{ now()->format('d/m/Y H:i') }}
                        </small>
                        <div>
                            <button type="submit" name="accion" value="borrador" class="btn btn-secondary">Guardar borrador</button>
                            <button type="submit" name="accion" value="enviar" class="btn btn-primary">✓ Enviar a aprobación</button>
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">
                        Al enviar se notifica al gerente del sector para su autorización y la suscripción
                        queda visible en su bandeja con el impacto en el presupuesto de la cuenta.
                    </small>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.contable.modalconsultacentrocosto')
@include('includes.contable.modalconsultacuentacontable')
@include('includes.compras.modalconsultaproveedor')
@include('includes.admin.modalconsultausuario')
@endsection
