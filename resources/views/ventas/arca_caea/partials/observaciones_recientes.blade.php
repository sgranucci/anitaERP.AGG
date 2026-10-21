@if (($observacionesCaea ?? collect())->isNotEmpty())
    <details class="mb-3">
        <summary class="small text-warning" style="cursor:pointer;">
            {{ $tituloObservaciones ?? 'Observaciones ARCA de los últimos 3 meses' }}
            ({{ $observacionesCaea->count() }})
        </summary>
        <div class="table-responsive mt-2">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Informado</th>
                        <th>Comprobante</th>
                        <th>Fecha</th>
                        <th>Texto</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($observacionesCaea as $obs)
                        <tr>
                            <td class="text-nowrap small">{{ $obs->informado_at?->format('d/m/Y H:i') }}</td>
                            <td class="text-nowrap small">{{ $obs->etiqueta() }}</td>
                            <td class="text-nowrap small">{{ $obs->fecha_comprobante?->format('d/m/Y') ?? '—' }}</td>
                            <td class="small" style="white-space:pre-wrap;">{{ $obs->texto }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif
