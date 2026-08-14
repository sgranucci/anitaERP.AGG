@extends("theme.$theme.layout")
@section('titulo')
Configuración de asientos contables
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-cog"></i> Aprobación de asientos por cuentas no autorizadas</h3>
            </div>
            <form action="{{ route('actualizar_configuracion_asiento_contable') }}" id="form-general" class="form-horizontal" method="POST" autocomplete="off">
                @csrf @method('put')
                <div class="card-body">
                    <div class="alert alert-info py-2">
                        Cuando un usuario con cuentas asignadas carga un asiento con cuentas fuera de su lista,
                        el asiento queda <strong>pendiente</strong> hasta que contaduría lo apruebe.
                        Configure aquí el correo del aprobador y las reglas de notificación.
                    </div>

                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" name="enviar_mail_aprobacion" id="enviar_mail_aprobacion" value="1"
                            @if (old('enviar_mail_aprobacion', $config->enviar_mail_aprobacion)) checked @endif>
                        <label class="form-check-label" for="enviar_mail_aprobacion">Enviar correo al aprobador cuando hay un asiento pendiente</label>
                    </div>

                    <div class="form-group">
                        <label for="mail_aprobador">Correo del aprobador (contaduría) *</label>
                        <input type="email" class="form-control" name="mail_aprobador" id="mail_aprobador" maxlength="255"
                            placeholder="contaduria@empresa.com"
                            value="{{ old('mail_aprobador', $config->mail_aprobador) }}" required>
                        <small class="form-text text-muted">Destinatario principal de los avisos con enlace para aprobar desde el mail.</small>
                    </div>

                    <div class="form-group">
                        <label>Copia (CC, separadas por coma)</label>
                        <input type="text" class="form-control" name="mail_copia_a" maxlength="255"
                            value="{{ old('mail_copia_a', $config->mail_copia_a) }}">
                    </div>

                    <div class="form-group">
                        <label>Vigencia (horas) de los enlaces del mail</label>
                        <input type="number" min="1" max="8760" class="form-control" name="horas_validez_token"
                            value="{{ old('horas_validez_token', $config->horas_validez_token) }}" required>
                    </div>

                    <hr>
                    <h5>Asuntos de correo</h5>
                    <div class="form-group">
                        <label>Asunto — solicitud de aprobación</label>
                        <input type="text" class="form-control" name="mail_asunto_aprobacion" maxlength="255"
                            value="{{ old('mail_asunto_aprobacion', $config->mail_asunto_aprobacion) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto — aviso al usuario si aprueban</label>
                        <input type="text" class="form-control" name="mail_asunto_aprobado_solicitante" maxlength="255"
                            value="{{ old('mail_asunto_aprobado_solicitante', $config->mail_asunto_aprobado_solicitante) }}" required>
                    </div>
                    <div class="form-group">
                        <label>Asunto — aviso al usuario si rechazan</label>
                        <input type="text" class="form-control" name="mail_asunto_rechazado_solicitante" maxlength="255"
                            value="{{ old('mail_asunto_rechazado_solicitante', $config->mail_asunto_rechazado_solicitante) }}" required>
                    </div>

                    <div class="form-group">
                        <label>Texto introductorio en el mail al aprobador</label>
                        <textarea class="form-control" name="mail_texto_aprobacion" rows="3" maxlength="5000">{{ old('mail_texto_aprobacion', $config->mail_texto_aprobacion) }}</textarea>
                    </div>

                    <hr>
                    <h5>Impresión al dar de alta</h5>
                    <div class="form-group">
                        <label for="formato_impresion_alta">Formato de salida automática</label>
                        @php
                            $formatoImpresionAlta = old(
                                'formato_impresion_alta',
                                $config->formatoImpresionAltaNormalizado()
                            );
                        @endphp
                        <select class="form-control" name="formato_impresion_alta" id="formato_impresion_alta" required>
                            <option value="excel" @if ($formatoImpresionAlta === 'excel') selected @endif>
                                Excel
                            </option>
                            <option value="pdf" @if ($formatoImpresionAlta === 'pdf') selected @endif>
                                PDF
                            </option>
                            <option value="ninguno" @if ($formatoImpresionAlta === 'ninguno') selected @endif>
                                Ninguno (no abrir nada)
                            </option>
                        </select>
                        <small class="form-text text-muted">
                            Al grabar un asiento nuevo se abre automáticamente este formato en otra pestaña.
                        </small>
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
