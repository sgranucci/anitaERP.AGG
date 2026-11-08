<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'a4 portrait',
            'pdf_margin' => '12mm 14mm',
        ])
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #17202A; }
        h1 { font-size: 14px; margin: 0; }
        h2 { font-size: 11px; margin: 10px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85C1E9; color: #17202A; padding: 3px 4px; border: 1px solid #ccc; text-align: left; }
        td { padding: 3px 4px; border: 1px solid #ccc; }
        .sin-borde td { border: none; padding: 1px 4px; }
        .right { text-align: right; }
        .logo { max-height: 64px; max-width: 200px; }
        .muted { color: #555; font-size: 8px; }
        .firma-box { height: 55px; border: 1px solid #ccc; margin-top: 4px; }
    </style>
</head>
<body>
@php
    use App\Support\Caja\TesoreriaCrownBovedaArqueoSupport;
    use App\Support\Caja\TesoreriaCrownNumeradorSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($arqueo->empresa->nombre ?? null);
    $numero = TesoreriaCrownNumeradorSupport::visible((int) $arqueo->numero);
@endphp
<table class="marco-pdf"><tr>
    <td class="marco-lat"></td>
    <td class="marco-centro">
        <table class="sin-borde">
            <tr>
                <td style="width:30%;">
                    @if (! empty($logo['uri']))
                        <img src="{{ $logo['uri'] }}" class="logo" alt="">
                    @endif
                </td>
                <td style="text-align:right;">
                    <h1>Arqueo de cambio de turno {{ $numero }}</h1>
                    <div class="muted">{{ $arqueo->empresa->nombre ?? '' }}</div>
                    <div class="muted">Generado {{ now()->format('d/m/Y H:i') }}</div>
                    <div class="muted">Estado: {{ TesoreriaCrownBovedaArqueoSupport::etiquetaEstado($arqueo->estado) }}</div>
                </td>
            </tr>
        </table>
        <table class="sin-borde" style="margin-top:8px;">
            <tr>
                <td>Sala: {{ $arqueo->sala->codigo ?? '' }} {{ $arqueo->sala->nombre ?? '' }}</td>
                <td>Jornada: {{ $arqueo->jornada->fecha->format('d/m/Y') }}</td>
                <td>Corte: {{ $arqueo->corte_at?->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td colspan="2">Lugar: {{ $arqueo->deposito->codigo ?? '' }} {{ $arqueo->deposito->nombre ?? '' }}</td>
                <td>
                    @if ($arqueo->observacion)
                        Obs.: {{ $arqueo->observacion }}
                    @endif
                </td>
            </tr>
        </table>

        @foreach ($grupos as $grupo)
            <h2>{{ $grupo['titulo'] }}</h2>
            <table>
                <thead>
                    <tr>
                        <th>Valor</th>
                        <th class="right">Inicial</th>
                        <th class="right">Ingresos</th>
                        <th class="right">Egresos</th>
                        <th class="right">Actual</th>
                        <th class="right">Monto actual</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($grupo['filas'] as $fila)
                        @php $den = (float) $fila['valor']->denominacion; @endphp
                        <tr>
                            <td>{{ number_format($den, $den == (int) $den ? 0 : 2, ',', '.') }} {{ $fila['valor']->tipoValor->codigo ?? '' }}</td>
                            <td class="right">{{ number_format($fila['inicial'], 0, ',', '.') }}</td>
                            <td class="right">{{ number_format($fila['ingresos'], 0, ',', '.') }}</td>
                            <td class="right">{{ number_format($fila['egresos'], 0, ',', '.') }}</td>
                            <td class="right">{{ number_format($fila['actual'], 0, ',', '.') }}</td>
                            <td class="right">{{ number_format($fila['monto_actual'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr>
                        <th>Total</th>
                        <th class="right">{{ number_format($grupo['totales']['inicial'] ?? 0, 2, ',', '.') }}</th>
                        <th class="right">{{ number_format($grupo['totales']['ingresos'] ?? 0, 2, ',', '.') }}</th>
                        <th class="right">{{ number_format($grupo['totales']['egresos'] ?? 0, 2, ',', '.') }}</th>
                        <th class="right">{{ number_format($grupo['totales']['actual'] ?? 0, 2, ',', '.') }}</th>
                        <th></th>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <table class="sin-borde" style="margin-top:24px;">
            <tr>
                <td style="width:48%; vertical-align:top;">
                    <strong>Entrega (sale de turno)</strong>
                    <div class="firma-box"></div>
                    <div class="muted" style="margin-top:4px;">
                        @if ($arqueo->entregaUsuario)
                            {{ $arqueo->entregaUsuario->nombre }} · {{ $arqueo->entrega_at?->format('d/m/Y H:i') }}
                        @else
                            Firma / aclaración
                        @endif
                    </div>
                </td>
                <td style="width:4%;"></td>
                <td style="width:48%; vertical-align:top;">
                    <strong>Recepción (entra de turno)</strong>
                    <div class="firma-box"></div>
                    <div class="muted" style="margin-top:4px;">
                        @if ($arqueo->recibeUsuario)
                            {{ $arqueo->recibeUsuario->nombre }} · {{ $arqueo->recibe_at?->format('d/m/Y H:i') }}
                        @else
                            Firma / aclaración
                        @endif
                    </div>
                </td>
            </tr>
        </table>
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
