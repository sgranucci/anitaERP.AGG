@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filas = $filas ?? [];
    $columnas = $columnas ?? [];
    $titulo = $titulo ?? 'Pagos';
    $subtitulo = $subtitulo ?? '';
    $total = (float) ($total ?? 0);
    $paraExcel = ! empty($para_excel);
    $cantidad = count($filas);
    $anchoTotal = 0;
    foreach ($columnas as $columna) {
        $anchoTotal += (int) ($columna['ancho'] ?? 16);
    }
    $anchoTotal = $anchoTotal > 0 ? $anchoTotal : 1;
    $logosCabecera = $paraExcel ? [] : EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $formatearMonto = $formatearMonto ?? null;
    $textoMonto = function ($valor) use ($paraExcel, $formatearMonto): string {
        if ($paraExcel && is_callable($formatearMonto)) {
            return (string) $formatearMonto($valor);
        }

        return number_format((float) $valor, 2, ',', '.');
    };
@endphp
@if ($paraExcel)
<table>
    @if (!empty($reservarFilaLogoExcel))
        <tr>
            <td colspan="{{ count($columnas) }}"></td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ count($columnas) }}">{{ $titulo }}</td>
    </tr>
    <tr>
        <td colspan="{{ count($columnas) }}">Generado {{ date('d/m/Y H:i') }}</td>
    </tr>
    @if (trim($subtitulo) !== '')
        <tr>
            <td colspan="{{ count($columnas) }}">{{ $subtitulo }}</td>
        </tr>
    @endif
    <tr>
        <td colspan="{{ count($columnas) }}">{{ $cantidad }} pago(s) · Total {{ $textoMonto($total) }}</td>
    </tr>
    <thead>
        <tr>
            @foreach ($columnas as $columna)
                <th>{{ $columna['titulo'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($filas as $fila)
            <tr>
                @foreach ($columnas as $columna)
                    @php $clave = (string) $columna['clave']; @endphp
                    <td>
                        @if (($columna['tipo'] ?? '') === 'importe')
                            {{ $textoMonto($fila[$clave] ?? 0) }}
                        @else
                            {{ $fila[$clave] ?? '' }}
                        @endif
                    </td>
                @endforeach
            </tr>
        @endforeach
        <tr>
            @foreach ($columnas as $indice => $columna)
                <td>
                    @if ($indice === 0)
                        Total general
                    @elseif (($columna['tipo'] ?? '') === 'importe')
                        {{ $textoMonto($total) }}
                    @endif
                </td>
            @endforeach
        </tr>
    </tbody>
</table>
@else
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 9px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 3px 4px;
            vertical-align: top;
        }
        table.data tbody tr:nth-child(even) { background-color: #f7f9fb; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-size: 8px; font-weight: bold; color: #17202A; }
        .text-right { text-align: right; white-space: nowrap; }
        .mono { font-family: DejaVu Sans Mono, DejaVu Sans, monospace; font-size: 8px; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #1a5276; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
        .total td { background-color: #d6eaf8; font-weight: bold; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}"
                        style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 15px; font-weight: bold; color: #17202A;">{{ $titulo }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (trim($subtitulo) !== '')
                    <div class="meta">{{ $subtitulo }}</div>
                @endif
            </td>
            <td style="width: 26%; text-align: right; font-size: 9px;">
                Pagos: {{ number_format($cantidad, 0, ',', '.') }}<br>
                <strong>Total $ {{ $textoMonto($total) }}</strong>
            </td>
        </tr>
    </table>

    <table class="data">
        <colgroup>
            @foreach ($columnas as $columna)
                <col style="width: {{ round(((int) ($columna['ancho'] ?? 16) / $anchoTotal) * 100, 2) }}%;">
            @endforeach
        </colgroup>
        <thead>
            <tr>
                @foreach ($columnas as $columna)
                    <th class="{{ ($columna['tipo'] ?? '') === 'importe' ? 'text-right' : '' }}">{{ $columna['titulo'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                <tr>
                    @foreach ($columnas as $columna)
                        @php
                            $clave = (string) $columna['clave'];
                            $esImporte = ($columna['tipo'] ?? '') === 'importe';
                            $esMono = ! empty($columna['mono']);
                        @endphp
                        <td class="{{ $esImporte ? 'text-right' : '' }} {{ $esMono ? 'mono' : '' }}">
                            @if ($esImporte)
                                {{ $textoMonto($fila[$clave] ?? 0) }}
                            @else
                                {{ $fila[$clave] ?? '' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
            <tr class="total">
                @foreach ($columnas as $indice => $columna)
                    <td class="{{ ($columna['tipo'] ?? '') === 'importe' ? 'text-right' : '' }}">
                        @if ($indice === 0)
                            Total general
                        @elseif (($columna['tipo'] ?? '') === 'importe')
                            {{ $textoMonto($total) }}
                        @endif
                    </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</body>
</html>
@endif
