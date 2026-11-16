@php
    $opcionesAlta = \App\Support\Configuracion\ModuloAvisoProgramadoSuscripcionesSupport::opcionesAlta();
@endphp
<div class="modal fade" id="modal-crear-suscripcion-programador" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <form method="post" action="{{ route('crear_suscripcion_hermana_modulo_aviso') }}" id="form-crear-suscripcion-programador" data-enter-campos="1">
            @csrf
            <div class="modal-content">
                <div class="modal-header" style="background:#85C1E9;color:#17202A;">
                    <h5 class="modal-title">Nueva suscripción de envío</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar"><span>&times;</span></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">
                        Alta mínima desde el Programador. Los filtros del informe (definibles) se afinan después en el módulo.
                    </p>
                    <div class="form-group">
                        <label class="control-label" for="crear_susc_familia">Origen</label>
                        <select class="form-control" name="familia" id="crear_susc_familia" required>
                            <option value="">— Elegir —</option>
                            @foreach ($opcionesAlta['familias'] as $fam)
                                <option value="{{ $fam['value'] }}">{{ $fam['label'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group d-none" id="wrap-crear-reporte-contable">
                        <label class="control-label" for="crear_susc_reporte_contable">Reporte contable</label>
                        <select class="form-control" id="crear_susc_reporte_contable">
                            <option value="">— Elegir —</option>
                            @foreach ($opcionesAlta['reportes_contable'] as $r)
                                <option value="{{ $r['id'] }}">{{ $r['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group d-none" id="wrap-crear-reporte-sueldos">
                        <label class="control-label" for="crear_susc_reporte_sueldos">Reporte sueldos</label>
                        <select class="form-control" id="crear_susc_reporte_sueldos">
                            <option value="">— Elegir —</option>
                            @foreach ($opcionesAlta['reportes_sueldos'] as $r)
                                <option value="{{ $r['id'] }}">{{ $r['nombre'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <input type="hidden" name="reporte_id" id="crear_susc_reporte_id" value="">
                    <div class="form-group">
                        <label class="control-label" for="crear_susc_nombre">Nombre</label>
                        <input type="text" class="form-control" name="nombre" id="crear_susc_nombre" required maxlength="160">
                    </div>
                    <div class="form-check mb-3">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" class="form-check-input" name="activo" id="crear_susc_activo" value="1" checked>
                        <label class="form-check-label" for="crear_susc_activo">Activa</label>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label" for="crear_susc_periodicidad">Periodicidad</label>
                                <select class="form-control" name="periodicidad" id="crear_susc_periodicidad">
                                    <option value="diaria">Diaria</option>
                                    <option value="semanal">Semanal</option>
                                    <option value="mensual">Mensual</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="control-label" for="crear_susc_hora">Hora</label>
                                <input type="time" class="form-control" name="hora" id="crear_susc_hora" value="08:00" required>
                            </div>
                        </div>
                        <div class="col-md-4" id="wrap-crear-dia-semana">
                            <div class="form-group">
                                <label class="control-label" for="crear_susc_dia_semana">Día semana</label>
                                <select class="form-control" name="dia_semana" id="crear_susc_dia_semana">
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
                        <div class="col-md-4 d-none" id="wrap-crear-dia-mes">
                            <div class="form-group">
                                <label class="control-label" for="crear_susc_dia_mes">Día del mes</label>
                                <input type="number" class="form-control" name="dia_mes" id="crear_susc_dia_mes" min="1" max="28" step="1" value="1">
                            </div>
                        </div>
                    </div>
                    <div class="form-group mb-0">
                        <label class="control-label" for="crear_susc_destinatarios">Destinatarios (mails separados por coma)</label>
                        <textarea class="form-control" name="destinatarios" id="crear_susc_destinatarios" rows="3" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary btn-sm" data-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-plus"></i> Crear</button>
                </div>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    function syncCrearPeriodicidad() {
        var p = $('#crear_susc_periodicidad').val();
        $('#wrap-crear-dia-semana').toggleClass('d-none', p !== 'semanal');
        $('#wrap-crear-dia-mes').toggleClass('d-none', p !== 'mensual');
    }
    function syncCrearFamilia() {
        var f = $('#crear_susc_familia').val();
        var esCont = f === 'contable_definible';
        var esSue = f === 'sueldos_definible';
        $('#wrap-crear-reporte-contable').toggleClass('d-none', !esCont);
        $('#wrap-crear-reporte-sueldos').toggleClass('d-none', !esSue);
        if (esCont) {
            $('#crear_susc_reporte_id').val($('#crear_susc_reporte_contable').val() || '');
        } else if (esSue) {
            $('#crear_susc_reporte_id').val($('#crear_susc_reporte_sueldos').val() || '');
        } else {
            $('#crear_susc_reporte_id').val('');
        }
    }
    $(document).on('change', '#crear_susc_periodicidad', syncCrearPeriodicidad);
    $(document).on('change', '#crear_susc_familia, #crear_susc_reporte_contable, #crear_susc_reporte_sueldos', syncCrearFamilia);
    $(document).on('click', '.js-crear-suscripcion-programador', function () {
        syncCrearPeriodicidad();
        syncCrearFamilia();
        $('#modal-crear-suscripcion-programador').modal('show');
    });
    $('#form-crear-suscripcion-programador').on('submit', function () {
        syncCrearFamilia();
    });
})();
</script>
