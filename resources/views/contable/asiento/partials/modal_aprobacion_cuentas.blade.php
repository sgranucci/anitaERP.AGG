<div class="modal fade" id="aprobacionCuentasModal" tabindex="-1" role="dialog" aria-labelledby="aprobacionCuentasModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="aprobacionCuentasModalLabel">Cuentas no autorizadas</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>El asiento incluye cuentas que no están en su lista autorizada. Debe solicitar permiso a contaduría.</p>
                <p>El asiento se guardará en estado <strong>pendiente</strong> y contaduría recibirá un correo para aprobarlo.</p>
                <ul id="lista-cuentas-no-autorizadas" class="mb-0"></ul>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-warning" id="acepta-aprobacion-cuentas">Solicitar aprobación y guardar</button>
            </div>
        </div>
    </div>
</div>
