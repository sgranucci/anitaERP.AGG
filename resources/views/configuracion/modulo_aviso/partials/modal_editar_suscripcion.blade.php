<div class="modal fade" id="modal-editar-suscripcion-programador" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" id="form-editar-suscripcion-programador" data-enter-campos="1">
            @csrf
            <div class="modal-content">
                <div class="modal-header" style="background:#85C1E9;color:#17202A;">
                    <h5 class="modal-title">Editar suscripción</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted">Cambios de horario y destinatarios. El batch del Programador sigue disparando el envío.</p>
                    <div class="form-group">
                        <label class="control-label" for="susc_nombre">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="susc_nombre" required maxlength="160">
                    </div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" class="form-check-input" name="activo" id="susc_activo" value="1">
                        <label class="form-check-label" for="susc_activo">Activa</label>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label" for="susc_periodicidad">Periodicidad</label>
                                <select class="form-control" name="periodicidad" id="susc_periodicidad">
                                    <option value="diaria">Diaria</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="mensual">Mensual</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label" for="susc_hora">Hora</label>
                                <input type="time" class="form-control" name="hora" id="susc_hora" required>
                            </div>
                        </div>
                        <div class="col-md-4" id="wrap-susc-dia-semana">
                            <div class="form-group">
                                <label class="control-label" for="susc_dia_semana">Día semana</label>
                                <select class="form-control" name="dia_semana" id="susc_dia_semana">
                                    <option value="1">Lunes</option>
                                    <option value="2">Martes</option>
                                    <option value="3">Miércoles</option>
                                    <option value="4">Jueves</option>
                                    <option value="5">Viernes</option>
                                    <option value="6">Sábado</option>
                                    <option value="7">Domingo</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4 d-none" id="wrap-susc-dia-mes">
                            <div class="form-group">
                                <label class="control-label" for="susc_dia_mes">Día del mes</label>
                                <input type="number" class="form-control" name="dia_mes" id="susc_dia_mes" min="1" max="28" step="1" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label" for="susc_destinatarios">Destinatarios (mails separados por coma)</label>
                        <textarea class="form-control" name="destinatarios" id="susc_destinatarios" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    function syncPeriodicidad() {
        var p = $('#susc_periodicidad').val();
        $('#wrap-susc-dia-semana').toggleClass('d-none', p !== 'semanal');
        $('#wrap-susc-dia-mes').toggleClass('d-none', p !== 'mensual');
    }
    $(document).on('change', '#susc_periodicidad', syncPeriodicidad);

    $(document).on('click', '.js-editar-suscripcion-programador', function (e) {
        e.preventDefault();
        var familia = $(this).data('familia');
        var id = $(this).data('id');
        var url = '{{ url('configuracion/modulo-aviso/suscripcion') }}/' + familia + '/' + id;
        $.getJSON(url).done(function (resp) {
            if (!resp.ok || !resp.suscripcion) {
                alert('No se pudo cargar la suscripción.');
                return;
            }
            var s = resp.suscripcion;
            $('#form-editar-suscripcion-programador').attr('action', url);
            $('#susc_nombre').val(s.nombre);
            $('#susc_activo').prop('checked', !!s.activo);
            $('#susc_periodicidad').val(s.periodicidad || 'diaria');
            $('#susc_hora').val((s.hora || '08:00').substring(0, 5));
            $('#susc_dia_semana').val(String(s.dia_semana || 1));
            $('#susc_dia_mes').val(s.dia_mes || 1);
            $('#susc_destinatarios').val(s.destinatarios || '');
            syncPeriodicidad();
            $('#modal-editar-suscripcion-programador').modal('show');
        }).fail(function () {
            alert('No se pudo cargar la suscripción.');
        });
    });
})();
</script>
