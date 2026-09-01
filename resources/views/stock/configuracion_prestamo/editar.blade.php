@extends("theme.$theme.layout")
@section('titulo')
Configuración de salida de bienes
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cog"></i> Configuración de salida de bienes</h3>
            </div>
            <form action="{{ route('actualizar_configuracion_salida_bienes') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    <div class="alert alert-info py-2">
                        Las <strong>plantillas de correo</strong> (asunto y textos de notificación) se configuran en
                        <a href="{{ route('consultar_modulo_aviso') }}">Configuración → Avisos por módulo</a>
                        (tipos de aviso del módulo Stock / salida de bienes).
                        En esta pantalla se mantienen las reglas operativas del circuito.
                    </div>
                    <h5>Notificaciones</h5>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="enviar_aprobacion" id="enviar_aprobacion" value="1"
                            @if (old('enviar_aprobacion', $config->enviar_aprobacion)) checked @endif>
                        <label class="form-check-label" for="enviar_aprobacion">Enviar correo de aprobación al confirmar el envío</label>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="enviar_recordatorios" id="enviar_recordatorios" value="1"
                            @if (old('enviar_recordatorios', $config->enviar_recordatorios)) checked @endif>
                        <label class="form-check-label" for="enviar_recordatorios">Enviar recordatorios automáticos de devolución</label>
                    </div>

                    <hr>
                    <h5>Reglas de envío</h5>
                    <div class="row">
                        <div class="col-md-4">
                            <label>Días antes del vencimiento para empezar a recordar</label>
                            <input type="number" min="0" max="60" class="form-control" name="dias_antes_devolucion_aviso"
                                value="{{ old('dias_antes_devolucion_aviso', $config->dias_antes_devolucion_aviso) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label>Cada cuántos días repetir mientras esté pendiente</label>
                            <input type="number" min="1" max="60" class="form-control" name="dias_repeticion_vencido"
                                value="{{ old('dias_repeticion_vencido', $config->dias_repeticion_vencido) }}" required>
                        </div>
                        <div class="col-md-4">
                            <label>Vigencia (en horas) de los enlaces aprobar/rechazar del mail</label>
                            <input type="number" min="1" max="8760" class="form-control" name="horas_validez_token"
                                value="{{ old('horas_validez_token', $config->horas_validez_token) }}" required>
                        </div>
                    </div>

                    <hr>
                    <h5>Asuntos de los correos</h5>
                    <div class="form-group">
                        <label>Asunto al pedir aprobación</label>
                        <input type="text" class="form-control" name="mail_asunto_aprobacion" maxlength="255"
                            value="{{ old('mail_asunto_aprobacion', $config->mail_asunto_aprobacion) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto del recordatorio</label>
                        <input type="text" class="form-control" name="mail_asunto_recordatorio" maxlength="255"
                            value="{{ old('mail_asunto_recordatorio', $config->mail_asunto_recordatorio) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto cuando ya está vencido</label>
                        <input type="text" class="form-control" name="mail_asunto_devolucion_vencida" maxlength="255"
                            value="{{ old('mail_asunto_devolucion_vencida', $config->mail_asunto_devolucion_vencida) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto al solicitante cuando aprueban</label>
                        <input type="text" class="form-control" name="mail_asunto_aprobado_solicitante" maxlength="255"
                            value="{{ old('mail_asunto_aprobado_solicitante', $config->mail_asunto_aprobado_solicitante) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto al solicitante cuando rechazan</label>
                        <input type="text" class="form-control" name="mail_asunto_rechazado_solicitante" maxlength="255"
                            value="{{ old('mail_asunto_rechazado_solicitante', $config->mail_asunto_rechazado_solicitante) }}" required>
                    </div>

                    <hr>
                    <h5>Cuentas</h5>
                    <div class="form-group">
                        <label>Remitente (From)</label>
                        <input type="email" class="form-control" name="mail_remitente" maxlength="255"
                            placeholder="ejemplo: prestamos@miempresa.com"
                            value="{{ old('mail_remitente', $config->mail_remitente) }}">
                    </div>
                    <div class="form-group">
                        <label>Copia (CC, separadas por coma)</label>
                        <input type="text" class="form-control" name="mail_copia_a" maxlength="1000"
                            placeholder="aux1@empresa.com, aux2@empresa.com"
                            value="{{ old('mail_copia_a', $config->mail_copia_a) }}">
                    </div>

                    <hr>
                    <h5>Textos opcionales</h5>
                    <div class="form-group">
                        <label>Texto adicional en el correo de aprobación</label>
                        <textarea class="form-control" name="mail_texto_aprobacion" rows="3" maxlength="5000">{{ old('mail_texto_aprobacion', $config->mail_texto_aprobacion) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Texto adicional en los recordatorios</label>
                        <textarea class="form-control" name="mail_texto_recordatorio" rows="3" maxlength="5000">{{ old('mail_texto_recordatorio', $config->mail_texto_recordatorio) }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Texto adicional cuando está vencido</label>
                        <textarea class="form-control" name="mail_texto_devolucion_vencida" rows="3" maxlength="5000">{{ old('mail_texto_devolucion_vencida', $config->mail_texto_devolucion_vencida) }}</textarea>
                    </div>
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
