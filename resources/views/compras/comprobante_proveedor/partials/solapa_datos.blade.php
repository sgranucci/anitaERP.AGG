<div class="row">
    <div class="col-sm-6">
        @include('includes.form-empresa-asignada', [
            'empresa_query' => $empresa_query,
            'empresa_id' => old('empresa_id', $data->empresa_id ?? session('empresa_id')),
            'mostrar_id' => true,
            'col_label' => 'col-lg-3',
            'col_input' => 'col-lg-7',
        ])
        <div class="form-group row">
            <label for="tipotransaccion_compra_id" class="col-lg-3 col-form-label requerido">Tipo comprobante</label>
            <select name="tipotransaccion_compra_id" id="tipotransaccion_compra_id" class="col-lg-8 form-control required" required>
                <option value="">-- Seleccionar --</option>
                @foreach ($tipotransaccion_compra_query as $value)
                    <option value="{{ $value->id }}"
                        data-abreviatura="{{ $value->abreviatura }}"
                        @if ((int) old('tipotransaccion_compra_id', $data->tipotransaccion_compra_id ?? 0) === (int) $value->id) selected @endif>
                        {{ $value->nombre }}
                    </option>
                @endforeach
            </select>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 col-form-label requerido">Número</label>
            <input type="text" name="letra" id="letra" class="col-lg-1 form-control" maxlength="1"
                value="{{ old('letra', $data->letra ?? '') }}" required>
            <span class="input-group-text">#</span>
            <input type="number" name="sucursal" id="sucursal" class="col-lg-2 form-control"
                value="{{ old('sucursal', $data->sucursal ?? '') }}" required>
            <span class="input-group-text">#</span>
            <input type="number" name="numerocomprobante" id="numerocomprobante" class="col-lg-3 form-control"
                value="{{ old('numerocomprobante', $data->numerocomprobante ?? '') }}" required>
        </div>
        @include('includes.compras.campo_proveedor_consulta', [
            'proveedor_id' => ($data ?? null)?->proveedor_id,
            'codigo_proveedor' => ($data ?? null)?->proveedores?->codigo,
            'nombre_proveedor' => ($data ?? null)?->proveedores?->nombre,
            'requerido' => true,
            'mostrar_aviso_cuenta' => true,
        ])
        <div class="form-group row">
            <label for="modo_carga" class="col-lg-3 col-form-label">Modo de carga</label>
            <select name="modo_carga" id="modo_carga" class="col-lg-8 form-control">
                @foreach ($modos_carga as $modo)
                    <option value="{{ $modo }}" @if (old('modo_carga', $data->modo_carga ?? '') === $modo) selected @endif>
                        {{ \App\Support\Compras\ComprobanteProveedorModoCarga::etiqueta($modo) }}
                    </option>
                @endforeach
            </select>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="fechacomprobante" class="col-lg-4 col-form-label requerido">Fecha comprobante</label>
            <div class="col-lg-4">
                <input type="date" name="fechacomprobante" id="fechacomprobante" class="form-control"
                    value="{{ old('fechacomprobante', $data->fechacomprobante instanceof \DateTimeInterface ? $data->fechacomprobante->format('Y-m-d') : ($data->fechacomprobante ?? date('Y-m-d'))) }}" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="fechaiva" class="col-lg-4 col-form-label requerido">Fecha IVA</label>
            <div class="col-lg-4">
                <input type="date" name="fechaiva" id="fechaiva" class="form-control"
                    value="{{ old('fechaiva', $data->fechaiva instanceof \DateTimeInterface ? $data->fechaiva->format('Y-m-d') : ($data->fechaiva ?? date('Y-m-d'))) }}" required>
            </div>
        </div>
        <div class="form-group row">
            <label for="fecharecepcion" class="col-lg-4 col-form-label">Recepción email</label>
            <div class="col-lg-4">
                <input type="datetime-local" name="fecharecepcion" id="fecharecepcion" class="form-control"
                    value="{{ old('fecharecepcion', $data->fecharecepcion ? $data->fecharecepcion->format('Y-m-d\TH:i') : '') }}">
            </div>
        </div>
        <div class="form-group row">
            <label for="fechavencimiento" class="col-lg-4 col-form-label">Vencimiento</label>
            <div class="col-lg-4">
                <input type="date" name="fechavencimiento" id="fechavencimiento" class="form-control"
                    value="{{ old('fechavencimiento', $data->fechavencimiento instanceof \DateTimeInterface ? $data->fechavencimiento->format('Y-m-d') : ($data->fechavencimiento ?? '')) }}">
            </div>
        </div>
        @if ($data->ordencompra_id ?? null)
        <div class="form-group row">
            <label class="col-lg-4 col-form-label">Orden de compra</label>
            <div class="col-lg-6">
                <p class="form-control-plaintext">
                    #{{ $data->ordencompras->numeroordencompra ?? $data->ordencompra_id }}
                    @if (can('editar-ordencompra', false))
                    <a href="{{ route('editar_ordencompra', ['id' => $data->ordencompra_id]) }}" target="_blank" rel="noopener" class="text-primary">Abrir OC</a>
                    @endif
                </p>
            </div>
        </div>
        @endif
        @if ($data->precarga_comprobante_proveedor_id ?? null)
        <div class="form-group row">
            <label class="col-lg-4 col-form-label">Precarga</label>
            <div class="col-lg-6">
                <p class="form-control-plaintext">
                    #{{ $data->precarga_comprobante_proveedor_id }}
                    @if (can('editar-precarga-proveedores', false))
                    <a href="{{ route('editar_precarga_comprobante_proveedor', ['id' => $data->precarga_comprobante_proveedor_id]) }}" target="_blank" rel="noopener" class="text-primary">Abrir precarga</a>
                    @endif
                </p>
            </div>
        </div>
        @endif
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="numerocae" class="col-lg-3 col-form-label">CAE</label>
            <div class="col-lg-4">
                <input type="text" name="numerocae" id="numerocae" class="form-control"
                    value="{{ old('numerocae', $data->numerocae ?? '') }}">
            </div>
            <label for="fechavencimientocae" class="col-lg-3 col-form-label">Vto. CAE</label>
            <div class="col-lg-2">
                <input type="date" name="fechavencimientocae" id="fechavencimientocae" class="form-control"
                    value="{{ old('fechavencimientocae', $data->fechavencimientocae instanceof \DateTimeInterface ? $data->fechavencimientocae->format('Y-m-d') : ($data->fechavencimientocae ?? '')) }}">
            </div>
        </div>
        <div class="form-group row">
            <label for="moneda_id" class="col-lg-3 col-form-label requerido">Moneda</label>
            <div class="col-lg-3">
                <input type="number" name="moneda_id" id="moneda_id" class="form-control"
                    value="{{ old('moneda_id', $data->moneda_id ?? 1) }}" required>
            </div>
            <label for="cotizacion" class="col-lg-2 col-form-label">Cotización</label>
            <div class="col-lg-3">
                <input type="number" step="0.0001" name="cotizacion" id="cotizacion" class="form-control"
                    value="{{ old('cotizacion', $data->cotizacion ?? 1) }}">
            </div>
        </div>
        <div class="form-group row">
            <label class="col-lg-3 col-form-label">Moneda nombre</label>
            <div class="col-lg-6">
                <input type="text" class="form-control" readonly value="{{ $data->monedas->nombre ?? '' }}">
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group row">
            <label for="subtotal" class="col-lg-4 col-form-label">Subtotal</label>
            <div class="col-lg-4">
                <input type="number" step="0.01" name="subtotal" id="subtotal" class="form-control"
                    value="{{ old('subtotal', $data->subtotal ?? 0) }}">
            </div>
        </div>
        <div class="form-group row">
            <label for="total" class="col-lg-4 col-form-label">Total</label>
            <div class="col-lg-4">
                <input type="number" step="0.01" name="total" id="total" class="form-control"
                    value="{{ old('total', $data->total ?? 0) }}">
            </div>
        </div>
        <div class="form-group row">
            <label for="leyenda" class="col-lg-4 col-form-label">Leyenda</label>
            <div class="col-lg-8">
                <textarea name="leyenda" id="leyenda" class="form-control" rows="2">{{ old('leyenda', $data->leyenda ?? '') }}</textarea>
            </div>
        </div>
        <div class="form-group row">
            <label for="pararevisar" class="col-lg-4 col-form-label">Para revisar</label>
            <div class="col-lg-4">
                <select name="pararevisar" id="pararevisar" class="form-control">
                    <option value="0" @if (! old('pararevisar', $data->pararevisar ?? false)) selected @endif>Sin errores</option>
                    <option value="1" @if (old('pararevisar', $data->pararevisar ?? false)) selected @endif>Para revisar</option>
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="es_fce" class="col-lg-4 col-form-label">FCE</label>
            <div class="col-lg-4">
                <select name="es_fce" id="es_fce" class="form-control">
                    <option value="0" @if (! old('es_fce', $data->es_fce ?? false)) selected @endif>No</option>
                    <option value="1" @if (old('es_fce', $data->es_fce ?? false)) selected @endif>Sí</option>
                </select>
            </div>
        </div>
    </div>
</div>
