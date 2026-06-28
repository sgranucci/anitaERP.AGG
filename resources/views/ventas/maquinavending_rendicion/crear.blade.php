@extends("theme.$theme.layout")
@section('titulo')
    Rendici&oacute;n vending
@endsection

@section('styles')
@include('ventas.maquinavending_rendicion.partials.estilos_pantalla')
@endsection

@section('scripts')
@php
    $empresaUnica = ($empresa_query ?? collect())->count() === 1;
    $empresaInicial = (int) old('empresa_id', $empresaDefaultId ?? 0);
@endphp
<script>
    window.MV_RENDICION = {
        csrf: @json(csrf_token()),
        empresaUnica: @json($empresaUnica),
        empresaId: {{ $empresaInicial }},
        urlMaquinas: @json(route('maquinavending_rendicion_api_maquinas', ['empresaId' => '__EMP__'])),
        urlArticulosBase: @json(url('ventas/gastronomia/maquinas-vending/rendiciones/api/maquina')),
        urlCuentasCaja: @json(route('maquinavending_rendicion_api_cuentas_caja')),
        monedaFacturaId: {{ (int) config('gastronomia.moneda_factura_id', 1) }},
        usocuentacajaGastronomiaId: {{ (int) ($usocuentacaja_gastronomia_id ?? 0) }},
    };
</script>
<script src="{{ asset('assets/pages/scripts/caja/cuentacaja/consulta.js') }}"></script>
<script src="{{ asset('assets/pages/scripts/ventas/maquinavending_rendicion/form.js') }}?v={{ @filemtime(public_path('assets/pages/scripts/ventas/maquinavending_rendicion/form.js')) }}"></script>
@endsection

@section('contenido')
<div class="row">
    <div class="col-lg-12">
        @include('includes.form-error')
        @include('includes.mensaje')

        <div class="card card-primary">
            <div class="card-header">
                <h3 class="card-title">Registrar rendici&oacute;n de m&aacute;quina vending</h3>
                <div class="card-tools">
                    <a href="{{ route('consultar_maquinavending_rendicion_gastronomia') }}" class="btn btn-outline-info btn-sm">
                        <i class="fa fa-fw fa-reply-all"></i> Volver al listado
                    </a>
                </div>
            </div>
            <form action="{{ route('guardar_maquinavending_rendicion_gastronomia') }}" id="form-rendicion-vending" method="POST" autocomplete="off">
                @csrf
                <div class="card-body pb-2">
                    @if ($errors->has('general'))
                    <div class="alert alert-danger">{{ $errors->first('general') }}</div>
                    @endif

                    <div class="card card-outline card-secondary mb-3">
                        <div class="card-header py-2"><strong>Datos de la rendici&oacute;n</strong></div>
                        <div class="card-body py-2">
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="empresa_id" class="requerido">Empresa</label>
                                    @include('includes.form-empresa-asignada-control', [
                                        'empresa_query' => $empresa_query,
                                        'empresa_id' => old('empresa_id', $empresaDefaultId ?? ''),
                                        'required' => true,
                                        'opcion_vacia' => '— Seleccionar —',
                                    ])
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="maquinavending_id" class="requerido">M&aacute;quina</label>
                                    <select name="maquinavending_id" id="maquinavending_id" class="form-control" required>
                                        <option value="">— Seleccione empresa y m&aacute;quina —</option>
                                        @foreach ($maquinas_query ?? [] as $maq)
                                            <option value="{{ $maq->id }}"
                                                data-empresa="{{ $maq->empresa_id }}"
                                                @selected((int) old('maquinavending_id') === (int) $maq->id)>
                                                {{ trim(($maq->puntoventa->codigo ?? '').' — '.$maq->nombre, ' —') }}
                                            </option>
                                        @endforeach
                                    </select>
                                    <small class="text-muted">Corresponde al n&uacute;mero / PV de la m&aacute;quina definida en el ABM.</small>
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="fecha_rendicion" class="requerido">Fecha/hora</label>
                                    <input type="datetime-local" name="fecha_rendicion" id="fecha_rendicion" class="form-control" required
                                           value="{{ old('fecha_rendicion', now()->format('Y-m-d\\TH:i')) }}">
                                </div>
                                <div class="form-group col-md-2">
                                    <label for="fecha_jornada">Fecha jornada</label>
                                    <input type="date" name="fecha_jornada" id="fecha_jornada" class="form-control"
                                           value="{{ old('fecha_jornada', now()->format('Y-m-d')) }}">
                                </div>
                            </div>
                            <div class="form-group mb-0">
                                <label for="observacion">Observaciones</label>
                                <textarea name="observacion" id="observacion" class="form-control" rows="2" maxlength="65535">{{ old('observacion') }}</textarea>
                                <small class="text-muted d-block mt-1">
                                    Al guardar se asigna el pr&oacute;ximo <strong>N&ordm; cierre</strong> correlativo de la empresa seleccionada
                                    (compartido entre todas sus m&aacute;quinas vending). La m&aacute;quina queda registrada en este comprobante.
                                </small>
                            </div>
                        </div>
                    </div>

                    @include('ventas.maquinavending_rendicion.partials.form_rendicion_cuerpo', [
                        'empresaInicial' => $empresaInicial,
                    ])
                </div>
                <div class="card-footer">
                    <button type="submit" class="btn btn-primary" id="btn-guardar-rendicion" disabled>
                        <i class="fa fa-save"></i> Registrar rendici&oacute;n
                    </button>
                    <span class="text-muted small ml-2">Al guardar se asigna un n&uacute;mero de cierre por m&aacute;quina (como cierre de turno gastronom&iacute;a).</span>
                </div>
            </form>
        </div>
    </div>
</div>

@include('includes.caja.modalconsultacuentacaja')

@include('ventas.maquinavending_rendicion.partials.template_renglon_cuenta')
@endsection
