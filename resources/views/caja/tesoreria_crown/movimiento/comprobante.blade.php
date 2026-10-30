<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @include('includes.reportes.estilos_pdf_pagina', [
            'pdf_size' => 'a4 portrait',
            'pdf_margin' => '12mm 14mm',
        ])
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #17202A; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 12px; margin: 12px 0 4px; }
        table { width: 100%; border-collapse: collapse; }
        th { background: #85C1E9; color: #17202A; padding: 3px 4px; border: 1px solid #ccc; text-align: left; }
        td { padding: 3px 4px; border: 1px solid #ccc; }
        .sin-borde td { border: none; padding: 1px 4px; }
        .right { text-align: right; }
        .logo { max-height: 46px; max-width: 150px; }
        .firma td { border: none; text-align: center; }
        .linea { border-top: 1px solid #333; width: 78%; margin: 28px auto 4px; }
        .muted { color: #555; font-size: 9px; }
    </style>
</head>
<body>
@php
    use App\Support\Caja\TesoreriaCrownComprobanteSupport;
    use App\Support\Caja\TesoreriaCrownFirmaSupport;
    use App\Support\Caja\TesoreriaCrownNumeradorSupport;
    use App\Support\Caja\TesoreriaCrownRendicionCampos;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logo = EmpresaLogoArchivo::dataUriDesdeNombre($comprobante->empresa->nombre ?? null);
    $numero = TesoreriaCrownNumeradorSupport::visible($comprobante->numero);
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
                    <h1>{{ TesoreriaCrownComprobanteSupport::tipo($comprobante->tipo) }} {{ $numero }}</h1>
                    <div class="muted">{{ $comprobante->empresa->nombre ?? '' }}</div>
                    <div class="muted">Generado {{ now()->format('d/m/Y H:i') }}</div>
                </td>
            </tr>
        </table>
        <table class="sin-borde" style="margin-top:8px;">
            <tr>
                <td>Sala: {{ $comprobante->sala->codigo ?? '' }} {{ $comprobante->sala->nombre ?? '' }}</td>
                <td>Fecha: {{ $comprobante->jornada->fecha->format('d/m/Y') }}</td>
                <td>Estado: {{ TesoreriaCrownComprobanteSupport::estado($comprobante->estado) }}</td>
            </tr>
            <tr>
                <td>Caja: {{ $comprobante->asignacion->deposito->codigo ?? '' }} {{ $comprobante->asignacion->deposito->nombre ?? '' }}</td>
                <td colspan="2">Cajero: {{ $comprobante->asignacion->cajero->nombre ?? '' }}</td>
            </tr>
        </table>
        @foreach ($grupos as $grupo)
            @php
                $filas = $grupo['filas']->filter(fn ($valor) => (float) ($cantidades[$valor->id] ?? 0) != 0.0);
                $total = 0.0;
            @endphp
            @if ($filas->isNotEmpty())
                <h2>{{ $grupo['titulo'] }}</h2>
                <table>
                    <thead>
                        <tr>
                            <th>Denominación</th>
                            <th>Descripción</th>
                            <th class="right">Cantidad</th>
                            <th class="right">Importe</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filas as $valor)
                            @php
                                $cantidad = (float) $cantidades[$valor->id];
                                $importe = $cantidad * (float) $valor->denominacion;
                                $total += $importe;
                            @endphp
                            <tr>
                                <td class="right">{{ number_format((float) $valor->denominacion, 2, ',', '.') }}</td>
                                <td>{{ $valor->descripcion }}</td>
                                <td class="right">{{ number_format($cantidad, 0, ',', '.') }}</td>
                                <td class="right">{{ number_format($importe, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <th colspan="3">Total {{ $grupo['titulo'] }}</th>
                            <th class="right">{{ number_format($total, 2, ',', '.') }}</th>
                        </tr>
                    </tbody>
                </table>
            @endif
        @endforeach
        @if ($comprobante->tipo === TesoreriaCrownComprobanteSupport::RENDICION)
            @foreach (TesoreriaCrownRendicionCampos::gruposParaTarea($comprobante->asignacion->tipo_tarea ?? '') as $grupoMedio)
                <h2>{{ $grupoMedio['titulo'] }}</h2>
                <table>
                    <tbody>
                        @foreach ($grupoMedio['campos'] as $clave => $meta)
                            @php $valorCampo = $comprobante->{$clave}; @endphp
                            <tr>
                                <td>{{ $meta['label'] }}</td>
                                <td class="right">
                                    @if ($meta['tipo'] === 'importe')
                                        {{ number_format((float) $valorCampo, 2, ',', '.') }}
                                    @else
                                        {{ $valorCampo !== null && $valorCampo !== '' ? $valorCampo : '—' }}
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endforeach
        @endif
        <table class="firma" style="margin-top:18px;">
            <tr>
                <td style="width:50%;">
                    <div class="linea"></div>
                    {{ TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, 'entrega') }}<br>
                    <span class="muted">{{ TesoreriaCrownFirmaSupport::textoFirma($comprobante->entregaUsuario, $comprobante->entrega_at, TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo) === 'entrega' ? ($comprobante->asignacion->cajero->nombre ?? null) : null) }}</span>
                </td>
                <td style="width:50%;">
                    <div class="linea"></div>
                    {{ TesoreriaCrownFirmaSupport::etiqueta($comprobante->tipo, 'recibe') }}<br>
                    <span class="muted">{{ TesoreriaCrownFirmaSupport::textoFirma($comprobante->recibeUsuario, $comprobante->recibe_at, TesoreriaCrownFirmaSupport::ladoCajero($comprobante->tipo) === 'recibe' ? ($comprobante->asignacion->cajero->nombre ?? null) : null) }}</span>
                </td>
            </tr>
        </table>
    </td>
    <td class="marco-lat"></td>
</tr></table>
</body>
</html>
