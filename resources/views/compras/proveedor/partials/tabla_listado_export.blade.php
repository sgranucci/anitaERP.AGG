{{-- Tabla compartida PDF / Excel del listado de proveedores (columnas dinámicas). --}}
@php
    use App\Support\Compras\ProveedorListadoColumnas;
    $catalogo = ProveedorListadoColumnas::catalogoActivo();
    $columnas = $columnasVisibles ?? ProveedorListadoColumnas::defaultsVisibles();
    $columnas = array_values(array_filter(
        $columnas,
        static function ($k) use ($catalogo) {
            return isset($catalogo[$k]) && ! empty($catalogo[$k]['export']);
        }
    ));
    if ($columnas === []) {
        $columnas = array_values(array_filter(
            ProveedorListadoColumnas::defaultsVisibles(),
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
    @foreach ($proveedores as $data)
        <tr>
            @foreach ($columnas as $key)
                @php
                    $valor = match ($key) {
                        'id' => $data->id,
                        'codigo' => $data->codigo,
                        'nombre' => $data->nombre,
                        'fantasia' => $data->fantasia,
                        'numerodocumento' => $data->numerodocumento,
                        'domicilio' => $data->domicilio,
                        'localidad' => $data->nombrelocalidad ?? '',
                        'provincia' => $data->nombreprovincia ?? '',
                        'empresa' => ($data->nombreempresa ?: 'Todas'),
                        'estado' => $data->estado,
                        'cbu' => $data->cbu ?? '',
                        'alias_cbu' => $data->alias_cbu ?? '',
                        default => '',
                    };
                @endphp
                @if (in_array($key, ['cbu', 'numerodocumento', 'codigo'], true))
                    <td style="mso-number-format:'\@';"><small>{{ $valor }}</small></td>
                @else
                    <td><small>{{ $valor }}</small></td>
                @endif
            @endforeach
        </tr>
    @endforeach
</tbody>
