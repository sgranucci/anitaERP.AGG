@extends("theme.$theme.layout")
@section('titulo')
    Mail de facturas
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-envelope"></i> Envío de facturas por mail</h3>
            </div>
            <form method="POST" action="{{ route('actualizar_factura_mail_configuracion') }}" class="form-horizontal" autocomplete="off">
                @csrf
                @method('PUT')
                <div class="card-body">
                    <div class="alert alert-info py-2 mb-3">
                        Patrón habitual: SMTP de la instalación + flag opcional en el cliente + envío manual y/o automático al obtener CAE.
                        Placeholders: <code>{codigo}</code>, <code>{cliente}</code>, <code>{empresa}</code>, <code>{fecha}</code>.
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="empresa_id">Empresa</label>
                        <div class="col-lg-4">
                            <select name="empresa_id" id="empresa_id" class="form-control"
                                onchange="window.location='{{ route('factura_mail_configuracion') }}?empresa_id='+this.value">
                                @foreach ($empresa_query as $e)
                                    <option value="{{ $e->id }}" @selected((int) $empresa_id === (int) $e->id)>{{ $e->nombre }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Habilitado</label>
                        <div class="col-lg-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="habilitado" id="habilitado" value="1" @checked(old('habilitado', $config->habilitado))>
                                <label class="form-check-label" for="habilitado">Permitir envío de facturas por mail</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Automático al CAE</label>
                        <div class="col-lg-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="envio_automatico" id="envio_automatico" value="1" @checked(old('envio_automatico', $config->envio_automatico))>
                                <label class="form-check-label" for="envio_automatico">Enviar al obtener CAE/CAEA</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Solo clientes opt-in</label>
                        <div class="col-lg-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="exigir_flag_cliente" id="exigir_flag_cliente" value="1" @checked(old('exigir_flag_cliente', $config->exigir_flag_cliente ?? true))>
                                <label class="form-check-label" for="exigir_flag_cliente">Exigir “Enviar factura por mail” en el cliente</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2">Adjuntos</label>
                        <div class="col-lg-6">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="incluir_remito" id="incluir_remito" value="1" @checked(old('incluir_remito', $config->incluir_remito))>
                                <label class="form-check-label" for="incluir_remito">Incluir hoja remito en el PDF</label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="incluir_envio" id="incluir_envio" value="1" @checked(old('incluir_envio', $config->incluir_envio))>
                                <label class="form-check-label" for="incluir_envio">Adjuntar PDF ENVÍO (scaffold)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="asunto">Asunto</label>
                        <div class="col-lg-8">
                            <input type="text" class="form-control" name="asunto" id="asunto" maxlength="200"
                                value="{{ old('asunto', $config->asunto) }}">
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="cuerpo">Cuerpo</label>
                        <div class="col-lg-8">
                            <textarea class="form-control" name="cuerpo" id="cuerpo" rows="6" maxlength="4000">{{ old('cuerpo', $config->cuerpo) }}</textarea>
                        </div>
                    </div>

                    <div class="form-group row">
                        <label class="col-lg-3 control-label text-right pr-2" for="bcc">BCC</label>
                        <div class="col-lg-8">
                            <input type="text" class="form-control" name="bcc" id="bcc" maxlength="500"
                                value="{{ old('bcc', $config->bcc) }}"
                                placeholder="archivo@empresa.com (opcional, varios separados por coma)">
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-success"><i class="fa fa-save"></i> Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
