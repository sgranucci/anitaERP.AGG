<table>
    @if (! empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ max(1, count($columnas)) }}" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ max(1, count($columnas)) }}">
            <strong style="font-size:16pt;">{{ $titulo }}</strong>
        </td>
    </tr>
    <tr>
        <td colspan="{{ max(1, count($columnas)) }}">
            Generado {{ date('d/m/Y H:i') }} · {{ $totalFilas }} registro(s)
        </td>
    </tr>
    <thead>
        <tr>
            @foreach ($columnas as $key)
                <th>{{ $etiquetas[$key] ?? $key }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($matriz as $linea)
            <tr>
                @foreach ($columnas as $key)
                    <td>{{ $linea[$key] ?? '' }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
