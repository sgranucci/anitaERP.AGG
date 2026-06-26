<h2>Recuentos de inventario</h2>
<table>
    <thead>
        <tr>
            <th>ID</th>
            <th>Código</th>
            <th>Fecha</th>
            <th>Depósito</th>
            <th>Empresa</th>
            <th>Usuario</th>
            <th>Tipo</th>
            <th>Estado</th>
            <th>Líneas</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($recuentos as $r)
            <tr>
                <td>{{ $r->id }}</td>
                <td>{{ $r->codigo }}</td>
                <td>{{ optional($r->fecha)->format('d/m/Y') }}</td>
                <td>{{ optional($r->deposito)->etiqueta() }}</td>
                <td>{{ optional($r->empresa)->nombre }}</td>
                <td>{{ optional($r->usuario)->nombre }}</td>
                <td>{{ $r->tipo }}</td>
                <td>{{ \App\Models\Stock\Recuento::etiquetaEstado($r->estado) }}</td>
                <td>{{ $r->items_count ?? $r->items->count() }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
