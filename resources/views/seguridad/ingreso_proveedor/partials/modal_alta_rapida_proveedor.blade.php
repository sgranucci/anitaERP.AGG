@php
    $puedeCrearProveedor = can('crear-proveedor', false);
    $urlAltaProveedor = $puedeCrearProveedor
        ? route('crear_proveedor', ['origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '';
@endphp
<div class="modal fade" id="modal-alta-rapida-proveedor-ingreso" tabindex="-1" role="dialog" aria-hidden="true"
     data-url-buscar="{{ route('buscar_proveedor_rapido_ingreso_proveedor') }}"
     data-url-alta="{{ $urlAltaProveedor }}"
     data-puede-crear="{{ $puedeCrearProveedor ? '1' : '0' }}">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Alta r&aacute;pida de proveedor / visitante</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">
                    Busque primero en el maestro. Si no existe, abra el alta completa (CUIT, cuentas, Anita)
                    o cargue la visita como visitante puntual.
                </p>
                <div class="form-row">
                    <div class="col-md-6 mb-2">
                        <label for="alta-rapida-nombre">Nombre</label>
                        <input type="text" id="alta-rapida-nombre" class="form-control" maxlength="180" placeholder="Raz&oacute;n social o nombre">
                    </div>
                    <div class="col-md-4 mb-2">
                        <label for="alta-rapida-cuit">CUIT</label>
                        <input type="text" id="alta-rapida-cuit" class="form-control" maxlength="13" placeholder="XX-XXXXXXXX-X">
                    </div>
                    <div class="col-md-2 mb-2 d-flex align-items-end">
                        <button type="button" class="btn btn-primary btn-block" id="btn-buscar-alta-rapida-proveedor">
                            <i class="fa fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="table-responsive mt-2">
                    <table class="table table-sm table-bordered mb-0">
                        <thead style="background:#85C1E9;color:#17202A;">
                            <tr>
                                <th>C&oacute;digo</th>
                                <th>Nombre</th>
                                <th>CUIT</th>
                                <th style="width:7rem">Acci&oacute;n</th>
                            </tr>
                        </thead>
                        <tbody id="alta-rapida-resultados">
                            <tr>
                                <td colspan="4" class="text-muted">Escriba nombre o CUIT y pulse Buscar.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div id="alta-rapida-sin-resultado" class="alert alert-warning mt-3 mb-0" style="display:none">
                    No est&aacute; en el maestro.
                    <div class="mt-2">
                        @if ($puedeCrearProveedor)
                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-abrir-alta-proveedor-ingreso">
                                Abrir alta de proveedor
                            </button>
                        @else
                            <span class="d-block small mb-2">
                                No tiene permiso para crear proveedores. Pida el alta a Compras o c&aacute;rguelo como visitante.
                            </span>
                        @endif
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-usar-visitante-ingreso">
                            Cargar como visitante
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
