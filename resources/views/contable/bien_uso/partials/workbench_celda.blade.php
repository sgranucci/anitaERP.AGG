@php
    use App\Support\Contable\BienUsoListadoColumnas;
    $decimals = ['valor_origen', 'valor_baja', 'monto_factura'];
    $esId = $key === 'id';
    $clase = in_array($key, $decimals, true) ? 'text-right' : '';
@endphp
<td class="{{ $clase }}">
    @if ($esId && ($puede_ver_bien_uso ?? false))
        <a href="{{ route('editar_bien_uso', ['id' => $data->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
           class="text-primary" target="_blank" rel="noopener">{{ $data->id }}</a>
    @else
        {{ BienUsoListadoColumnas::valorCelda($data, $key) }}
    @endif
</td>
