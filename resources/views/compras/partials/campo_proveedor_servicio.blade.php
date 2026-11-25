@php
    use App\Support\Compras\ComprobanteProveedorServicioSupport;

    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-8';
    $soloLectura = ! empty($solo_lectura);
    $proveedorIdCampo = (int) old('proveedor_id', $proveedor_id ?? ($data->proveedor_id ?? 0));
    $servicioSelId = (int) old('proveedor_servicio_id', $proveedor_servicio_id ?? ($data->proveedor_servicio_id ?? 0));
    $servicioClienteVal = trim((string) old('servicio_cliente', $servicio_cliente ?? ($data->servicio_cliente ?? '')));
    $serviciosQuery = $servicios_query
        ?? ComprobanteProveedorServicioSupport::listarPorProveedor($proveedorIdCampo > 0 ? $proveedorIdCampo : null);
    $tieneServicios = $serviciosQuery->isNotEmpty() || $servicioClienteVal !== '';
    $requeridoServicio = $serviciosQuery->isNotEmpty() && empty($opcional);
    if ($servicioSelId <= 0 && $servicioClienteVal !== '') {
        foreach ($serviciosQuery as $optMatch) {
            if (trim((string) $optMatch->cliente) === $servicioClienteVal) {
                $servicioSelId = (int) $optMatch->id;
                break;
            }
        }
    }
@endphp
<div class="form-group row align-items-center{{ $tieneServicios ? '' : ' d-none' }}"
     id="div-proveedor-servicio"
     data-requerido-si-hay="{{ $requeridoServicio ? '1' : '0' }}">
    <label for="proveedor_servicio_id" class="{{ $colLabel }} col-form-label{{ $requeridoServicio ? ' requerido' : '' }}">
        Cliente / medidor
    </label>
    <div class="{{ $colInput }}">
        <input type="hidden" name="servicio_cliente" id="servicio_cliente" value="{{ $servicioClienteVal }}">
        <select name="proveedor_servicio_id" id="proveedor_servicio_id"
                class="form-control"
                @if ($soloLectura) disabled @endif
                @if ($requeridoServicio && ! $soloLectura) required @endif>
            <option value="">— Seleccionar —</option>
            @foreach ($serviciosQuery as $servicioOpt)
                <option value="{{ $servicioOpt->id }}"
                    data-cliente="{{ $servicioOpt->cliente }}"
                    data-detalle="{{ $servicioOpt->detalle ?? '' }}"
                    data-leyenda="{{ ComprobanteProveedorServicioSupport::leyendaSugerida($servicioOpt) }}"
                    @selected($servicioSelId === (int) $servicioOpt->id)>
                    {{ ComprobanteProveedorServicioSupport::etiqueta($servicioOpt) }}
                </option>
            @endforeach
            @if ($servicioClienteVal !== '' && $servicioSelId <= 0)
                <option value="" data-cliente="{{ $servicioClienteVal }}" selected>
                    {{ $servicioClienteVal }} (sin ficha actual)
                </option>
            @endif
        </select>
        @if ($soloLectura)
            <input type="hidden" name="proveedor_servicio_id" value="{{ $servicioSelId > 0 ? $servicioSelId : '' }}">
        @endif
        <small class="form-text text-muted mb-0">
            N&uacute;mero de cliente del proveedor (p. ej. Edesur). Se carga en la ficha del proveedor, solapa Servicios.
        </small>
    </div>
</div>
