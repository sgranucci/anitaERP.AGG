@extends("theme.$theme.layout")
@section('titulo')
    Catálogo de logística
@endsection
@section('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script src="{{ asset('assets/pages/scripts/admin/usuario/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/logistica/catalogo.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/logistica/catalogo.js')) ?: time() }}"></script>
@endsection
@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cog"></i> Catálogo de logística</h3>
            </div>
            <div class="card-body">
                <form method="GET" action="{{ route('editar_configuracion_logistica') }}" id="form-ver-como-usuario" class="form-horizontal mb-3">
                    <div class="form-group row mb-0 tm-usuario-campo">
                        <label class="col-lg-4 control-label text-right pr-2" for="ver_catalogo_usuario_codigo">Ver catálogo como</label>
                        <div class="col-lg-8">
                            <div class="align-items-center" style="display:flex;gap:4px;">
                                <input type="hidden" name="ver_usuario_id" id="ver_catalogo_usuario_id" class="usuario_id" value="{{ $verComo['usuario']->id ?? '' }}">
                                <input type="text" id="ver_catalogo_usuario_codigo" class="usuario_codigo_arbol form-control" style="flex:0 0 8rem;width:8rem;" value="{{ $verComo['usuario']->usuario ?? '' }}" placeholder="C&oacute;digo" title="C&oacute;digo o ID. Enter resuelve. F1 abre la consulta." autocomplete="off" autocapitalize="off" spellcheck="false">
                                <button type="button" class="btn-accion-tabla consultausuario tooltipsC flex-shrink-0" title="Consulta usuarios (F1)"
                                    data-ptrusuario_id="#ver_catalogo_usuario_id"
                                    data-ptrusuario_codigo="#ver_catalogo_usuario_codigo"
                                    data-ptrnombre="#ver_catalogo_usuario_nombre"
                                    data-omitir_filtro_empresa="1">
                                    <i class="fa fa-search text-primary"></i>
                                </button>
                                <input type="text" id="ver_catalogo_usuario_nombre" class="nombreusuario form-control" value="{{ $verComo['usuario']->nombre ?? '' }}" placeholder="Nombre" readonly autocomplete="off">
                                <button class="btn btn-outline-info btn-sm flex-shrink-0" type="submit">Ver</button>
                            </div>
                            <small class="text-muted">C&oacute;digo, Enter o F1. Muestra el cat&aacute;logo que ve ese usuario.</small>
                        </div>
                    </div>
                </form>
                @if (!empty($verComo))
                    <div class="alert alert-info">
                        <strong>{{ $verComo['usuario']->nombre }}</strong>
                        ve {{ count($verComo['catalogo']['tipos']) }} tipos,
                        {{ count($verComo['catalogo']['categorias']) }} categorías y
                        {{ count($verComo['catalogo']['items']) }} ítems.
                        @if (count($verComo['catalogo']['items']) === 0)
                            Si está en cero, falta publicar artículos o habilitar tipo, categoría e ítem.
                        @endif
                    </div>
                @endif
                <p class="text-muted">Quien no tiene una fila de habilitación no ve ese tipo, esa categoría ni ese ítem. Una fila de usuario pisa la del rol.</p>
                <form method="POST" action="{{ route('actualizar_configuracion_logistica') }}" id="form-logistica-config">
                    @csrf
                    @method('PUT')
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Monto que pide aprobación</label>
                        <div class="col-lg-3">
                            <input type="number" name="monto_aprobacion" class="form-control" min="0" step="0.01" value="{{ old('monto_aprobacion', $montoAprobacion) }}">
                            <small class="text-muted">0 = las solicitudes de insumos no pasan por aprobación. Si el total estimado supera este importe, quedan pendientes.</small>
                        </div>
                    </div>
                    <h4>Categorías del catálogo</h4>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Código</th><th>Nombre</th><th>Icono</th><th>Orden</th><th>Activa</th><th></th></tr>
                        </thead>
                        <tbody id="cat-body">
                            @foreach ($categorias as $cat)
                                <tr>
                                    <td><input type="hidden" name="cat_id[]" value="{{ $cat->id }}"><input class="form-control form-control-sm" name="cat_codigo[]" value="{{ $cat->codigo }}"></td>
                                    <td><input class="form-control form-control-sm" name="cat_nombre[]" value="{{ $cat->nombre }}"></td>
                                    <td><input class="form-control form-control-sm" name="cat_icono[]" value="{{ $cat->icono }}"></td>
                                    <td><input class="form-control form-control-sm" name="cat_orden[]" value="{{ $cat->orden }}" style="width:5rem;"></td>
                                    <td>
                                        <select class="form-control form-control-sm" name="cat_activo[]">
                                            <option value="1" @selected($cat->activo)>Sí</option>
                                            <option value="0" @selected(! $cat->activo)>No</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="cat-agregar">+ Categoría</button>

                    <h4>Tipos de solicitud</h4>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;"><tr><th>Código</th><th>Nombre</th><th>Icono</th><th>Orden</th><th>Activo</th></tr></thead>
                        <tbody>
                            @foreach ($tipos as $i => $tipo)
                                <tr>
                                    <td>{{ $tipo->codigo }}<input type="hidden" name="tipo_id[{{ $i }}]" value="{{ $tipo->id }}"></td>
                                    <td><input class="form-control form-control-sm" name="tipo_nombre[{{ $i }}]" value="{{ $tipo->nombre }}"></td>
                                    <td><input class="form-control form-control-sm" name="tipo_icono[{{ $i }}]" value="{{ $tipo->icono }}"></td>
                                    <td><input class="form-control form-control-sm" name="tipo_orden[{{ $i }}]" value="{{ $tipo->orden }}" style="width:5rem;"></td>
                                    <td>
                                        <select class="form-control form-control-sm" name="tipo_activo[{{ $i }}]">
                                            <option value="1" @selected($tipo->activo)>Sí</option>
                                            <option value="0" @selected(! $tipo->activo)>No</option>
                                        </select>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h4 class="mt-3">Tipos de trabajo</h4>
                    <p class="text-muted small">Los formularios de cada trabajo se piden en la fase siguiente. Acá queda el responsable y la prioridad piso.</p>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Código</th><th>Nombre</th><th>Icono</th><th>Responsable</th><th>Email</th><th>Piso</th><th>Orden</th><th>Activo</th><th></th></tr>
                        </thead>
                        <tbody id="trab-body">
                            @foreach ($trabajos as $trab)
                                <tr>
                                    <td><input type="hidden" name="trab_id[]" value="{{ $trab->id }}"><input class="form-control form-control-sm" name="trab_codigo[]" value="{{ $trab->codigo }}"></td>
                                    <td><input class="form-control form-control-sm" name="trab_nombre[]" value="{{ $trab->nombre }}"></td>
                                    <td><input class="form-control form-control-sm" name="trab_icono[]" value="{{ $trab->icono }}"></td>
                                    <td><input class="form-control form-control-sm" name="trab_responsable[]" value="{{ $trab->responsable }}"></td>
                                    <td><input class="form-control form-control-sm" name="trab_email[]" value="{{ $trab->email }}"></td>
                                    <td>
                                        <select class="form-control form-control-sm" name="trab_piso[]">
                                            @foreach (['Baja', 'Media', 'Alta'] as $piso)
                                                <option value="{{ $piso }}" @selected($trab->prioridad_piso === $piso)>{{ $piso }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td><input class="form-control form-control-sm" name="trab_orden[]" value="{{ $trab->orden }}" style="width:4rem;"></td>
                                    <td>
                                        <select class="form-control form-control-sm" name="trab_activo[]">
                                            <option value="1" @selected($trab->activo)>Sí</option>
                                            <option value="0" @selected(! $trab->activo)>No</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="trab-agregar">+ Tipo de trabajo</button>

                    <h4>Ubicaciones de traslado</h4>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;"><tr><th>Nombre</th><th>Icono</th><th>Orden</th><th>Activa</th><th></th></tr></thead>
                        <tbody id="ubi-body">
                            @foreach ($ubicaciones as $ubi)
                                <tr>
                                    <td><input type="hidden" name="ubi_id[]" value="{{ $ubi->id }}"><input class="form-control form-control-sm" name="ubi_nombre[]" value="{{ $ubi->nombre }}"></td>
                                    <td><input class="form-control form-control-sm" name="ubi_icono[]" value="{{ $ubi->icono }}"></td>
                                    <td><input class="form-control form-control-sm" name="ubi_orden[]" value="{{ $ubi->orden }}" style="width:4rem;"></td>
                                    <td>
                                        <select class="form-control form-control-sm" name="ubi_activo[]">
                                            <option value="1" @selected($ubi->activo)>Sí</option>
                                            <option value="0" @selected(! $ubi->activo)>No</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-4" id="ubi-agregar">+ Ubicación</button>

                    <h4>Habilitación de tipos y categorías</h4>
                    <p class="text-muted small">Código del tipo: insumos o trabajos. Código de categoría: LIB, LIM, EPP, MANT, REP, OTR. El ítem se habilita en la solapa del artículo.</p>
                    <table class="table table-sm table-bordered">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr><th>Nivel</th><th>Código</th><th>Alcance</th><th>Rol o usuario</th><th>Habilitado</th><th></th></tr>
                        </thead>
                        <tbody id="hab-body">
                            @foreach ($habilitaciones as $i => $hab)
                                @php
                                    $codigoRef = $hab->nivel === 'tipo' ? ($hab->tipoSolicitud->codigo ?? '') : ($hab->categoria->codigo ?? '');
                                    $nombreRef = $hab->nivel === 'tipo' ? ($hab->tipoSolicitud->nombre ?? '') : ($hab->categoria->nombre ?? '');
                                    $refId = $hab->nivel === 'tipo' ? (int) $hab->tipo_solicitud_id : (int) $hab->catalogo_categoria_id;
                                @endphp
                                <tr class="hab-config-fila">
                                    <td>
                                        <select name="hab_nivel[]" class="form-control form-control-sm hab-nivel">
                                            <option value="tipo" @selected($hab->nivel === 'tipo')>Tipo</option>
                                            <option value="categoria" @selected($hab->nivel === 'categoria')>Categoría</option>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="hidden" name="hab_ref_id[]" class="hab-ref-id" value="{{ $refId }}">
                                        <input type="text" class="form-control form-control-sm hab-ref-codigo" value="{{ $codigoRef }}" placeholder="Código" autocomplete="off">
                                        <input type="text" class="form-control form-control-sm hab-ref-nombre mt-1" value="{{ $nombreRef }}" readonly>
                                    </td>
                                    <td>
                                        <select name="hab_alcance[]" class="form-control form-control-sm hab-alcance">
                                            <option value="rol" @selected($hab->alcance === 'rol')>Rol</option>
                                            <option value="usuario" @selected($hab->alcance === 'usuario')>Usuario</option>
                                        </select>
                                    </td>
                                    <td>
                                        <div class="hab-box-rol" style="display:flex;gap:4px;">
                                            <input type="hidden" name="hab_rol_id[]" class="hab-rol-id" value="{{ $hab->rol_id }}">
                                            <button type="button" class="btn-accion-tabla consultalogisticarol"><i class="fa fa-search text-primary"></i></button>
                                            <input type="text" name="hab_rol_nombre[]" class="form-control form-control-sm hab-rol-nombre" value="{{ $hab->rol->nombre ?? '' }}" placeholder="Rol">
                                        </div>
                                        <div class="hab-box-usuario mt-1" style="display:flex;gap:4px;">
                                            <input type="hidden" name="hab_usuario_id[]" id="hab_usuario_{{ $i }}" class="usuario_id" value="{{ $hab->usuario_id }}">
                                            <button type="button" class="btn-accion-tabla consultausuario" data-omitir_filtro_empresa="1"
                                                data-ptrusuario_id="#hab_usuario_{{ $i }}"
                                                data-ptrusuario_codigo="#hab_usuario_codigo_{{ $i }}"
                                                data-ptrnombre="#hab_usuario_nombre_{{ $i }}">
                                                <i class="fa fa-search text-primary"></i>
                                            </button>
                                            <input type="text" name="hab_usuario_codigo[]" id="hab_usuario_codigo_{{ $i }}" class="form-control form-control-sm" value="{{ $hab->usuario->usuario ?? '' }}" placeholder="Usuario" style="width:8rem;">
                                            <input type="text" name="hab_usuario_nombre[]" id="hab_usuario_nombre_{{ $i }}" class="form-control form-control-sm" value="{{ $hab->usuario->nombre ?? '' }}" readonly>
                                        </div>
                                    </td>
                                    <td>
                                        <select name="hab_habilitado[]" class="form-control form-control-sm">
                                            <option value="1" @selected($hab->habilitado)>Sí</option>
                                            <option value="0" @selected(! $hab->habilitado)>No</option>
                                        </select>
                                    </td>
                                    <td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="hab-agregar">+ Habilitación</button>
                    <div class="mt-4">
                        <button type="submit" class="btn btn-success">Actualizar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<template id="hab-template">
    <tr class="hab-config-fila">
        <td>
            <select name="hab_nivel[]" class="form-control form-control-sm hab-nivel">
                <option value="tipo">Tipo</option>
                <option value="categoria">Categoría</option>
            </select>
        </td>
        <td>
            <input type="hidden" name="hab_ref_id[]" class="hab-ref-id" value="">
            <input type="text" class="form-control form-control-sm hab-ref-codigo" placeholder="Código" autocomplete="off">
            <input type="text" class="form-control form-control-sm hab-ref-nombre mt-1" readonly>
        </td>
        <td>
            <select name="hab_alcance[]" class="form-control form-control-sm hab-alcance">
                <option value="rol">Rol</option>
                <option value="usuario">Usuario</option>
            </select>
        </td>
        <td>
            <div class="hab-box-rol" style="display:flex;gap:4px;">
                <input type="hidden" name="hab_rol_id[]" class="hab-rol-id" value="">
                <button type="button" class="btn-accion-tabla consultalogisticarol"><i class="fa fa-search text-primary"></i></button>
                <input type="text" name="hab_rol_nombre[]" class="form-control form-control-sm hab-rol-nombre" placeholder="Rol">
            </div>
            <div class="hab-box-usuario mt-1" style="display:none;gap:4px;">
                <input type="hidden" name="hab_usuario_id[]" id="hab_usuario___IDX__" class="usuario_id" value="">
                <button type="button" class="btn-accion-tabla consultausuario" data-omitir_filtro_empresa="1"
                    data-ptrusuario_id="#hab_usuario___IDX__"
                    data-ptrusuario_codigo="#hab_usuario_codigo___IDX__"
                    data-ptrnombre="#hab_usuario_nombre___IDX__">
                    <i class="fa fa-search text-primary"></i>
                </button>
                <input type="text" name="hab_usuario_codigo[]" id="hab_usuario_codigo___IDX__" class="form-control form-control-sm" placeholder="Usuario" style="width:8rem;">
                <input type="text" name="hab_usuario_nombre[]" id="hab_usuario_nombre___IDX__" class="form-control form-control-sm" readonly>
            </div>
        </td>
        <td>
            <select name="hab_habilitado[]" class="form-control form-control-sm">
                <option value="1">Sí</option>
                <option value="0">No</option>
            </select>
        </td>
        <td><button type="button" class="btn-accion-tabla hab-quitar"><i class="fa fa-times-circle text-danger"></i></button></td>
    </tr>
</template>
@include('includes.admin.modalconsultausuario')
@include('includes.logistica.modales_catalogo')
@endsection
