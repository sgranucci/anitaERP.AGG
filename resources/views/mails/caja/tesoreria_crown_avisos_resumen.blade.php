<div style="font-family:Arial,sans-serif;font-size:13px;color:#17202A;">
    <p>Resumen de avisos de auditoría de tesorería Crown.</p>
    <p>
        Abiertos: <strong>{{ $resumen['total_abiertos'] }}</strong>
        @foreach ($resumen['abiertos'] as $severidad => $cantidad)
            · {{ \App\Support\Caja\TesoreriaCrownAvisoSupport::severidad($severidad) }}: {{ $cantidad }}
        @endforeach
    </p>
    @if ($resumen['nuevos'] === [])
        <p>No hay avisos nuevos desde el último resumen.</p>
    @else
        <p>Nuevos desde el último resumen:</p>
        <table cellpadding="4" cellspacing="0" style="border-collapse:collapse;font-size:12px;">
            <thead>
                <tr style="background:#85C1E9;color:#17202A;">
                    <th style="border:1px solid #cccccc;text-align:left;">Fecha</th>
                    <th style="border:1px solid #cccccc;text-align:left;">Severidad</th>
                    <th style="border:1px solid #cccccc;text-align:left;">Regla</th>
                    <th style="border:1px solid #cccccc;text-align:left;">Aviso</th>
                    <th style="border:1px solid #cccccc;text-align:right;">Importe</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($resumen['nuevos'] as $i => $aviso)
                    <tr style="{{ $i % 2 ? 'background:#f5f5f5;' : '' }}">
                        <td style="border:1px solid #cccccc;">{{ $aviso['fecha'] ? date('d/m/Y', strtotime($aviso['fecha'])) : '' }}</td>
                        <td style="border:1px solid #cccccc;">{{ \App\Support\Caja\TesoreriaCrownAvisoSupport::severidad($aviso['severidad']) }}</td>
                        <td style="border:1px solid #cccccc;">{{ \App\Support\Caja\TesoreriaCrownAvisoSupport::regla($aviso['regla']) }}</td>
                        <td style="border:1px solid #cccccc;">{{ $aviso['titulo'] }}</td>
                        <td style="border:1px solid #cccccc;text-align:right;">{{ $aviso['importe'] !== null ? number_format((float) $aviso['importe'], 2, ',', '.') : '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    <p><a href="{{ $resumen['url'] }}">Abrir la bandeja de avisos</a></p>
</div>
