<table>
    @if (! empty($hayFilaLogos))
        <tr>
            <td colspan="{{ (int) ($colspan ?? 2) }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ (int) ($colspan ?? 2) }}"><strong>{{ $titulo ?? 'Lista de precios' }}</strong></td>
    </tr>
    <tr>
        <td colspan="{{ (int) ($colspan ?? 2) }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim((string) ($subtitulo ?? '')) !== '')
        <tr>
            <td colspan="{{ (int) ($colspan ?? 2) }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ (int) ($colspan ?? 2) }}">Registros: {{ (int) ($totalFilas ?? 0) }}</td>
    </tr>
    <thead>
        <tr>
            <th>Artículo</th>
            <th>Descripción</th>
            @foreach ($listas ?? [] as $lista)
                <th>{{ $lista['encabezado'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($filas ?? [] as $fila)
            <tr>
                <td>{{ $fila->sku }}</td>
                <td>{{ $fila->descripcion }}</td>
                @foreach ($listas ?? [] as $lista)
                    <td>{{ ($fila->precios[$lista['id']] ?? null) === null ? '' : number_format((float) $fila->precios[$lista['id']], 2, '.', '') }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
