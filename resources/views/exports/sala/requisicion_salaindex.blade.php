<table>
    @if(!empty($reservarFilaLogoExcel))
    <tr><td colspan="11"></td></tr>
    @endif
    <tr><td colspan="11"><strong>Listado de requisiciones de sala</strong></td></tr>
    <tr>
        <th>Número</th><th>Fecha</th><th>Empresa</th><th>Centro costo</th>
        <th>Depósito</th><th>Zona</th><th>Prioridad</th><th>Estado</th><th>Solicitante</th><th>Comentario</th><th>Artículos</th>
    </tr>
    @foreach ($filas as $f)
    <tr>
        <td>{{ $f->numerorequisicion }}</td>
        <td>{{ $f->fecha }}</td>
        <td>{{ $f->nombreempresa }}</td>
        <td>{{ $f->nombrecentrocosto }}</td>
        <td>{{ $f->nombredeposito }}</td>
        <td>{{ $f->nombrezona }}</td>
        <td>{{ $f->nombreprioridad }}</td>
        <td>{{ $f->estado }}</td>
        <td>{{ $f->nombreusuario }}</td>
        <td>{{ $f->comentario }}</td>
        <td>
            @foreach ($f->requisicion_sala_articulos as $item)
                {{ $item->articulos->sku ?? '' }}-{{ $item->articulos->descripcion ?? '' }} ({{ $item->cantidad }})&#10;
            @endforeach
        </td>
    </tr>
    @endforeach
</table>
