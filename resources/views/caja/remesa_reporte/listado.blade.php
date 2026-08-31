@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Remesas por cuenta de caja';
    $subtitulo = $subtitulo ?? '';
    $resultado = $resultado ?? [];
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data th, table.data td { border: 1px solid #cccccc; padding: 3px 4px; }
        table.data thead th { background: #85C1E9; color: #17202A; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .grupo td { background: #D6EAF8 !important; font-weight: bold; }
        .total td { background: #e8e8e8 !important; font-weight: bold; }
        .total-general td { background: #D5D8DC !important; font-weight: bold; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table style="width:100%; margin-bottom: 8px;">
        <tr>
            <td style="width:20%;">
                @foreach ($logos as $logo)
                    @if (!empty($logo['uri']))
                        <img src="{{ $logo['uri'] }}" style="max-height:40px;">
                    @endif
                @endforeach
            </td>
            <td style="text-align:center;">
                <h2 style="margin:0;font-size:16px;">{{ $titulo }}</h2>
                <div>Generado {{ date('d/m/Y H:i') }}</div>
                <div>{{ $subtitulo }}</div>
                <div>
                    {{ (int) ($resultado['total_movimientos'] ?? 0) }} movimientos
                    · Origen {{ number_format((float) ($resultado['total_importe_origen'] ?? 0), 2, ',', '.') }}
                    · Importe {{ number_format((float) ($resultado['total_importe'] ?? 0), 2, ',', '.') }}
                </div>
            </td>
            <td style="width:20%;"></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Remesa</th>
                <th>Mon</th>
                <th>Cotizaci&oacute;n</th>
                <th>Importe origen</th>
                <th>Importe</th>
                <th>Estado</th>
                <th>Empr.</th>
                <th>Origen</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                @php $tipoFila = $fila['tipo_fila'] ?? 'dato'; @endphp
                @if ($tipoFila === 'grupo')
                    <tr class="grupo">
                        <td colspan="9">Cuenta: {{ $fila['cuenta_etiqueta'] ?? '' }}</td>
                    </tr>
                @elseif ($tipoFila === 'total_cuenta' || $tipoFila === 'total_general')
                    <tr class="{{ $tipoFila === 'total_general' ? 'total-general' : 'total' }}">
                        <td colspan="4" class="text-right">{{ $fila['cuenta_etiqueta'] ?? 'Total' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['importe_origen'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                        <td colspan="3"></td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $fila['fecha'] ?? '' }}</td>
                        <td>{{ $fila['remesa_nro'] ?? '' }}</td>
                        <td>{{ $fila['moneda'] ?? '' }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['cotizacion'] ?? 0), 4, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['importe_origen'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                        <td>{{ $fila['estado'] ?? '' }}</td>
                        <td>{{ $fila['empresa_id'] ?? '' }}</td>
                        <td>{{ $fila['fuente'] ?? '' }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</body>
</html>
