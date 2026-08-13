@extends("theme.$theme.layout")
@section("titulo")
Menú - Rol
@endsection

@section("scripts")
<script src="{{ asset('assets/pages/scripts/admin/menu-rol/index.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div id="menu-rol-page"
    data-permisos-url="{{ route('menu_rol_permisos') }}"
    data-usuarios-url="{{ route('menu_rol_usuarios') }}"
    data-guardar-menu-rol-url="{{ route('guardar_menu_rol') }}"
    data-guardar-permiso-rol-url="{{ route('guardar_permiso_rol') }}"
    data-centrocosto-id="{{ e($centrocostoId) }}">
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Menús y permisos por rol</h3>
            </div>
            <div class="card-body pb-2 pt-3">
                <form action="{{ route('menu_rol') }}" method="GET" id="form-filtro-menu-rol" class="menu-rol-filtros">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 col-lg-3 mb-2">
                            <label for="centrocosto_id" class="mb-1 small font-weight-bold">Centro de costo</label>
                            <select name="centrocosto_id" id="centrocosto_id" class="form-control form-control-sm">
                                <option value="">Todos los roles ({{ $totalRolesSistema }})</option>
                                @if ($hayRolesSinCentrocosto)
                                    <option value="sin" @selected($centrocostoId === 'sin')>Sin centro de costo</option>
                                @endif
                                @foreach ($centrocostosFiltro as $cc)
                                    <option value="{{ $cc->id }}" @selected((string) $centrocostoId === (string) $cc->id)>
                                        {{ trim(($cc->codigo ?? '').' — '.($cc->nombre ?? '')) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 col-lg-3 mb-2">
                            <label for="filtro-modulo-menu" class="mb-1 small font-weight-bold">Módulo / opción</label>
                            <select id="filtro-modulo-menu" class="form-control form-control-sm" autocomplete="off">
                                <option value="">Todos los módulos</option>
                                @foreach ($modulosMenu as $modulo)
                                    <option value="{{ $modulo['id'] }}">{{ $modulo['nombre'] }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group col-md-4 col-lg-2 mb-2">
                            <label for="filtro-nombre-menu" class="mb-1 small font-weight-bold">Buscar opción</label>
                            <input type="text" id="filtro-nombre-menu" class="form-control form-control-sm"
                                placeholder="Nombre de menú…" autocomplete="off">
                        </div>
                        <div class="form-group col-md-4 col-lg-2 mb-2">
                            <label for="filtro-nombre-rol" class="mb-1 small font-weight-bold">Buscar rol</label>
                            <input type="text" id="filtro-nombre-rol" class="form-control form-control-sm"
                                placeholder="Ocultar columnas…" autocomplete="off"
                                title="Filtra columnas de roles sin recargar (ej. Enc-compras)">
                        </div>
                        <div class="form-group col-md-8 col-lg-2 mb-2">
                            <button type="submit" class="btn btn-sm btn-primary mr-1">
                                <i class="fa fa-filter"></i> Aplicar C.Costo
                            </button>
                            @if ($centrocostoId !== '')
                                <a href="{{ route('menu_rol') }}" class="btn btn-sm btn-outline-secondary" title="Quitar filtro de centro de costo">
                                    Limpiar
                                </a>
                            @endif
                        </div>
                    </div>
                </form>
                <p class="text-muted small mb-2 mb-md-0">
                    Mostrando <strong>{{ count($rols) }}</strong> rol(es)
                    @if ($centrocostoId !== '')
                        (filtrados por centro de costo)
                    @else
                        de {{ $totalRolesSistema }}
                    @endif.
                    Use <i class="fa fa-key"></i> para permisos de cada opción
                    y <i class="fa fa-users"></i> en el encabezado del rol para ver sus usuarios.
                    Las filas azules marcan el inicio de cada módulo.
                    Desplace horizontalmente para ver más roles; la columna Menú permanece fija.
                </p>
            </div>
            <div class="card-body table-responsive p-0 menu-rol-tabla-wrap" id="menu-rol-tabla-wrap">
                @csrf
                <style id="menu-rol-col-filter-style"></style>
                <table class="table table-striped table-bordered table-hover tabla-menu-rol" id="tabla-menu-rol-data">
                    <thead>
                        <tr>
                            <th class="menu-rol-col-menu">Menú</th>
                            @foreach ($rols as $id => $nombre)
                            <th class="text-center menu-rol-col-rol col-rol-{{ $id }}"
                                data-rol-id="{{ $id }}"
                                data-rol-nombre="{{ e(mb_strtolower($nombre)) }}"
                                title="{{ $nombre }}">
                                <button type="button"
                                    class="btn btn-sm btn-outline-dark btn-usuarios-rol"
                                    title="Usuarios del rol {{ $nombre }}"
                                    data-rol-id="{{ $id }}"
                                    data-rol-nombre="{{ e($nombre) }}">
                                    <i class="fa fa-users"></i>
                                </button>
                                <span class="menu-rol-th-rol">{{ $nombre }}</span>
                            </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($menus as $key => $menu)
                        @if ($menu["menu_id"] != 0)
                            @break
                        @endif
                            @include('admin.menu-rol.partials.fila-menu', [
                                'item' => $menu,
                                'rols' => $rols,
                                'menusRols' => $menusRols,
                                'nivel' => 0,
                                'moduloId' => (int) $menu['id'],
                                'parentId' => 0,
                            ])
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-footer py-2 small text-muted d-flex flex-wrap justify-content-between">
                <span id="menu-rol-filas-visibles"></span>
                <span id="menu-rol-cols-visibles">Columnas de roles visibles: <strong>{{ count($rols) }}</strong></span>
            </div>
        </div>
    </div>
</div>
</div>

<div class="modal fade" id="modalPermisosMenu" tabindex="-1" role="dialog" aria-labelledby="modalPermisosMenuLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalPermisosMenuLabel">Permisos del menú</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalPermisosMenuCargando" class="text-center p-4 text-muted" style="display: none;">
                    Cargando permisos…
                </div>
                <div id="modalPermisosMenuError" class="alert alert-danger" style="display: none;"></div>
                <div id="modalPermisosMenuContenedor" class="menu-rol-modal-tabla-wrap"></div>
                <div id="modalPermisosMenuSinRoles" class="alert alert-warning" style="display: none;">
                    No hay roles que coincidan con el filtro aplicado (centro de costo o buscar rol). Ajuste el filtro y vuelva a intentar.
                </div>
                <div id="modalPermisosMenuVacio" class="alert alert-info" style="display: none;">
                    No hay permisos asociados a este ítem de menú (<code>menu_id</code> en la tabla permiso).
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalUsuariosRol" tabindex="-1" role="dialog" aria-labelledby="modalUsuariosRolLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuariosRolLabel">Usuarios del rol</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="modalUsuariosRolCargando" class="text-center p-4 text-muted" style="display: none;">
                    Cargando usuarios…
                </div>
                <div id="modalUsuariosRolError" class="alert alert-danger" style="display: none;"></div>
                <div id="modalUsuariosRolContenedor" class="table-responsive" style="display: none;"></div>
                <div id="modalUsuariosRolVacio" class="alert alert-info" style="display: none;">
                    Este rol no tiene usuarios asignados.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
@endsection
