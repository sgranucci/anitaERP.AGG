@extends("theme.$theme.layout")
@section('titulo')
WhatsApp — {{ $empresa->nombre }}
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-10">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-whatsapp"></i> {{ $empresa->nombre }}</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_empresa_whatsapp') }}" class="btn btn-outline-light btn-sm">Volver</a>
                </div>
            </div>
            <form method="POST" action="{{ route('actualizar_empresa_whatsapp', $empresa->id) }}" data-enter-campos="1" autocomplete="off">
                @csrf @method('PUT')
                <div class="card-body">
                    <p class="text-muted small">
                        Meta Cloud API (WABA). El canal de avisos solo envía si esta cuenta está <strong>activa</strong>
                        y tiene phone number ID + access token.
                    </p>
                    <div class="form-check mb-3">
                        <input type="hidden" name="activo" value="0">
                        <input type="checkbox" class="form-check-input" name="activo" id="activo" value="1"
                            @if(old('activo', $cuenta->activo)) checked @endif>
                        <label class="form-check-label" for="activo">Cuenta activa</label>
                    </div>
                    <div class="form-group">
                        <label for="nombre">Nombre / etiqueta</label>
                        <input type="text" class="form-control" name="nombre" id="nombre" maxlength="120"
                            value="{{ old('nombre', $cuenta->nombre) }}">
                    </div>
                    <div class="form-group">
                        <label for="proveedor">Proveedor</label>
                        <select class="form-control" name="proveedor" id="proveedor">
                            <option value="meta_cloud" @if(old('proveedor', $cuenta->proveedor ?: 'meta_cloud') === 'meta_cloud') selected @endif>Meta Cloud API</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="phone_number_id">Phone number ID</label>
                        <input type="text" class="form-control" name="phone_number_id" id="phone_number_id" maxlength="80"
                            value="{{ old('phone_number_id', $cuenta->phone_number_id) }}">
                    </div>
                    <div class="form-group">
                        <label for="waba_id">WABA ID (opcional)</label>
                        <input type="text" class="form-control" name="waba_id" id="waba_id" maxlength="80"
                            value="{{ old('waba_id', $cuenta->waba_id) }}">
                    </div>
                    <div class="form-group">
                        <label for="api_version">API version</label>
                        <input type="text" class="form-control" name="api_version" id="api_version" maxlength="20"
                            value="{{ old('api_version', $cuenta->api_version ?: 'v21.0') }}">
                    </div>
                    <div class="form-group">
                        <label for="access_token">Access token</label>
                        <input type="password" class="form-control" name="access_token" id="access_token" maxlength="4000"
                            placeholder="{{ $cuenta->access_token ? '•••• (dejar vacío para no cambiar)' : '' }}">
                        <small class="text-muted">Se guarda cifrado. Vacío = conservar el actual.</small>
                    </div>
                </div>
                <div class="card-footer">
                    @include('includes.boton-form-editar')
                </div>
            </form>
        </div>

        <div class="card card-outline card-info mt-3">
            <div class="card-header">
                <h3 class="card-title mb-0">Prueba de envío</h3>
            </div>
            <form method="POST" action="{{ route('prueba_empresa_whatsapp', $empresa->id) }}" data-enter-campos="1">
                @csrf
                <div class="card-body">
                    <p class="text-muted small mb-2">
                        Envía un WhatsApp de prueba al usuario indicado (por defecto: vos).
                        Requiere cuenta activa, <code>WHATSAPP_DRIVER=meta_cloud</code> y celular en el usuario.
                    </p>
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-4 mb-0">
                            <label for="usuario_id_prueba">Usuario destino (ID)</label>
                            <input type="number" min="1" class="form-control" name="usuario_id" id="usuario_id_prueba"
                                value="{{ old('usuario_id', auth()->id()) }}">
                        </div>
                        <div class="form-group col-md-4 mb-0">
                            <button type="submit" class="btn btn-outline-primary btn-sm">Enviar prueba</button>
                            <a href="{{ route('consultar_whatsapp_envio_log') }}" class="btn btn-outline-info btn-sm">Ver log</a>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
