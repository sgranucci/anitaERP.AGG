<table class="table table-bordered mb-0">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th colspan="8">{{ $titulo ?? 'Transferencias pendientes' }}</th>
        </tr>
        <tr>
            <th>Código</th>
            <th>Fecha</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Tipo</th>
            <th>Ítems</th>
            <th>Remitente</th>
            <th>Destinatario</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $t)
            <tr>
                <td>{{ $t->codigo }}</td>
                <td>{{ $t->fecha?->format('Y-m-d') }}</td>
                <td>
                    @if ($t->bien_uso_origen_id)
                        {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($t->bienUsoOrigen) }}
                    @else
                        {{ optional($t->depositoOrigen)->nombre }}
                    @endif
                </td>
                <td>
                    @if ($t->bien_uso_destino_id)
                        {{ \App\Support\Stock\TransferenciaBienUsoSupport::etiquetaBien($t->bienUsoDestino) }}
                    @else
                        {{ optional($t->depositoDestino)->nombre }}
                    @endif
                </td>
                <td>{{ optional($t->tipotransaccion_stock)->nombre }}</td>
                <td>{{ $t->articulos->count() }}</td>
                <td>{{ optional($t->usuarioOrigen)->nombre }}</td>
                <td>{{ optional($t->usuarioDestino)->nombre }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
