<table class="table table-striped table-bordered table-hover mb-0" id="tabla-paginada">
    <thead style="background:#85C1E9;color:#17202A;">
        <tr>
            <th>C&oacute;digo</th>
            <th>Fecha</th>
            <th>Estado</th>
            <th>Origen</th>
            <th>Destino</th>
            <th>Tipo</th>
            <th>&Iacute;tems</th>
            <th>Remitente</th>
            <th>Destinatario</th>
            <th>Aprobaci&oacute;n</th>
            @if ($puede_aprobar ?? false)
                <th class="text-nowrap">Acciones</th>
            @endif
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $t)
            <tr>
                <td><strong>{{ $t->codigo }}</strong></td>
                <td>{{ $t->fecha?->format('d/m/Y') }}</td>
                <td>
                    <span class="badge badge-warning">{{ \App\Support\Stock\TransferenciaMercaderiaEstados::etiqueta($t->estado) }}</span>
                </td>
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
                <td>{{ optional($t->usuarioOrigen)->nombre ?? '—' }}</td>
                <td>{{ optional($t->usuarioDestino)->nombre ?? '—' }}</td>
                <td>{{ $t->requiere_aprobacion ? 'Sí' : 'No' }}</td>
                @if ($puede_aprobar ?? false)
                    <td class="text-nowrap">
                        <a href="{{ route('transferencia_mercaderia_pendientes') }}" class="btn btn-warning btn-sm">Gestionar</a>
                    </td>
                @endif
            </tr>
        @empty
            <tr>
                <td colspan="{{ ($puede_aprobar ?? false) ? 11 : 10 }}" class="text-center text-muted">
                    No hay transferencias en stand-by para los filtros indicados.
                </td>
            </tr>
        @endforelse
    </tbody>
</table>
