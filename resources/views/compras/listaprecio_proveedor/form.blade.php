@php
    $visualizar = ! empty($visualizar);
    $tieneLista = isset($data) && $data && ($data->id ?? null);
@endphp
<div class="row">
    <div class="col-md-6">
        <input type="hidden" name="listaprecio_proveedor_id" id="listaprecio_proveedor_id" value="{{ $tieneLista ? $data->id : '' }}">

        @include('includes.compras.campo_proveedor_consulta', [
            'proveedor_id' => ($data ?? null)?->proveedor_id,
            'codigo_proveedor' => ($data ?? null)?->proveedores?->codigo,
            'nombre_proveedor' => ($data ?? null)?->proveedores?->nombre,
            'requerido' => true,
            'solo_lectura_codigo' => $visualizar,
            'col_label' => 'col-lg-4 control-label text-right pr-2',
            'col_input' => 'col-lg-8',
        ])

        <div class="form-group row">
            <label for="fecha" class="col-lg-4 control-label text-right pr-2 requerido">Fecha lista</label>
            <div class="col-lg-8">
                <input type="date" name="fecha" id="fecha" class="form-control" required value="{{ old('fecha', (isset($data) && $data && $data->fecha) ? substr($data->fecha, 0, 10) : date('Y-m-d')) }}" {{ $visualizar ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label for="nombre" class="col-lg-4 control-label text-right pr-2 requerido">Nombre</label>
            <div class="col-lg-8">
                <input type="text" name="nombre" id="nombre" class="form-control" required maxlength="255" value="{{ old('nombre', (isset($data) && $data) ? $data->nombre : '') }}" {{ $visualizar ? 'readonly' : '' }}>
            </div>
        </div>

        <div class="form-group row">
            <label for="observaciones" class="col-lg-4 control-label text-right pr-2">Observaciones</label>
            <div class="col-lg-8">
                <textarea name="observaciones" id="observaciones" class="form-control" rows="2" {{ $visualizar ? 'readonly' : '' }}>{{ old('observaciones', (isset($data) && $data) ? ($data->observaciones ?? '') : '') }}</textarea>
            </div>
        </div>

        @if ($tieneLista)
        <div class="form-group row">
            <label for="estado" class="col-lg-4 control-label text-right pr-2">Estado</label>
            <div class="col-lg-8">
                <select name="estado" id="estado" class="form-control" {{ $visualizar ? 'disabled' : '' }}>
                    @foreach ($estado_enum as $e)
                        <option value="{{ $e['nombre'] }}" {{ old('estado', $data->estado ?? '') == $e['nombre'] ? 'selected' : '' }}>
                            {{ $e['nombre'] }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        @endif
    </div>
    <div class="col-md-6">
        <div class="form-group row">
            <label for="condicionpago_id" class="col-lg-4 control-label text-right pr-2">Condici&oacute;n pago</label>
            <div class="col-lg-8">
                <select name="condicionpago_id" id="condicionpago_id" class="form-control" {{ $visualizar ? 'disabled' : '' }}>
                    <option value="">—</option>
                    @foreach ($condicionpago_query as $c)
                        <option value="{{ $c->id }}" {{ (int) old('condicionpago_id', (isset($data) && $data) ? ($data->condicionpago_id ?? 0) : 0) === (int) $c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="condicionentrega_id" class="col-lg-4 control-label text-right pr-2">Condici&oacute;n entrega</label>
            <div class="col-lg-8">
                <select name="condicionentrega_id" id="condicionentrega_id" class="form-control" {{ $visualizar ? 'disabled' : '' }}>
                    <option value="">—</option>
                    @foreach ($condicionentrega_query as $c)
                        <option value="{{ $c->id }}" {{ (int) old('condicionentrega_id', (isset($data) && $data) ? ($data->condicionentrega_id ?? 0) : 0) === (int) $c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="condicioncompra_id" class="col-lg-4 control-label text-right pr-2">Condici&oacute;n compra</label>
            <div class="col-lg-8">
                <select name="condicioncompra_id" id="condicioncompra_id" class="form-control" {{ $visualizar ? 'disabled' : '' }}>
                    <option value="">—</option>
                    @foreach ($condicioncompra_query as $c)
                        <option value="{{ $c->id }}" {{ (int) old('condicioncompra_id', (isset($data) && $data) ? ($data->condicioncompra_id ?? 0) : 0) === (int) $c->id ? 'selected' : '' }}>
                            {{ $c->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="form-group row">
            <label for="moneda_id" class="col-lg-4 control-label text-right pr-2">Moneda</label>
            <div class="col-lg-8">
                <select name="moneda_id" id="moneda_id" class="form-control" {{ $visualizar ? 'disabled' : '' }}>
                    <option value="">—</option>
                    @foreach ($moneda_query as $m)
                        <option value="{{ $m->id }}" {{ (int) old('moneda_id', (isset($data) && $data) ? ($data->moneda_id ?? 0) : 0) === (int) $m->id ? 'selected' : '' }}>
                            {{ $m->nombre }} ({{ $m->abreviatura ?? '' }})
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>
</div>
