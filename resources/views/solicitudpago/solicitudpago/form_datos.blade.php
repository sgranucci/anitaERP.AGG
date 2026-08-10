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

        @php
            $proveedorModel = $data->proveedores ?? null;
            $proveedorIdForm = old('proveedor_id', $data->proveedor_id ?? '');
            if ($proveedorIdForm && (int) $proveedorIdForm !== (int) optional($proveedorModel)->id) {
                $proveedorModel = \App\Models\Compras\Proveedor::query()->find($proveedorIdForm);
            }
        @endphp
        @include('includes.compras.campo_proveedor_consulta', [
            'proveedor_id' => $proveedorIdForm,
            'codigo_proveedor' => old('codigoproveedor', optional($proveedorModel)->codigo ?? ''),
            'nombre_proveedor' => old('nombreproveedor', optional($proveedorModel)->nombre ?? ''),
            'col_label' => 'col-lg-4',
            'col_input' => 'col-lg-8',
            'requerido' => false,
        ])

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
                <small class="form-text text-muted">Si el concepto pertenece a un sector, selecci&oacute;nelo primero para filtrar la consulta.</small>
            </div>
        </div>

        <div class="form-group row">
            <label class="col-lg-4 col-form-label">Centro de costo</label>
            <div class="col-lg-8">
                @php
                    $ccCab = $centrocosto_cabecera ?? ($data->centrocostos ?? null);
                    $ccEtiqueta = $ccCab
                        ? trim(($ccCab->codigo ?? '').' — '.($ccCab->nombre ?? ''))
                        : '— Sin centro de costo en el usuario —';
                @endphp
                <input type="text" class="form-control" value="{{ $ccEtiqueta }}" readonly
                       title="Se asigna automáticamente con el centro de costo del usuario que carga la solicitud"/>
                <small class="form-text text-muted">Fijo al cargar la solicitud (no editable).</small>
            </div>
        </div>

        @php
            $conceptoIdForm = old('concepto_solicitudpago_id', $data->concepto_solicitudpago_id ?? '');
            $conceptoModel = $data->conceptos ?? null;
            if ($conceptoIdForm && (int) $conceptoIdForm !== (int) optional($conceptoModel)->id) {
                $conceptoModel = \App\Models\Solicitudpago\Concepto_Solicitudpago::query()->find($conceptoIdForm);
            }
            $conceptoCodigoForm = old('concepto_codigo', optional($conceptoModel)->codigo ?? '');
            $conceptoNombreForm = old('concepto_nombre', optional($conceptoModel)->nombre ?? '');
            $conceptoFormaPagoForm = old('concepto_forma_pago', optional($conceptoModel)->forma_pago ?? '');
        @endphp
        @include('solicitudpago.partials.campo_consulta_concepto_solicitudpago', [
            'conceptoId' => $conceptoIdForm,
            'codigo' => $conceptoCodigoForm,
            'nombre' => $conceptoNombreForm,
            'formaPago' => $conceptoFormaPagoForm,
            'col_label' => 'col-lg-4',
            'col_input' => 'col-lg-8',
        ])
    </div>

    {{-- Columna derecha: importe / forma de pago / destinatario / fechas --}}
    <div class="col-lg-6">
        <div class="form-group row">
            <label for="formapagosol_id" class="col-lg-4 col-form-label requerido">Forma de pago</label>
            <div class="col-lg-8">
                <select name="formapagosol_id" id="formapagosol_id" class="form-control" required>
                    <option value="">-- Seleccione --</option>
                    @foreach ($formapagosol_query as $fp)
                        @php $sel = (int) old('formapagosol_id', $data->formapagosol_id ?? 0) === (int) $fp->id; @endphp
                        <option value="{{ $fp->id }}" @selected($sel)>{{ $fp->codigo }} — {{ $fp->nombre }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="form-group row">
            <label for="moneda_id" class="col-lg-4 col-form-label requerido">Moneda</label>
            <div class="col-lg-8">
                <select name="moneda_id" id="moneda_id" class="form-control" required>
                    <option value="">-- Seleccione --</option>
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
                @php
                    $montoSpVal = old('monto', isset($data) ? $data->monto : null);
                    if ($montoSpVal === null || $montoSpVal === '') {
                        $montoSpTxt = '';
                    } else {
                        $montoSpTxt = number_format((float) str_replace(',', '.', (string) $montoSpVal), 2, ',', '.');
                    }
                @endphp
                <input type="text" inputmode="decimal" name="monto" id="monto"
                       class="form-control text-right js-monto-ar" required autocomplete="off"
                       value="{{ $montoSpTxt }}" placeholder="0,00"/>
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
                @php
                    $fechaSpForm = old('fecha', isset($data) && $data->fecha ? $data->fecha->format('Y-m-d') : date('Y-m-d'));
                    $fechaEntregaForm = old(
                        'fecha_entrega',
                        isset($data) && $data->fecha_entrega
                            ? $data->fecha_entrega->format('Y-m-d')
                            : $fechaSpForm
                    );
                @endphp
                <input type="date" name="fecha_entrega" id="fecha_entrega" class="form-control"
                       value="{{ $fechaEntregaForm }}"/>
            </div>
        </div>

        <div class="form-group row">
            <label for="fecha_vencimiento" class="col-lg-4 col-form-label requerido">Vencimiento</label>
            <div class="col-lg-5">
                <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" required
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

        @if (isset($data) && (int) ($data->solicitudpago_madre_id ?? 0) > 0)
            <div class="form-group row">
                <label class="col-lg-4 col-form-label text-right">SP madre</label>
                <div class="col-lg-8">
                    @if ($data->madre ?? null)
                        <a href="{{ route('editar_solicitudpago', ['id' => $data->madre->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="btn btn-outline-primary btn-sm font-weight-bold" target="_blank" rel="noopener"
                           title="Abrir SP madre en solapa de consulta (sin menú)">
                            <i class="fa fa-link"></i> #{{ $data->madre->codigo }}
                        </a>
                        <span class="ml-2">
                            @include('solicitudpago.solicitudpago.partials.estado_badge', ['estado' => $data->madre->estado ?? ''])
                        </span>
                    @else
                        <span class="text-muted">ID {{ $data->solicitudpago_madre_id }}</span>
                    @endif
                    <input type="hidden" name="solicitudpago_madre_id" id="solicitudpago_madre_id"
                           value="{{ $data->solicitudpago_madre_id }}"/>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Textos largos a ancho completo --}}
<div class="row">
    <div class="col-lg-12">
        <div class="form-group row">
            <label for="detalle" class="col-lg-2 col-form-label requerido">Detalle</label>
            <div class="col-lg-10">
                <input type="text" name="detalle" id="detalle" class="form-control" maxlength="180" required
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
