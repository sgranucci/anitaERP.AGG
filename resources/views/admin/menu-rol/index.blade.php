@extends("theme.$theme.layout")
@section("titulo")
Menú - Rol
@endsection

@section("scripts")
<script src="{{asset("assets/pages/scripts/admin/menu-rol/index.js")}}" type="text/javascript"></script>
<script src="https://unpkg.com/sticky-table-headers"></script>
<script>
    $('#tabla-menu-rol-data').stickyTableHeaders();
</script>
@endsection

@section('contenido')
<div id="menu-rol-page"
    data-permisos-url="{{ route('menu_rol_permisos') }}"
    data-guardar-menu-rol-url="{{ route('guardar_menu_rol') }}"
    data-guardar-permiso-rol-url="{{ route('guardar_permiso_rol') }}"
    data-centrocosto="{{ e(request('centrocosto', '')) }}">
<div class="row">
    <div class="col-lg-12">
        @include('includes.mensaje')
        <div class="card card-success">
            <div class="card-header">
                <h3 class="card-title">Menús y permisos por rol</h3>
                <div class="d-md-flex justify-content-md-end flex-wrap">
					<form action="{{ route('menu_rol') }}" method="GET" class="mr-2 mb-2">
						<div class="btn-group">
							<input type="text" name="centrocosto" class="form-control" placeholder="Filtra C.Costo (código o nombre)…" value="{{ old('centrocosto', request('centrocosto')) }}">
							<button type="submit" class="btn btn-default">
								<span class="fa fa-search"></span>
							</button>
						</div>
					</form>
                    <div class="btn-group mb-2">
                        <input type="text" id="filtro-nombre-menu" class="form-control" placeholder="Buscar menú (ej. estacionamiento)…" autocomplete="off">
                    </div>
                </div>
            </div>
            <div class="card-body pb-0">
                <p class="text-muted small mb-2">
                    Use el ícono <i class="fa fa-key"></i> junto a cada menú para abrir los permisos vinculados a esa opción.
                    Las columnas de roles respetan el filtro por centro de costo (si no filtra, se listan todos los roles).
                    Las filas resaltadas en azul marcan el inicio de cada módulo del sistema; el borde inferior indica dónde termina ese bloque.
                </p>
            </div>
            <div class="card-body table-responsive p-0">
                @csrf
                <table class="table table-striped table-bordered table-hover tabla-menu-rol" id="tabla-menu-rol-data">
                    <thead>
                        <tr>
                            <th>Menú</th>
                            @foreach ($rols as $id => $nombre)
                            <th class="text-center">{{$nombre}}</th>
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
                            ])
                        @endforeach
                    </tbody>
                </table>
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
                <div id="modalPermisosMenuContenedor" class="table-responsive"></div>
                <div id="modalPermisosMenuSinRoles" class="alert alert-warning" style="display: none;">
                    No hay roles que coincidan con el filtro de centro de costo. Ajuste el filtro y vuelva a intentar.
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
@endsection
