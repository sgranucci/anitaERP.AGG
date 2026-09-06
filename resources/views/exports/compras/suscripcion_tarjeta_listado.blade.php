@php
    $usos = $usos ?? collect();
@endphp
<table>
    <thead>
        @if (! empty($reservarFilaLogoExcel))
            <tr>
                <th colspan="10"></th>
            </tr>
        @endif
        <tr>
            <th colspan="10">Tarjetas corporativas — {{ now()->format('d/m/Y H:i') }}</th>
        </tr>
        <tr>
            <th>Etiqueta</th>
            <th>Últimos 4</th>
            <th>Emisor</th>
            <th>Empresa</th>
            <th>Área / CC</th>
            <th>Responsable</th>
            <th>Imputación</th>
            <th>Suscripciones</th>
            <th>Estado</th>
            <th>Observación</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($tarjetas as $t)
            <tr>
                <td>{{ $t->etiqueta }}</td>
                <td>••{{ $t->ult4 }}</td>
                <td>{{ $t->emisor ?: '' }}</td>
                <td>{{ optional($t->empresas)->nombre }}</td>
                <td>
                    {{ $t->area ?: '' }}
                    @if ($t->centrocostos)
                        {{ trim(($t->centrocostos->codigo ?? '').' '.($t->centrocostos->nombre ?? '')) }}
                    @endif
                </td>
                <td>{{ optional($t->responsables)->nombre ?: '' }}</td>
                <td>{{ $t->imputable() ? 'Lista' : 'Incompleta' }}</td>
                <td>{{ $usos[$t->id] ?? 0 }}</td>
                <td>{{ $t->activo ? 'Activa' : 'Inactiva' }}</td>
                <td>{{ $t->observacion ?: '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
