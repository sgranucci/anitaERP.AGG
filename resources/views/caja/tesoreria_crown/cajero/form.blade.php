@extends("theme.$theme.layout")

@section('titulo')
    {{ $edicion ? 'Editar cajero' : 'Nuevo cajero' }}
@endsection

@section('scripts')
    <script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}?v={{ filemtime(public_path('assets/pages/scripts/admin/usuario/consulta.js')) }}" type="text/javascript"></script>
    <script src="{{ asset('assets/pages/scripts/caja/tesoreria_crown/cajero_abm.js') }}?v={{ filemtime(public_path('assets/pages/scripts/caja/tesoreria_crown/cajero_abm.js')) }}" type="text/javascript"></script>
@endsection

@section('contenido')
@php
    use App\Support\Caja\CajeroCrownSistemaExternoSupport;
    $activo = (string) old('activo', ($cajero->activo ?? true) ? '1' : '0') === '1';
    $externosOld = old('externos');
    if (is_array($externosOld)) {
        $filasExternos = collect($externosOld);
    } else {
        $filasExternos = ($externos ?? collect())->map(function ($ext) {
            return [
                'sistema' => $ext->sistema,
                'codigo_externo' => $ext->codigo_externo,
                'mozo_codigo' => $ext->mozoGastronomia->codigo ?? '',
            ];
        });
    }
    if ($filasExternos->isEmpty()) {
        $filasExternos = collect([['sistema' => '', 'codigo_externo' => '', 'mozo_codigo' => '']]);
    }
