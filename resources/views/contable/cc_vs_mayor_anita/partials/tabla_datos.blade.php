@php
    $items = $filas instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
        ? $filas
        : collect($filas ?? []);
    $fmt = static fn ($n) => number_format((float) $n, 2, ',', '.');
    $badge = static function (string $e): string {
        return match ($e) {
            'DIFF' => 'badge-danger',
            'SOLO_CC', 'SOLO_MAYOR' => 'badge-warning',
            'MATCH_FLEX' => 'badge-info',
            default => 'badge-success',
        };
    };
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered table-hover mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Estado</th>
                <th>Clave</th>
                <th>Tipo</th>
                <th>Nro</th>
                <th>PV CC</th>
                <th>PV mayor</th>
                <th class="text-right">Neto CC</th>
                <th class="text-right">Neto mayor</th>
                <th class="text-right">Diff</th>
                <th class="text-right">Debe mayor</th>
                <th class="text-right">Haber mayor</th>
                <th>Lado / mov</th>
                <th>Aviso / detalle</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($items as $f)
                @php
                    $row = is_array($f) ? $f : (array) $f;
                    $estado = (string) ($row['estado'] ?? '');
                @endphp
                <tr>
                    <td><span class="badge {{ $badge($estado) }}">{{ $estado }}</span></td>
                    <td><code>{{ $row['clave'] ?? '' }}</code></td>
                    <td>{{ $row['tipo'] ?? '' }}</td>
                    <td>{{ $row['nro'] ?? '' }}</td>
                    <td>{{ $row['sucursal_cc'] ?? $row['sucursal'] ?? '' }}</td>
                    <td>{{ $row['sucursal_mayor'] ?? $row['sucursal'] ?? '' }}</td>
                    <td class="text-right">{{ $fmt($row['neto_cc'] ?? 0) }}</td>
                    <td class="text-right">{{ $fmt($row['neto_mayor'] ?? 0) }}</td>
                    <td class="text-right font-weight-bold">{{ $fmt($row['diff'] ?? 0) }}</td>
                    <td class="text-right">{{ $fmt($row['mayor_debe'] ?? 0) }}</td>
                    <td class="text-right">{{ $fmt($row['mayor_haber'] ?? 0) }}</td>
                    <td class="small">{{ trim(($row['lado'] ?? '').' '.($row['tipo_mov'] ?? '')) }}</td>
                    <td class="small">
                        @if (!empty($row['aviso']))
                            <strong>{{ $row['aviso'] }}</strong><br>
                        @endif
                        {{ $row['desc'] ?? '' }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" class="text-center text-muted">Sin diferencias / sin datos para el filtro.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
