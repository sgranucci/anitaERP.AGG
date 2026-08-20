@extends("theme.$theme.layout")
@section('titulo')
Nuevo certificado SENASA Surmar
@endsection

@section('scripts')
<script src="{{ asset('assets/pages/scripts/admin/crear.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/cliente/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/transporte/consulta.js') }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/ventas/camion/consulta.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/camion/consulta.js')) ?: time() }}" type="text/javascript"></script>
<script src="{{ asset('assets/pages/scripts/stock/certificado_senasa_surmar/crear.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/stock/certificado_senasa_surmar/crear.js')) ?: time() }}" type="text/javascript"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')
        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title"><i class="fa fa-certificate"></i> Nuevo certificado SENASA Surmar</h3>
                <div class="card-tools">
                    <a href="{{ route('certificado_senasa_surmar') }}" class="btn btn-outline-info btn-sm"><i class="fa fa-reply-all"></i> Volver</a>
                </div>
            </div>
            <form action="{{ route('guardar_certificado_senasa_surmar') }}" method="POST" id="form-cert-senasa-surmar" class="form-horizontal" autocomplete="off">
                @csrf
                <input type="hidden" name="empresa_id" id="empresa_id" value="{{ $empresa_id }}">
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Se crea en estado <strong>provisorio</strong>. Después asociás ítems y etiquetas; al confirmar se genera el remito cárnico AFIP y el XML SENASA.
                    </p>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Empresa</label>
                        <div class="col-lg-6">
                            <input type="text" class="form-control" value="Surmar" readonly>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Fecha</label>
                        <div class="col-lg-3">
                            <input type="date" name="fecha" id="fecha" class="form-control" value="{{ old('fecha', date('Y-m-d')) }}" required>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2 requerido">Cliente</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="hidden" name="cliente_id" id="cliente_id" class="cliente_id" value="{{ old('cliente_id') }}">
                                <input type="text" id="codigocliente" class="form-control codigocliente" placeholder="Cód." style="max-width:7rem;" title="Código cliente">
                                <input type="text" id="nombrecliente" class="form-control nombrecliente descripcioncliente" placeholder="Cliente" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultacliente" title="Consultar clientes (F1)"><i class="fa fa-search"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @include('ventas.partials.campo_consulta_camion', [
                        'camionId' => old('camion_id', optional($camionSeleccionado)->id ?? ''),
                        'codigo' => old('camion_codigo', optional($camionSeleccionado)->codigo ?? ''),
                        'descripcion' => old('camion_descripcion', optional($camionSeleccionado)->descripcionConsulta() ?? ''),
                        'col_label' => 'col-lg-4 control-label text-right pr-2',
                        'col_input' => 'col-lg-6',
                        'focusSiguiente' => '#codigotransporte',
                    ])
                    <div class="form-group row tm-transporte-campo">
                        <label class="col-lg-4 control-label text-right pr-2" for="codigotransporte">Reparto</label>
                        <div class="col-lg-6">
                            <div class="input-group">
                                <input type="hidden" name="transporte_id" id="transporte_id" class="transporte_id"
                                    value="{{ old('transporte_id', optional($transporteSeleccionado)->id ?? '') }}">
                                <input type="text" class="form-control codigotransporte" id="codigotransporte" name="codigotransporte"
                                    value="{{ old('codigotransporte', optional($transporteSeleccionado)->codigo ?? '') }}"
                                    placeholder="C&oacute;d." title="C&oacute;digo; Enter valida; F1 consulta" autocomplete="off" style="max-width:7rem;">
                                <input type="text" class="form-control nombretransporte" id="nombretransporte"
                                    value="{{ old('nombretransporte', optional($transporteSeleccionado)->nombre ?? '') }}"
                                    placeholder="Reparto" readonly>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-secondary consultatransporte" title="Consultar repartos (F1)">
                                        <i class="fa fa-search"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2" for="precinto">Precinto</label>
                        <div class="col-lg-3">
                            <input type="text" name="precinto" id="precinto" class="form-control" maxlength="15" value="{{ old('precinto') }}">
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2" for="cantidad_precinto">Cant. precinto</label>
                        <div class="col-lg-2">
                            <input type="number" name="cantidad_precinto" id="cantidad_precinto" class="form-control" min="0" value="{{ old('cantidad_precinto', optional($camionSeleccionado)->cantidad_precinto ?? '') }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2" for="temperatura">Temperatura</label>
                        <div class="col-lg-2">
                            <input type="number" step="0.1" name="temperatura" id="temperatura" class="form-control" value="{{ old('temperatura') }}">
                        </div>
                        <label class="col-lg-2 control-label text-right pr-2" for="punto_emision">Punto emisión</label>
                        <div class="col-lg-2">
                            <input type="number" name="punto_emision" id="punto_emision" class="form-control" min="1" value="{{ old('punto_emision', $punto_emision) }}">
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Generar</label>
                        <div class="col-lg-6">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="genera_remito" id="genera_remito" value="1" @if (old('genera_remito', '1') == '1') checked @endif>
                                <label class="form-check-label" for="genera_remito">Remito cárnico AFIP</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="checkbox" name="genera_web" id="genera_web" value="1" @if (old('genera_web', '1') == '1') checked @endif>
                                <label class="form-check-label" for="genera_web">XML SENASA WEB</label>
                            </div>
                        </div>
                    </div>
                    <div class="form-group row">
                        <label class="col-lg-4 control-label text-right pr-2">Observación</label>
                        <div class="col-lg-6">
                            <textarea name="observacion" class="form-control" rows="2">{{ old('observacion') }}</textarea>
                        </div>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-arrow-right"></i> Iniciar carga</button>
                </div>
            </form>
        </div>
    </div>
</div>
@include('includes.ventas.modalconsultacliente')
@include('includes.ventas.modalconsultatransporte')
@include('includes.ventas.modalconsultacamion')
@endsection
