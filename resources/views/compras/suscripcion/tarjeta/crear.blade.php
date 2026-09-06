@extends("theme.$theme.layout")
@section('titulo')
    {{ $tarjeta ? 'Editar tarjeta' : 'Nueva tarjeta' }}
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/contable/centrocosto/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/caja/bingo/configuracion_puntoventa/form.js') }}" type="text/javascript"></script>
<script>
(function () {
    window._consultaUsuarioOmitirFiltroEmpresaFijo = true;
    window._consultaUsuarioOmitirFiltroEmpresa = true;

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof activa_eventos_consultacentrocosto === 'function') {
            activa_eventos_consultacentrocosto();
        }
        if (typeof activa_eventos_consultausuario === 'function') {
            activa_eventos_consultausuario();
        }
    });
})();
</script>
@endsection

@section('contenido')
@php
    $accion = $tarjeta
        ? route('actualizar_tarjeta_suscripcion', ['id' => $tarjeta->id] + ($filtrosQuery ?? []))
        : route('guardar_tarjeta_suscripcion', $filtrosQuery ?? []);
    $v = fn (string $campo, $default = null) => old($campo, $tarjeta->{$campo} ?? $default);
    $cc = $tarjeta?->centrocostos;
    $resp = $tarjeta?->responsables;
    $caja = $tarjeta?->cuentacajas;
    $ccId = (int) old('centrocosto_id', $tarjeta->centrocosto_id ?? 0);
    $ccCodigo = old('centrocosto_codigo', $cc->codigo ?? '');
    $ccNombre = old('centrocosto_nombre', $cc->nombre ?? '');
    $respId = (int) old('responsable_usuario_id', $tarjeta->responsable_usuario_id ?? 0);
    $respCodigo = old('responsable_usuario_codigo', $resp->usuario ?? ($resp->id ?? ''));
    $respNombre = old('responsable_usuario_nombre', $resp->nombre ?? '');
    $cajaId = (int) old('cuentacaja_id', $tarjeta->cuentacaja_id ?? 0);
    $cajaCodigo = old('cuentacaja_codigo', $caja->codigo ?? '');
    $cajaNombre = old('cuentacaja_nombre', $caja->nombre ?? '');
    $volverQs = $filtrosQuery ?? [];
