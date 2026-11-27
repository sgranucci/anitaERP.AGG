{{--
  Detalle de líneas de OC con ahorro (pantalla y Excel).
  Variables: $filas, $pantalla (bool), $puede_ver_ordencompra (bool, solo pantalla)
--}}
@php
    $enPantalla = (bool) ($pantalla ?? false);
    $fmt = fn ($n, $d = 2) => number_format((float) $n, $d, ',', '.');
    $fechaCorta = fn ($f) => $f ? \Carbon\Carbon::parse($f)->format('d/m/Y') : '';
@endphp
<thead>
    <tr class="columnas" style="{{ $enPantalla ? 'background:#85C1E9;color:#17202A;' : '' }}">
        <th>Fecha OC</th>
        <th>Nro OC</th>
        <th>Empresa</th>
        <th>Requisici&oacute;n</th>
        <th>Comprador</th>
        <th>Proveedor</th>
        <th>SKU</th>
        <th>Art&iacute;culo</th>
        <th class="text-right">Cantidad</th>
        <th class="text-right">Precio anterior</th>
        <th>Mon. ant.</th>
        <th class="text-right">Precio final</th>
        <th class="text-right">Ahorro $</th>
        <th>Mon.</th>
        <th class="text-right">Ahorro %</th>
        <th>Tipo de ahorro</th>
        <th>Motivo</th>
    </tr>
</thead>
<tbody>
    @forelse ($filas as $f)
        <tr>
            <td class="text-nowrap">{{ $fechaCorta($f['fecha']) }}</td>
            <td class="text-nowrap">
                @if ($enPantalla && ($puede_ver_ordencompra ?? false))
                    <a class="text-primary" target="_blank" rel="noopener"
                       href="{{ route('editar_ordencompra', ['id' => $f['oc_id'], 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}">{{ $f['numero'] }}</a>
                @else
                    {{ $f['numero'] }}
                @endif
            </td>
            <td>{{ $f['empresa'] }}</td>
            <td class="text-nowrap">{{ $f['requisicion'] }}</td>
            <td>{{ $f['comprador'] }}</td>
            <td>{{ $f['proveedor'] }}</td>
            <td>{{ $f['sku'] }}</td>
            <td>{{ $f['articulo'] }}</td>
            <td class="text-right">{{ $enPantalla ? $fmt($f['cantidad']) : $f['cantidad'] }}</td>
            <td class="text-right">{{ $enPantalla ? $fmt($f['precio_anterior']) : $f['precio_anterior'] }}</td>
            <td>{{ $f['moneda_anterior'] }}</td>
            <td class="text-right">{{ $enPantalla ? $fmt($f['precio_final']) : $f['precio_final'] }}</td>
            <td class="text-right {{ $enPantalla && $f['ahorro_pesos'] < 0 ? 'text-danger' : '' }}">{{ $enPantalla ? $fmt($f['ahorro_pesos']) : round($f['ahorro_pesos'], 2) }}</td>
            <td>{{ $f['moneda'] }}</td>
            <td class="text-right">{{ $f['pct'] === null ? '' : ($enPantalla ? $fmt($f['pct'], 1).' %' : round($f['pct'], 2)) }}</td>
            <td>{{ $f['tipo'] }}</td>
            <td>{{ $f['motivo'] }}</td>
        </tr>
    @empty
        <tr>
            <td colspan="17" class="text-center text-muted">Sin l&iacute;neas con ahorro para los filtros elegidos.</td>
        </tr>
    @endforelse
</tbody>
