@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $filas = $filas ?? [];
    $titulo = $titulo ?? 'Movimientos de caja';
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
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'legal landscape',
            'pdf_margin' => '14mm 16mm',
        ])
        body { font-family: DejaVu Sans, sans-serif; font-size: 8px; color: #222; }
        table.data { border-collapse: collapse; width: 100%; table-layout: fixed; }
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
<table class="marco-pdf"><tr>
    <td class="marco-lat"></td>
    <td class="marco-centro">
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
                    {{ (int) ($resultado['total_registros'] ?? 0) }} registros
                    · Ingresos {{ number_format((float) ($resultado['total_ingreso'] ?? 0), 2, ',', '.') }}
                    · Egresos {{ number_format((float) ($resultado['total_egreso'] ?? 0), 2, ',', '.') }}
                </div>
            </td>
            <td style="width:20%;"></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Nro.</th>
                <th>Tip</th>
                <th>Código</th>
                <th>Cliente / Proveedor</th>
                <th>Concepto</th>
                <th>Detalle</th>
                <th>Estado</th>
                <th>Ingreso</th>
                <th>Egreso</th>
                <th>Empresa</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $fila)
                @php $tipoFila = $fila['tipo_fila'] ?? 'dato'; @endphp
                @if ($tipoFila === 'grupo')
                    <tr class="grupo">
                        <td colspan="11">Cuenta: {{ $fila['cuenta_etiqueta'] ?? '' }}</td>
                    </tr>
                @elseif ($tipoFila === 'total_cuenta' || $tipoFila === 'total_general')
                    <tr class="{{ $tipoFila === 'total_general' ? 'total-general' : 'total' }}">
                        <td colspan="8" class="text-right">{{ $fila['cuenta_etiqueta'] ?? ($fila['clipro_nombre'] ?? 'Total') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['ingreso'] ?? 0), 2, ',', '.') }}</td>
                        <td class="text-right">{{ number_format((float) ($fila['egreso'] ?? 0), 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                @else
                    <tr>
                        <td>{{ $fila['fecha'] ?? '' }}</td>
                        <td>{{ $fila['numero'] ?? '' }}</td>
                        <td>{{ $fila['tipo_abrev'] ?? '' }}</td>
                        <td>{{ $fila['clipro_codigo'] ?? '' }}</td>
                        <td>{{ $fila['clipro_nombre'] ?? '' }}</td>
                        <td>{{ $fila['concepto'] ?? '' }}</td>
                        <td>{{ $fila['detalle'] ?? '' }}</td>
                        <td>{{ $fila['estado_nombre'] ?? '' }}</td>
                        <td class="text-right">{{ (float) ($fila['ingreso'] ?? 0) > 0 ? number_format((float) $fila['ingreso'], 2, ',', '.') : '' }}</td>
                        <td class="text-right">{{ (float) ($fila['egreso'] ?? 0) > 0 ? number_format((float) $fila['egreso'], 2, ',', '.') : '' }}</td>
                        <td>{{ $fila['nombreempresa'] ?? '' }}</td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
