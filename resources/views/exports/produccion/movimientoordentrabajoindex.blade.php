<table>
    <tr>
        <td colspan="{{ max(1, count($columnasVisibles ?? [])) }}"><strong>Movimientos de OT</strong></td>
    </tr>
    <tr>
        <td colspan="{{ max(1, count($columnasVisibles ?? [])) }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo ?? '') !== '')
        <tr>
            <td colspan="{{ max(1, count($columnasVisibles ?? [])) }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ max(1, count($columnasVisibles ?? [])) }}">Registros: {{ number_format($totalFilas ?? 0, 0, ',', '.') }}</td>
    </tr>
    <thead>
        <tr>
            @foreach ($columnasVisibles as $keyColumna)
                <th>{{ ($etiquetasColumnas ?? [])[$keyColumna] ?? $keyColumna }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($datas as $data)
            <tr>
                @foreach ($columnasVisibles as $keyColumna)
                    <td>{{ \App\Support\Produccion\MovimientoOrdentrabajoListadoColumnas::valorCelda($data, $keyColumna) }}</td>
                @endforeach
            </tr>
        @endforeach
    </tbody>
</table>
