<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 8px; color: #17202A; margin: 12px; }
        h1 { font-size: 14px; margin: 0 0 2px; }
        .meta { font-size: 8px; margin-bottom: 6px; color: #444; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background-color: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px 4px; text-align: left; }
        table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data tr:nth-child(even) td { background-color: #f5f5f5; }
        .right { text-align: right; }
        .logos img { height: 40px; margin-right: 10px; }
    </style>
</head>
<body>
    @if (!empty($logos))
        <div class="logos">
            @foreach ($logos as $logo)
                <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}">
            @endforeach
        </div>
    @endif
    <h1>Cumplimientos de requisici&oacute;n de compra</h1>
    <div class="meta">
        Generado {{ now()->format('d/m/Y H:i') }} &nbsp;|&nbsp; Registros: {{ $filas->count() }}
    </div>
    <table class="data">
        <thead>
            <tr>
                <th>N&ordm;</th>
                <th>Fecha</th>
                <th>Usuario</th>
                <th>Empresa</th>
                <th>Requisiciones</th>
                <th class="right">L&iacute;neas</th>
                <th>Estado</th>
                <th>Leyenda</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($filas as $row)
                @php
                    $reqNros = $row->articulos->pluck('requisicion.numerorequisicion')->filter()->unique()->implode(', ');
                @endphp
                <tr>
                    <td>{{ $row->numero }}</td>
                    <td>{{ optional($row->fecha)->format('d/m/Y H:i') }}</td>
                    <td>{{ $row->usuario?->nombre ?? '' }}</td>
                    <td>{{ $row->empresa?->nombre ?? '' }}</td>
                    <td>{{ $reqNros }}</td>
                    <td class="right">{{ $row->articulos_count ?? $row->articulos->count() }}</td>
                    <td>{{ $row->estado === \App\Models\Compras\CumplimientoRequisicionCompra::ESTADO_ACTIVO ? 'ACTIVO' : 'REVERTIDO' }}</td>
                    <td>{{ $row->leyenda }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="right">Sin registros.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
