<div class="modal fade" id="modal-consulta-contrato-ingreso" tabindex="-1" role="dialog" aria-hidden="true"
     data-url-consulta="{{ route('consultar_contrato_ingreso_proveedor') }}"
     data-url-resolver="{{ route('resolver_contrato_ingreso_proveedor') }}">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Contratos / abonos activos</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-row mb-2">
                    <div class="col-md-8">
                        <input type="text" id="busqueda-contrato-ingreso" class="form-control" placeholder="N&uacute;mero de OC o proveedor">
                    </div>
                    <div class="col-md-4">
                        <button type="button" class="btn btn-primary btn-block" id="btn-buscar-contrato-ingreso">
                            <i class="fa fa-search"></i> Buscar
                        </button>
                    </div>
                </div>
                <div class="table-responsive" id="resultado-consulta-contrato-ingreso">
                    <p class="text-muted mb-0">Escriba un texto y pulse Buscar, o Enter.</p>
                </div>
            </div>
        </div>
    </div>
</div>
