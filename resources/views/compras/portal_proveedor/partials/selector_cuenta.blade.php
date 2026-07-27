<div class="card card-info">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fa fa-building-o"></i> Cuenta del proveedor
        </h3>
    </div>
    <div class="card-body">
        <form method="get" action="{{ $rutaSelector ?? route('portal_proveedores') }}" id="form-portal-proveedor">
            <div class="form-group row align-items-center mb-0" id="div-proveedor">
                <label for="codigoproveedor" class="col-lg-2 control-label">Proveedor</label>
                <div class="col-lg-10">
                    <input type="hidden" id="proveedor_id" name="proveedor_id" value="{{ $proveedorId ?? '' }}">
                    <div class="d-flex flex-wrap align-items-center">
                        <input type="text"
                               class="form-control codigoproveedor mr-2"
                               id="codigoproveedor"
                               name="codigoproveedor"
                               value="{{ $proveedor->codigo ?? '' }}"
                               placeholder="Código"
                               style="width: 7rem;">
                        <input type="text"
                               class="form-control mr-2"
                               id="nombreproveedor"
                               name="nombreproveedor"
                               value="{{ $proveedor->nombre ?? '' }}"
                               readonly
                               placeholder="Seleccione un proveedor"
                               style="min-width: 16rem; flex: 1 1 16rem;">
                        <button type="button"
                                title="Consultar proveedores (F1)"
                                class="btn btn-outline-primary consultaproveedor mr-2">
                            <i class="fa fa-search"></i> Buscar
                        </button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-sign-in"></i> Abrir portal
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>