@endphp
<div class="row">
    <div class="col-lg-10">
        @include('includes.mensaje')
        @include('includes.form-error')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">{{ $tarjeta ? 'Editar tarjeta corporativa' : 'Nueva tarjeta corporativa' }}</h3>
                <div class="card-tools">
                    <a href="{{ route('tarjetas_suscripcion', $volverQs) }}" class="btn btn-outline-light btn-sm">← Volver</a>
                </div>
            </div>
            <form method="post" action="{{ $accion }}" autocomplete="off">
                @csrf
                @if ($tarjeta)
                    @method('PUT')
                @endif
                <div class="card-body">
                    <div class="form-row">
                        <div class="form-group col-md-5">
                            <label>Etiqueta <span class="text-danger">*</span></label>
                            <input type="text" name="etiqueta" class="form-control" required maxlength="80"
                                   value="{{ $v('etiqueta') }}" placeholder="Ej: Visa Marketing">
                        </div>
                        <div class="form-group col-md-2">
                            <label>Últimos 4 <span class="text-danger">*</span></label>
                            <input type="text" name="ult4" class="form-control" required maxlength="4" pattern="\d{4}"
                                   value="{{ $v('ult4') }}" placeholder="4821">
                        </div>
                        <div class="form-group col-md-2">
                            <label>Emisor</label>
                            <input type="text" name="emisor" class="form-control" maxlength="60"
                                   value="{{ $v('emisor') }}" placeholder="Visa">
                        </div>
                        <div class="form-group col-md-3">
                            <label>Empresa <span class="text-danger">*</span></label>
                            <select name="empresa_id" id="empresa_id" class="form-control" required>
                                @foreach ($empresa_query as $emp)
                                    <option value="{{ $emp->id }}" @selected((int)$v('empresa_id') === (int)$emp->id)>{{ $emp->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group col-md-3">
                            <label>Área</label>
                            @php
                                $areaActual = (string) $v('area', '');
                                $areasOpciones = $areas ?? [];
                                if ($areaActual !== '' && ! in_array($areaActual, $areasOpciones, true)) {
                                    $areasOpciones = array_values(array_merge([$areaActual], $areasOpciones));
                                }
                            @endphp
                            <select name="area" class="form-control">
                                <option value="">—</option>
                                @foreach ($areasOpciones as $area)
                                    <option value="{{ $area }}" @selected($areaActual === $area)>{{ $area }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Centro de costo</label>
                            <div class="tm-centrocosto-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                <input type="hidden" name="centrocosto_id" id="centrocosto_id" class="centrocosto_id" value="{{ $ccId ?: '' }}">
                                <button type="button" title="Consulta centros de costo (F1)" class="btn-accion-tabla consultacentrocosto flex-shrink-0">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" name="centrocosto_codigo" id="centrocosto_codigo"
                                       class="form-control codigocentrocosto" value="{{ $ccCodigo }}"
                                       placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;">
                                <input type="text" name="centrocosto_nombre" id="centrocosto_descripcion"
                                       class="form-control descripcioncentrocosto" value="{{ $ccNombre }}"
                                       placeholder="Descripción" readonly style="min-width:0;flex:1 1 auto;">
                            </div>
                            <small class="text-muted">Código + Enter · <kbd>F1</kbd> o lupa</small>
                        </div>
                        <div class="form-group col-md-3">
                            <label>Responsable del plástico</label>
                            <div class="tm-usuario-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
                                <input type="hidden" name="responsable_usuario_id" id="responsable_usuario_id"
                                       class="usuario_id" value="{{ $respId ?: '' }}">
                                <input type="text" name="responsable_usuario_codigo" id="responsable_usuario_codigo"
                                       class="usuario_codigo_arbol form-control" value="{{ $respCodigo }}"
                                       placeholder="Cód." autocomplete="off" style="width:5.5rem;flex-shrink:0;">
                                <button type="button" title="Consulta usuarios (F1)" class="btn-accion-tabla consultausuario tooltipsC"
                                        data-omitir_filtro_empresa="1">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" name="responsable_usuario_nombre" id="responsable_usuario_nombre"
                                       class="nombreusuario form-control" value="{{ $respNombre }}"
                                       placeholder="Nombre" readonly style="min-width:0;flex:1 1 auto;">
                            </div>
                            <small class="text-muted">Código + Enter · <kbd>F1</kbd> o lupa</small>
                        </div>
                        <div class="form-group col-md-2">
                            <label>Límite mensual</label>
                            <input type="number" step="0.01" min="0" name="limite_mensual" class="form-control" value="{{ $v('limite_mensual') }}">
                        </div>
                    </div>

                    <hr>
                    <h5 class="mb-3">Imputación en Ingresos y egresos</h5>
                    <p class="text-muted small">
                        Sin estos dos datos los cargos conciliados quedan identificados pero no se pueden imputar.
                    </p>
                    <div class="form-row">
                        <div class="form-group col-md-4">
                            @include('caja.partials.campo_consulta_cuentacaja', [
                                'layout' => 'inline',
                                'label' => 'Cuenta de caja',
                                'cuentacajaId' => $cajaId ?: '',
                                'codigo' => $cajaCodigo,
                                'nombre' => $cajaNombre,
                                'inputName' => 'cuentacaja_id',
                                'inputId' => 'cuentacaja_id',
                                'ayuda' => 'Código + Enter · F1 o lupa',
                            ])
                        </div>
                        <div class="form-group col-md-4">
                            <label>Tipo de transacción</label>
                            <select name="tipotransaccion_caja_id" class="form-control">
                                <option value="">—</option>
                                @foreach ($tipotransaccion_query as $tt)
                                    <option value="{{ $tt->id }}" @selected((int)$v('tipotransaccion_caja_id') === (int)$tt->id)>
                                        {{ $tt->abreviatura }} · {{ $tt->nombre }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4">
                            <label>Moneda de liquidación</label>
                            <select name="moneda_id" class="form-control">
                                <option value="">—</option>
                                @foreach ($moneda_query as $m)
                                    <option value="{{ $m->id }}" @selected((int)$v('moneda_id') === (int)$m->id)>
                                        {{ $m->nombre ?? $m->abreviatura ?? $m->id }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Observación</label>
                        <input type="text" name="observacion" class="form-control" maxlength="255" value="{{ $v('observacion') }}">
                    </div>

                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="chk-activo" name="activo" value="1"
                               @checked((bool) old('activo', $tarjeta->activo ?? true))>
                        <label class="custom-control-label" for="chk-activo">Tarjeta activa</label>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Guardar</button>
                    <a href="{{ route('tarjetas_suscripcion', $volverQs) }}" class="btn btn-secondary">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.contable.modalconsultacentrocosto')
@include('includes.admin.modalconsultausuario')
@include('includes.caja.modalconsultacuentacaja')
@endsection
