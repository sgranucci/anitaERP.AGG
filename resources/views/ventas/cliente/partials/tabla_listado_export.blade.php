{{-- Tabla compartida PDF / Excel del listado de clientes (columnas dinámicas). --}}
@php
    use App\Support\Ventas\ClienteListadoColumnas;
    $catalogo = ClienteListadoColumnas::catalogoActivo();
    $columnas = $columnasVisibles ?? ClienteListadoColumnas::defaultsVisibles();
    $columnas = array_values(array_filter(
        $columnas,
        static function ($k) use ($catalogo) {
            return isset($catalogo[$k]) && ! empty($catalogo[$k]['export']);
        }
    ));
    if ($columnas === []) {
        $columnas = array_values(array_filter(
            ClienteListadoColumnas::defaultsVisibles(),
            static fn ($k) => isset($catalogo[$k]) && ! empty($catalogo[$k]['export'])
        ));
    }
    $etiquetas = $etiquetasColumnas ?? [];
@endphp
<thead>
    <tr>
        @foreach ($columnas as $key)
            <th>{{ $etiquetas[$key] ?? ($catalogo[$key]['label'] ?? $key) }}</th>
        @endforeach
    </tr>
</thead>
<tbody>
    @foreach ($clientes as $data)
        <tr>
            @foreach ($columnas as $key)
                @php
                    $valor = \App\Support\Ventas\ClienteListadoColumnas::valorCelda($data, $key);
                @endphp
                @if (in_array($key, ['numerodocumento', 'codigo', 'estado'], true))
                    <td style="mso-number-format:'\@';"><small>{{ $valor }}</small></td>
                @else
                    <td><small>{{ $valor }}</small></td>
                @endif
            @endforeach
        </tr>
    @endforeach
</tbody>