@endphp
<div class="row">
    <div class="col-lg-12">
        <div class="card card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fa fa-id-badge mr-1"></i>
                    {{ $edicion ? 'Editar cajero' : 'Nuevo cajero' }}
                </h3>
                <div class="card-tools">
                    <a href="{{ route('tesoreria_crown_cajero', ['sala_id' => $sala->id]) }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form class="form-horizontal form--label-right" id="form-cajero-crown" method="POST" action="{{ $accion }}" autocomplete="off" data-enter-campos="1">
                @csrf
                @if ($metodo !== 'POST')
                    @method($metodo)
                @endif
                <input type="hidden" name="sala_id" value="{{ $sala->id }}">
                <input type="hidden" name="empresa_id" value="{{ $sala->empresa_id }}">
                <div class="card-body">
                    @include('includes.form-error')
                    @include('includes.mensaje')
                    @include('includes.tabs-activas-estilos')
                    <div class="tabs-activas mb-3">
                        <ul class="nav nav-tabs" id="tabs-cajero-crown" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-toggle="tab" href="#tab-cajero-datos" role="tab">
                                    <i class="fa fa-user"></i> Datos
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-toggle="tab" href="#tab-cajero-externo" role="tab">
                                    <i class="fa fa-exchange"></i> Cajero externo
                                </a>
                            </li>
                        </ul>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="tab-cajero-datos" role="tabpanel">
                            <div class="form-group row">
                                <label class="col-lg-3 control-label text-right pr-2">Sala</label>
                                <div class="col-lg-8">
                                    <input type="text" class="form-control" readonly value="{{ $sala->codigo }} — {{ $sala->nombre }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="codigo" class="col-lg-3 control-label text-right pr-2 requerido">Código</label>
                                <div class="col-lg-4">
                                    @if ($edicion)
                                        <input type="text" id="codigo" class="form-control" value="{{ $cajero->codigo }}" readonly>
                                        <input type="hidden" name="codigo" value="{{ $cajero->codigo }}">
                                    @else
                                        <input type="number" name="codigo" id="codigo" class="form-control" min="1" required value="{{ old('codigo') }}" inputmode="numeric">
                                    @endif
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="nombre" class="col-lg-3 control-label text-right pr-2 requerido">Nombre</label>
                                <div class="col-lg-8">
                                    <input type="text" name="nombre" id="nombre" class="form-control" maxlength="120" required value="{{ old('nombre', $cajero->nombre) }}">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="legajo" class="col-lg-3 control-label text-right pr-2">Legajo</label>
                                <div class="col-lg-4">
                                    <input type="number" name="legajo" id="legajo" class="form-control" min="0" value="{{ old('legajo', $cajero->legajo) }}" inputmode="numeric">
                                </div>
                            </div>
                            <div class="form-group row tm-usuario-campo">
                                <label for="usuario_codigo" class="col-lg-3 control-label text-right pr-2">Usuario ERP</label>
                                <div class="col-lg-8">
                                    <div class="d-flex flex-nowrap align-items-center" style="gap:4px;">
                                        <input type="hidden" name="usuario_id" id="usuario_id" class="usuario_id" value="{{ old('usuario_id', $cajero->usuario_id) }}">
                                        <button type="button" class="btn-accion-tabla consultausuario tooltipsC flex-shrink-0" title="Consultar usuario (F1)"
                                                data-ptrusuario_id="#usuario_id" data-ptrnombre="#nombreusuario" data-ptrusuario_codigo="#usuario_codigo"
                                                data-omitir_filtro_empresa="1">
                                            <i class="fa fa-search text-primary"></i>
                                        </button>
                                        <input type="text" id="usuario_codigo" class="form-control usuario_codigo_arbol" style="width:7rem;flex-shrink:0;"
                                               value="{{ old('usuario_codigo', $cajero->usuario->usuario ?? '') }}" placeholder="C&oacute;digo" autocomplete="off">
                                        <input type="text" id="nombreusuario" class="form-control nombreusuario text-truncate" readonly
                                               value="{{ old('usuario_nombre', $cajero->usuario->nombre ?? '') }}" placeholder="Nombre" style="min-width:0;flex:1 1 auto;">
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="caja_codigo" class="col-lg-3 control-label text-right pr-2">Caja</label>
                                <div class="col-lg-4">
                                    <input type="text" name="caja_codigo" id="caja_codigo" class="form-control" maxlength="20"
                                           value="{{ old('caja_codigo', $cajero->caja->codigo ?? '') }}" placeholder="C&oacute;digo de caja">
                                </div>
                                <div class="col-lg-4">
                                    <input type="text" class="form-control" readonly value="{{ $cajero->caja->nombre ?? '' }}" placeholder="Nombre">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="mozo_codigo" class="col-lg-3 control-label text-right pr-2">Mozo gastronomía</label>
                                <div class="col-lg-4">
                                    <input type="text" name="mozo_codigo" id="mozo_codigo" class="form-control" maxlength="20"
                                           value="{{ old('mozo_codigo', $cajero->mozoGastronomia->codigo ?? '') }}" placeholder="C&oacute;digo mozo">
                                </div>
                                <div class="col-lg-4">
                                    <input type="text" class="form-control" readonly value="{{ $cajero->mozoGastronomia->nombre ?? '' }}" placeholder="Nombre">
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="tipo_tarea" class="col-lg-3 control-label text-right pr-2 requerido">Tarea</label>
                                <div class="col-lg-4">
                                    @php
                                        $tareaActual = (string) old('tipo_tarea', $cajero->tipo_tarea ?? 'T');
                                    @endphp
                                    <select name="tipo_tarea" id="tipo_tarea" class="form-control" required>
                                        @foreach ($tareas as $codigo => $nombre)
                                            <option value="{{ $codigo }}" @selected($tareaActual === $codigo)>{{ $codigo }} — {{ $nombre }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-3 control-label text-right pr-2">Rendición</label>
                                <div class="col-lg-8 pt-2">
                                    @php
                                        $permiteSinHab = (string) old(
                                            'permite_rendir_sin_habilitacion',
                                            ($cajero->permite_rendir_sin_habilitacion ?? false) ? '1' : '0'
                                        ) === '1';
                                    @endphp
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="permite_rendir_sin_habilitacion"
                                               name="permite_rendir_sin_habilitacion" value="1" @checked($permiteSinHab)>
                                        <label class="custom-control-label" for="permite_rendir_sin_habilitacion">
                                            Permite rendir sin habilitación
                                            <span class="text-muted small">(empleados, QR, kiosco)</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label for="clave" class="col-lg-3 control-label text-right pr-2">PIN / clave</label>
                                <div class="col-lg-4">
                                    <input type="password" name="clave" id="clave" class="form-control" maxlength="20" autocomplete="new-password"
                                           placeholder="{{ $edicion ? 'Vacío = no cambia' : 'Vacío = usa el código' }}">
                                    <small class="form-text text-muted">Para firmar comprobantes. En alta, si queda vacío se usa el código.</small>
                                </div>
                            </div>
                            <div class="form-group row">
                                <label class="col-lg-3 control-label text-right pr-2">Activo</label>
                                <div class="col-lg-8 pt-2">
                                    <input type="hidden" name="activo" value="0">
                                    <div class="custom-control custom-switch">
                                        <input type="checkbox" class="custom-control-input" id="activo" name="activo" value="1" @checked($activo)>
                                        <label class="custom-control-label" for="activo">Operativo en sala</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="tab-cajero-externo" role="tabpanel">
                            <div class="alert alert-light border mb-3">
                                Enlaces a sistemas externos (PXSOL, GST, gastronomía). Un sistema por renglón.
                            </div>
                            <div class="card card-outline card-info mb-0">
                                <div class="card-header py-2">
                                    <h3 class="card-title mb-0">Mapeos externos</h3>
                                </div>
                                <div class="card-body p-0 table-responsive">
                                    <table class="table table-sm table-bordered mb-0" id="tabla-cajero-externos">
                                        <thead style="background:#85C1E9;color:#17202A;">
                                            <tr>
                                                <th style="width:22%;">Sistema</th>
                                                <th style="width:28%;">Código externo</th>
                                                <th style="width:28%;">Código mozo</th>
                                                <th style="width:90px;">Acciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbody-cajero-externos">
                                            @foreach ($filasExternos as $i => $filaExt)
                                                @include('caja.tesoreria_crown.cajero.partials.renglon_externo', [
                                                    'indice' => $i,
                                                    'sistema' => $filaExt['sistema'] ?? '',
                                                    'codigoExterno' => $filaExt['codigo_externo'] ?? '',
                                                    'mozoCodigo' => $filaExt['mozo_codigo'] ?? '',
                                                    'sistemas' => $sistemas,
                                                ])
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                                <div class="card-footer py-2">
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btn-agregar-externo">
                                        <i class="fa fa-plus"></i> Agregar renglón
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="row">
                        <div class="col-lg-3"></div>
                        <div class="col-lg-6">
                            <button type="submit" class="btn botonsubmit btn-success" form="form-cajero-crown">
                                <i class="fa fa-save"></i> Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<template id="tpl-cajero-externo">
    @include('caja.tesoreria_crown.cajero.partials.renglon_externo', [
        'indice' => '__IDX__',
        'sistema' => '',
        'codigoExterno' => '',
        'mozoCodigo' => '',
        'sistemas' => $sistemas,
    ])
</template>
@include('includes.admin.modalconsultausuario')
<script>
(function () {
    var tarea = document.getElementById('tipo_tarea');
    var flag = document.getElementById('permite_rendir_sin_habilitacion');
    if (!tarea || !flag) {
        return;
    }
    var sugeridos = { E: true, K: true };
    tarea.addEventListener('change', function () {
        if (sugeridos[tarea.value]) {
            flag.checked = true;
        }
    });
})();
</script>
@endsection
