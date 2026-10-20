<table>
    <tr>
        <td colspan="14"><strong>{{ $titulo ?? 'Control de inventario de items' }}</strong></td>
    </tr>
    <tr>
        <td colspan="14">{{ $subtitulo ?? '' }}</td>
    </tr>
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Categoría</th>
            <th>Subcategoría</th>
            <th>Uso</th>
            <th>Tipo</th>
            <th>Exist. inicial</th>
            <th>Recepciones</th>
            <th>Devoluciones</th>
            <th>Ajustes</th>
            <th>Consumos</th>
            <th>Otros</th>
            <th>Exist. final</th>
            <th>Dif.</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                <td>{{ $fila['sku'] ?? '' }}</td>
                <td>{{ $fila['descripcion'] ?? '' }}</td>
                <td>{{ $fila['categoria'] ?? '' }}</td>
                <td>{{ $fila['subcategoria'] ?? '' }}</td>
                <td>{{ $fila['uso'] ?? '' }}</td>
                <td>{{ $fila['tipo'] ?? '' }}</td>
                <td>{{ (float) ($fila['existencia_inicial'] ?? 0) }}</td>
                <td>{{ (float) ($fila['recepciones'] ?? 0) }}</td>
                <td>{{ (float) ($fila['devoluciones'] ?? 0) }}</td>
                <td>{{ (float) ($fila['ajustes'] ?? 0) }}</td>
                <td>{{ (float) ($fila['consumos'] ?? 0) }}</td>
                <td>{{ (float) ($fila['otros'] ?? 0) }}</td>
                <td>{{ (float) ($fila['existencia_final'] ?? 0) }}</td>
                <td>{{ empty($fila['cierra']) ? (float) ($fila['diferencia'] ?? 0) : '' }}</td>
            </tr>
        @endforeach
    </tbody>
    @if (! empty($totales))
        <tfoot>
            <tr>
                <td colspan="6">Totales ({{ $totales['total_articulos'] ?? 0 }})</td>
                <td>{{ (float) ($totales['existencia_inicial'] ?? 0) }}</td>
                <td>{{ (float) ($totales['recepciones'] ?? 0) }}</td>
                <td>{{ (float) ($totales['devoluciones'] ?? 0) }}</td>
                <td>{{ (float) ($totales['ajustes'] ?? 0) }}</td>
                <td>{{ (float) ($totales['consumos'] ?? 0) }}</td>
                <td>{{ (float) ($totales['otros'] ?? 0) }}</td>
                <td>{{ (float) ($totales['existencia_final'] ?? 0) }}</td>
                <td>{{ (float) ($totales['diferencia'] ?? 0) }}</td>
            </tr>
        </tfoot>
    @endif
</table>
@foreach ($avisos ?? [] as $aviso)
    <p>{{ $aviso }}</p>
@endforeach
