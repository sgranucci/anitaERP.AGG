@php
    $codigoSeleccionado = old('codigo', $data?->codigo ?? '');
    $codigoNormalizado = $codigoSeleccionado !== '' ? str_pad(preg_replace('/\D+/', '', (string) $codigoSeleccionado) ?: (string) $codigoSeleccionado, 3, '0', STR_PAD_LEFT) : '';
    $empresa_query = $empresa_query ?? collect();
    $empresaArcaId = (int) ($empresaArcaId ?? 0);
    $tiposCbteArca = $tiposCbteArca ?? [];
    $codigosArca = array_column($tiposCbteArca, 'codigo');
    $codigoFueraDeArca = $codigoNormalizado !== '' && ! in_array($codigoNormalizado, $codigosArca, true);
    $sincronizadoArcaTexto = $sincronizadoArcaTexto ?? null;
@endphp
<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-4">
       <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data?->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="abreviatura" class="col-lg-3 col-form-label requerido">Abreviatura</label>
    <div class="col-lg-2">
       <input type="text" name="abreviatura" id="abreviatura" class="form-control" value="{{old('abreviatura', $data?->abreviatura ?? '')}}" required/>
    </div>
</div>
<div class="form-group row" id="tipotransaccion-arca-panel"
     data-url-tipos="{{ route('tipotransaccion_arca_tipos_cbte') }}">
    <label for="empresa_arca_id" class="col-lg-3 col-form-label">Empresa (ARCA)</label>
    <div class="col-lg-4">
        @if($empresa_query->isEmpty())
            <select name="empresa_arca_id" id="empresa_arca_id" class="form-control" data-fouc disabled>
                <option value="">Sin empresas WSFE configuradas</option>
            </select>
        @else
            @include('includes.form-empresa-asignada-control', [
                'empresa_query' => $empresa_query,
                'empresa_id' => $empresaArcaId,
                'id' => 'empresa_arca_id',
                'name' => 'empresa_arca_id',
                'required' => false,
                'mostrar_opcion_vacia' => false,
                'data_fouc' => true,
            ])
        @endif
        <small class="form-text text-muted">
            Use <strong>Actualizar desde ARCA</strong> para cargar el catálogo AFIP.
            @if(!empty($webserviceArcaEtiqueta))
                Webservice previsto: <strong>{{ $webserviceArcaEtiqueta }}</strong>
                (según PV en modo CAE o <code>ARCA_TIPOS_CBTE_WEBSERVICE</code> en .env).
            @else
                Se elige <code>wsmtxca</code> o <code>wsfev1</code> según los puntos de venta activos en modo CAE.
            @endif
        </small>
        @if($sincronizadoArcaTexto && count($tiposCbteArca) > 0)
        <small id="tipotransaccion-webservice-arca" class="form-text text-muted">
            Catálogo local: {{ count($tiposCbteArca) }} tipos AFIP (sincronizado {{ $sincronizadoArcaTexto }}).
            Use <strong>Actualizar desde ARCA</strong> para refrescar desde AFIP.
        </small>
        @else
        <small id="tipotransaccion-webservice-arca" class="form-text text-muted d-none"></small>
        @endif
        <div id="tipotransaccion-arca-estado" class="alert alert-info py-2 px-3 mt-2 mb-0 d-none" role="status" aria-live="polite"></div>
    </div>
    <div class="col-lg-2 d-flex align-items-start">
        <button type="button" id="btn-actualizar-tipos-arca" class="btn btn-outline-secondary btn-sm"
                @if($empresa_query->isEmpty()) disabled @endif>
            <i class="fa fa-refresh" id="btn-actualizar-tipos-arca-icono"></i>
            <i class="fa fa-spinner fa-spin d-none" id="btn-actualizar-tipos-arca-spinner" aria-hidden="true"></i>
            <span id="btn-actualizar-tipos-arca-texto"> Actualizar desde ARCA</span>
        </button>
    </div>
