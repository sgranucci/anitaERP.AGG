<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'legal landscape',
            'pdf_margin' => '10mm 10mm',
        ])
        body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #17202A; }
        h1 { font-size: 13px; margin: 0; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; padding: 2px 3px; border: 1px solid #ccc; }
        table.data td { padding: 2px 3px; border: 1px solid #ccc; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .right { text-align: right; }
        .muted { color: #555; font-size: 7px; }
        .logo { max-height: 48px; }
        .sin-borde td { border: none; padding: 1px 3px; }
    </style>
</head>
<body>
@php
    $logos = $logos ?? [];
@endphp
<table class="marco-pdf"><tr>
    <td class="marco-lat"></td>
    <td class="marco-centro">
        <table class="sin-borde" style="width:100%;">
            <tr>
                <td style="width:25%;">
                    @foreach ($logos as $logo)
                        @if (! empty($logo['uri']))
                            <img src="{{ $logo['uri'] }}" class="logo" alt="">
                        @endif
                    @endforeach
                </td>
                <td style="text-align:right;">
                    <h1>{{ $titulo }}</h1>
                    <div class="muted">Generado {{ now()->format('d/m/Y H:i') }}</div>
                    <div class="muted">{{ $subtitulo }}</div>
                    <div class="muted">{{ count($filas) }} registro(s)</div>
                </td>
            </tr>
        </table>
        <table class="data" style="margin-top:8px;">
            <thead>
                <tr>
                    <th>Fecha</th>
                    <th>Depósito</th>
                    <th>Abv</th>
                    <th>Número</th>
                    <th>Tipo</th>
                    <th>Subtipo</th>
                    <th>Valor</th>
                    <th class="right">Entrada</th>
                    <th class="right">Salida</th>
                    <th class="right">Monto</th>
                    <th>Comentario</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($filas as $fila)
                    <tr>
                        <td>{{ $fila->fecha instanceof \DateTimeInterface ? $fila->fecha->format('d/m/Y') : \Carbon\Carbon::parse($fila->fecha)->format('d/m/Y') }}</td>
                        <td>{{ $fila->deposito_codigo }} {{ $fila->deposito_tipo }}</td>
                        <td>{{ $fila->abreviatura }}</td>
                        <td>{{ $fila->numero_visible }}</td>
                        <td>{{ $fila->tipo_nombre }}</td>
                        <td>{{ $fila->subtipo_etiqueta }}</td>
                        <td>{{ number_format((float) $fila->denominacion, 0, ',', '.') }} {{ $fila->moneda }}</td>
                        <td class="right">{{ $fila->entrada !== null ? number_format($fila->entrada, 0, ',', '.') : '' }}</td>
                        <td class="right">{{ $fila->salida !== null ? number_format($fila->salida, 0, ',', '.') : '' }}</td>
                        <td class="right">{{ number_format((float) $fila->monto, 2, ',', '.') }}</td>
                        <td>{{ $fila->comentario }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
