<table>
    @if (! empty($hayFilaLogos))
        <tr>
            <td colspan="5" style="height: 52px;"></td>
        </tr>
    @endif
    <tr>
        <td colspan="5"><strong style="font-size:16pt;">Control transferencias Crown</strong></td>
    </tr>
    <tr>
        <td colspan="5">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    <tr>
        <td colspan="5">{{ $subtitulo ?? '' }} · {{ (int) (($resultado['resumen']['total'] ?? 0)) }} incongruencias</td>
    </tr>
    <thead>
        <tr>
            <th>Severidad</th>
            <th>Tipo</th>
            <th>Referencia</th>
            <th>Detalle</th>
            <th>Importe</th>
        </tr>
    </thead>
    <tbody>
        @foreach (($resultado['filas'] ?? []) as $fila)
            <tr>
                <td>{{ ($fila['severidad'] ?? '') === 'error' ? 'Error' : 'Aviso' }}</td>
                <td>{{ $fila['tipo_label'] ?? $fila['tipo'] ?? '' }}</td>
                <td>{{ $fila['referencia'] ?? '' }}</td>
                <td>{{ $fila['detalle'] ?? '' }}</td>
                <td>{{ $fila['importe'] !== null ? number_format((float) $fila['importe'], 2, '.', '') : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