</div>
<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label requerido">Tipo AFIP</label>
    <div class="col-lg-6">
        <select name="codigo" id="codigo" class="form-control" required data-fouc
                @if($empresa_query->isEmpty() && $codigoNormalizado === '') disabled @endif>
            <option value="">-- Elija tipo AFIP (ARCA) --</option>
            @foreach($tiposCbteArca as $tipo)
                @php
                    $tipoCodigo = (string) ($tipo['codigo'] ?? '');
                    $tipoCodigoNorm = $tipoCodigo !== '' ? str_pad(preg_replace('/\D+/', '', $tipoCodigo) ?: $tipoCodigo, 3, '0', STR_PAD_LEFT) : '';
                @endphp
                <option value="{{ $tipoCodigo }}"
                        @if($tipoCodigoNorm !== '' && $tipoCodigoNorm === $codigoNormalizado) selected @endif>
                    {{ $tipoCodigo }} — {{ $tipo['descripcion'] }}
                </option>
            @endforeach
            @if($codigoFueraDeArca)
                <option value="{{ $codigoNormalizado }}" selected>
                    {{ $codigoNormalizado }} — valor actual (no figura en catálogo local)
                </option>
            @endif
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="operacion" class="col-lg-3 col-form-label requerido">Operaci&oacute;n</label>
    <select name="operacion" class="col-lg-3 form-control" required>
        <option value="">-- Elija operaci&oacute;n --</option>
        @foreach($operacionEnum as $value => $operacion)
            @if( $value == old('operacion', $data?->operacion ?? ''))
                <option value="{{ $value }}" selected="select">{{ $operacion }}</option>    
            @else
                <option value="{{ $value }}">{{ $operacion }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="operacionstock" class="col-lg-3 col-form-label requerido">Operaci&oacute;n sobre stock</label>
    <select name="operacionstock" class="col-lg-4 form-control" required>
        <option value="">-- Elija operaci&oacute;n sobre stock --</option>
        @foreach($operacionStockEnum as $value => $operacionStock)
            @if( $value == old('operacionstock', $data?->operacionstock ?? ''))
                <option value="{{ $value }}" selected="select">{{ $operacionStock }}</option>
            @else
                <option value="{{ $value }}">{{ $operacionStock }}</option>
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="signo" class="col-lg-3 col-form-label requerido">Signo</label>
    <select name="signo" class="col-lg-3 form-control" required>
        <option value="">-- Elija signo --</option>
        @foreach($signoEnum as $value => $signo)
            @if( $value == old('signo', $data?->signo ?? ''))
                <option value="{{ $value }}" selected="select">{{ $signo }}</option>    
            @else
                <option value="{{ $value }}">{{ $signo }}</option>    
            @endif
        @endforeach
    </select>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <select name="estado" class="col-lg-3 form-control" required>
        <option value="">-- Elija estado --</option>
        @foreach($estadoEnum as $value => $estado)
            @if( $value == old('estado', $data?->estado ?? ''))
                <option value="{{ $value }}" selected="select">{{ $estado }}</option>    
            @else
                <option value="{{ $value }}">{{ $estado }}</option>    
            @endif
        @endforeach
    </select>
</div>
@include('ventas.partials.campo_consulta_concepto_venta', [
    'conceptoId' => old('concepto_venta_id', $data?->concepto_venta_id ?? ''),
    'codigo' => old('concepto_venta_codigo', $data?->conceptoVenta?->codigo ?? ''),
    'descripcion' => old('concepto_venta_nombre', $data?->conceptoVenta?->nombre ?? ''),
    'required' => false,
    'label' => 'Concepto de venta',
    'ayuda_tooltip' => 'Si está cargado, el facturador lo muestra en la cabecera (NC/ND o FAC). Vacío: no aparece el campo global.',
])
<small class="form-text text-muted col-lg-8 offset-lg-3 mb-2">
    Con concepto asignado, el facturador lo muestra al elegir este tipo. Sin asignar, en FAC se elige en el renglón (ícono de documento) y se completa el detalle.
</small>
