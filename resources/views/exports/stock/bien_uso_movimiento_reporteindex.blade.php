<table class="table table-bordered mb-0">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th colspan="10">{{ $titulo ?? 'Movimientos por bien de uso' }}</th>
        </tr>
        <tr>
            <th>Fecha</th>
            <th>Bien</th>
            <th>Efecto</th>
            <th>SKU</th>
            <th>Artículo</th>
            <th>Cantidad</th>
            <th>Tipo</th>
            <th>Mov.</th>
            <th>Transfer.</th>
            <th>Concepto</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $row)
            @php $cantidad = (float) ($row->cantidad ?? 0); @endphp
            <tr>
                <td>{{ $row->fecha }}</td>
                <td>{{ $row->bien_hostname }}</td>
                <td>{{ \App\Support\Stock\BienUsoAsignacionSupport::etiquetaEfecto($cantidad) }}</td>
                <td>{{ $row->sku }}</td>
                <td>{{ $row->articulo_descripcion }}</td>
                <td>{{ number_format(abs($cantidad), 4, '.', '') }}</td>
                <td>{{ $row->tipo_transaccion }}</td>
                <td>{{ $row->movimiento_codigo }}</td>
                <td>{{ $row->transferencia_codigo }}</td>
                <td>{{ $row->concepto }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
