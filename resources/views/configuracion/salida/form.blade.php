<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
    <input type="text" name="nombre" id="nombre" class="form-control" value="{{old('nombre', $data->nombre ?? '')}}" required/>
    </div>
</div>
<div class="form-group row">
    <label for="ubicacion_impresora_id" class="col-lg-3 col-form-label requerido">Ubicaci&oacute;n</label>
    <div class="col-lg-8">
        <select name="ubicacion_impresora_id" id="ubicacion_impresora_id" class="form-control" required>
            <option value="">Seleccione ubicaci&oacute;n…</option>
            @foreach ($ubicacion_impresora_query ?? [] as $ubicacion)
                <option value="{{ $ubicacion->id }}"
                    title="{{ $ubicacion->descripcion }}"
                    {{ (int) old('ubicacion_impresora_id', $data->ubicacion_impresora_id ?? 0) === (int) $ubicacion->id ? 'selected' : '' }}>
                    {{ $ubicacion->nombre }}
                </option>
            @endforeach
        </select>
    </div>
</div>
<div class="form-group row">
    <label for="comando" class="col-lg-3 col-form-label requerido">Comando de salida</label>
    <div class="col-lg-8">
    <input type="text" name="comando" id="comando" class="form-control" value="{{old('comando', $data->comando ?? '')}}" required/>
        <small class="form-text text-muted">
            Pedidos PDF (CUPS): <code>{{ config('pedido.imprimir_script', base_path('bin/imprimir-pedido.sh')) }} "%s" NOMBRE_COLA</code>.
            Etiquetas Zebra ZPL (CUPS): <code>{{ base_path('bin/imprimir-etiqueta-zebra.sh') }} "%s" NOMBRE_COLA</code>.
            Tickets térmicos: <code>{{ base_path('bin/gastronomia-print-ticket.sh') }} "%s" IP_IMPRESORA 9100</code>.
        </small>
    </div>
</div>
<div class="form-group row">
    <label for="uso_salida_impresora_ids" class="col-lg-3 col-form-label">Usos</label>
    <div class="col-lg-8">
        @php
            $selectedUsos = old(
                'uso_salida_impresora_ids',
                ($data->exists ?? false) ? $data->usoSalidaImpresoras->pluck('id')->all() : []
            );
        @endphp
        <select name="uso_salida_impresora_ids[]" id="uso_salida_impresora_ids" data-placeholder="Usos de la impresora" class="form-control" data-fouc multiple>
            @foreach ($uso_salida_impresora_query ?? [] as $uso)
                @if (in_array((int) $uso->id, array_map('intval', (array) $selectedUsos), true))
                    <option value="{{ $uso->id }}" selected="selected" title="{{ $uso->descripcion }}">{{ $uso->nombre }}</option>
                @else
                    <option value="{{ $uso->id }}" title="{{ $uso->descripcion }}">{{ $uso->nombre }}</option>
                @endif
            @endforeach
        </select>
        <small class="form-text text-muted">
            Sin usos seleccionados la impresora queda como <strong>uso genérico</strong> (disponible para cualquier destino).
        </small>
    </div>
</div>
