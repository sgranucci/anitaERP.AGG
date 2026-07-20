@php
    use App\Support\Solicitudpago\SolicitudpagoEstados;
    use App\Support\Solicitudpago\SolicitudpagoTratamientos;
    $tratamientoActual = old('tratamiento', $data->tratamiento ?? SolicitudpagoTratamientos::NORMAL);
    $estadoActual = old('estado', $data->estado ?? SolicitudpagoEstados::EMITIDA);
    $estadoNombre = $estadoActual;
    foreach ($estado_enum as $opt) {
        if ($opt['valor'] === $estadoActual) {
            $estadoNombre = $opt['nombre'];
            break;
        }
    }
@endphp
<div class="row">
    {{-- Columna izquierda: cabecera / clasificaci&oacute;n --}}
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="codigo" class="col-lg-4 col-form-label">C&oacute;digo</label>
            <div class="col-lg-4">
                @if (isset($data))
                    <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
                @else
                    <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                           value="{{ old('codigo') }}" placeholder="Autom&aacute;tico"/>
                @endif
            </div>
        </div>

        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? session('empresa_id')),
            'mostrar_id' => true,
            'col_label' => 'col-lg-4',
            'col_input' => 'col-lg-8',
            'required' => true,
        ])

        <div class="form-group row">
            <label for="fecha" class="col-lg-4 col-form-label requerido">Fecha</label>
            <div class="col-lg-5">
                <input type="date" name="fecha" id="fecha" class="form-control" required
                       value="{{ old('fecha', isset($data) && $data->fecha ? $data->fecha->format('Y-m-d') : date('Y-m-d')) }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="tratamiento" class="col-lg-4 col-form-label requerido">Tratamiento</label>
            <div class="col-lg-8">
                <select name="tratamiento" id="tratamiento" class="form-control" required>
                    @foreach ($tratamiento_enum as $opt)
                        <option value="{{ $opt['valor'] }}" @selected($tratamientoActual === $opt['valor'])>{{ $opt['nombre'] }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 col-form-label">Proveedor</label>
            <div class="col-lg-8">
                <div class="d-flex flex-nowrap align-items-center" style="gap: 4px;">
                    <input type="hidden" name="proveedor_id" id="proveedor_id" class="proveedor_id"
                           value="{{ old('proveedor_id', $data->proveedor_id ?? '') }}">
                    <button type="button" title="Consulta proveedores" class="btn-accion-tabla consultaproveedor tooltipsC flex-shrink-0">
                        <i class="fa fa-search text-primary"></i>
                    </button>
                    <input type="text" class="form-control proveedor" id="proveedor" name="proveedor" readonly
                           style="flex: 1 1 auto;"
                           value="{{ old('proveedor', optional($data->proveedores ?? null)->nombre ?? '') }}">
                </div>
            </div>
        </div>

        <div class="form-group row">
            <label for="concepto_solicitudpago_id" class="col-lg-4 col-form-label">Concepto</label>
            <div class="col-lg-8">
                <select name="concepto_solicitudpago_id" id="concepto_solicitudpago_id" class="form-control"
                        data-forma-pago-cuotas="CUOTAS">
                    <option value="">-- Sin concepto --</option>
                    @foreach ($concepto_query as $c)
                        @php $sel = (int) old('concepto_solicitudpago_id', $data->concepto_solicitudpago_id ?? 0) === (int) $c->id; @endphp
                        <option value="{{ $c->id }}" data-forma-pago="{{ $c->forma_pago }}" @selected($sel)>
                            {{ $c->codigo }} — {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="sector_solicitudpago_id" class="col-lg-4 col-form-label">Sector</label>
            <div class="col-lg-8">
                <select name="sector_solicitudpago_id" id="sector_solicitudpago_id" class="form-control">
                    <option value="">-- Sin sector --</option>
                    @foreach ($sector_query as $sector)
                        @php $sel = (int) old('sector_solicitudpago_id', $data->sector_solicitudpago_id ?? 0) === (int) $sector->id; @endphp
                        <option value="{{ $sector->id }}" @selected($sel)>{{ $sector->codigo }} — {{ $sector->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="formapagosol_id" class="col-lg-4 col-form-label">Forma de pago</label>
            <div class="col-lg-8">
                <select name="formapagosol_id" id="formapagosol_id" class="form-control">
                    <option value="">-- Sin forma --</option>
                    @foreach ($formapagosol_query as $fp)
                        @php $sel = (int) old('formapagosol_id', $data->formapagosol_id ?? 0) === (int) $fp->id; @endphp
                        <option value="{{ $fp->id }}" @selected($sel)>{{ $fp->codigo }} — {{ $fp->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>

    {{-- Columna derecha: importe / destinatario / fechas --}}
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="moneda_id" class="col-lg-4 col-form-label">Moneda</label>
            <div class="col-lg-8">
                <select name="moneda_id" id="moneda_id" class="form-control">
                    <option value="">-- Sin moneda --</option>
                    @foreach ($moneda_query as $m)
                        @php $sel = (int) old('moneda_id', $data->moneda_id ?? 0) === (int) $m->id; @endphp
                        <option value="{{ $m->id }}" @selected($sel)>{{ $m->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="monto" class="col-lg-4 col-form-label requerido">Monto</label>
            <div class="col-lg-5">
                <input type="number" step="0.01" min="0" name="monto" id="monto" class="form-control" required
                       value="{{ old('monto', $data->monto ?? '0') }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="beneficiario" class="col-lg-4 col-form-label">Beneficiario</label>
            <div class="col-lg-8">
                <input type="text" name="beneficiario" id="beneficiario" class="form-control" maxlength="80"
                       value="{{ old('beneficiario', $data->beneficiario ?? '') }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="endoso" class="col-lg-4 col-form-label">Endoso</label>
            <div class="col-lg-8">
                <input type="text" name="endoso" id="endoso" class="form-control" maxlength="80"
                       value="{{ old('endoso', $data->endoso ?? '') }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="fecha_entrega" class="col-lg-4 col-form-label">Fecha entrega</label>
            <div class="col-lg-5">
                <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control"
                       value="{{ old('fecha_entrega', isset($data) && $data->fecha_entrega ? $data->fecha_entrega->format('Y-m-d') : '') }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="fecha_vencimiento" class="col-lg-4 col-form-label">Vencimiento</label>
            <div class="col-lg-5">
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control"
                       value="{{ old('fecha_vencimiento', isset($data) && $data->fecha_vencimiento ? $data->fecha_vencimiento->format('Y-m-d') : '') }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="estado_mostrar" class="col-lg-4 col-form-label">Estado</label>
            <div class="col-lg-8">
                <input type="text" id="estado_mostrar" class="form-control" readonly
                       value="{{ $estadoNombre }}"/>
                <input type="hidden" name="estado" value="{{ $estadoActual }}"/>
            </div>
        </div>

        @if (isset($data) && $data->madre)
            <div class="form-group row">
                <label class="col-lg-4 col-form-label">SP madre</label>
                <div class="col-lg-8">
                    <a href="{{ route('editar_solicitudpago', $data->madre->id) }}" target="_blank" rel="noopener">
                        #{{ $data->madre->codigo }}
                    </a>
                    <input type="hidden" name="solicitudpago_madre_id" value="{{ $data->solicitudpago_madre_id }}"/>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Textos largos a ancho completo --}}
<div class="row">
    <div class="col-lg-12">
        <div class="form-group row">
            <label for="detalle" class="col-lg-2 col-form-label">Detalle</label>
            <div class="col-lg-10">
                <input type="text" name="detalle" id="detalle" class="form-control" maxlength="180"
                       value="{{ old('detalle', $data->detalle ?? '') }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="observacion" class="col-lg-2 col-form-label">Observaci&oacute;n</label>
            <div class="col-lg-10">
                <input type="text" name="observacion" id="observacion" class="form-control" maxlength="160"
                       value="{{ old('observacion', $data->observacion ?? '') }}"/>
            </div>
        </div>
    </div>
</div>
