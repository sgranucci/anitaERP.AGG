<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Constancia COT #{{ $sesion->id ?? '' }}</title>
    <style type="text/css">
        @page { margin: 12mm; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #17202A;
        }
        .cot-pagina { page-break-inside: avoid; }
        .salto-pagina { page-break-before: always; }
        table.cot-header { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        table.cot-header td { border: none; vertical-align: top; padding: 0; }
        .cot-logo { width: 38%; }
        .cot-meta { width: 62%; text-align: right; }
        .cot-titulo {
            font-size: 15px;
            font-weight: bold;
            color: #1B4F72;
            margin: 0 0 4px 0;
        }
        .cot-numero {
            border: 2px solid #1B4F72;
            background: #D6EAF8;
            text-align: center;
            padding: 10px 8px;
            margin: 10px 0 12px 0;
        }
        .cot-numero .rotulo { font-size: 10px; letter-spacing: 1px; color: #1B4F72; }
        .cot-numero .valor { font-size: 22px; font-weight: bold; letter-spacing: 1px; margin-top: 4px; }
        table.cot-datos { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        table.cot-datos th,
        table.cot-datos td {
            border: 1px solid #cccccc;
            padding: 5px 6px;
            vertical-align: top;
            text-align: left;
        }
        table.cot-datos th {
            width: 28%;
            background: #85C1E9;
            color: #17202A;
            font-weight: bold;
        }
        .cot-pie {
            margin-top: 14px;
            font-size: 9px;
            color: #444;
            line-height: 1.4;
        }
    </style>
</head>
<body>
@foreach ($paginas as $pagina)
    <div class="cot-pagina {{ $loop->first ? '' : 'salto-pagina' }}">
        <table class="cot-header">
            <tr>
                <td class="cot-logo">
                    @if (is_array($logo) && ! empty($logo['uri']))
                        <img src="{{ $logo['uri'] }}" style="max-width:170px;max-height:64px;" alt="">
                    @endif
                </td>
                <td class="cot-meta">
                    <p class="cot-titulo">Constancia COT electr&oacute;nico ARBA</p>
                    Sesi&oacute;n #{{ $sesion->id }}
                    @if ($sesion->numero_comprobante_arba)
                        — Comprob. ARBA {{ $sesion->numero_comprobante_arba }}
                    @endif
                    <br>
                    Ambiente {{ strtoupper((string) $sesion->ambiente) }}
                    @if ($sesion->cuit_empresa)
                        — CUIT {{ $sesion->cuit_empresa }}
                    @endif
                </td>
            </tr>
        </table>

        <div class="cot-numero">
            <div class="rotulo">C&Oacute;DIGO COT</div>
            <div class="valor">{{ $pagina['cot'] !== '' ? $pagina['cot'] : '—' }}</div>
        </div>

        <table class="cot-datos">
            <tr>
                <th>N&deg; &uacute;nico ARBA</th>
                <td>{{ $pagina['nro_unico'] !== '' ? $pagina['nro_unico'] : '—' }}</td>
            </tr>
            <tr>
                <th>Remito</th>
                <td>{{ $pagina['remito'] }}</td>
            </tr>
            <tr>
                <th>Fecha remito</th>
                <td>{{ $pagina['fecha_remito'] !== '' ? $pagina['fecha_remito'] : '—' }}</td>
            </tr>
            <tr>
                <th>Fecha emisi&oacute;n COT</th>
                <td>{{ $pagina['fecha_envio'] !== '' ? $pagina['fecha_envio'] : '—' }}</td>
            </tr>
            <tr>
                <th>Destinatario</th>
                <td>
                    {{ $pagina['cliente_nombre'] !== '' ? $pagina['cliente_nombre'] : '—' }}
                    @if ($pagina['cuit_destinatario'] !== '')
                        <br>CUIT {{ $pagina['cuit_destinatario'] }}
                    @endif
                    @if ($pagina['domicilio_destinatario'] !== '')
                        <br>{{ $pagina['domicilio_destinatario'] }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Origen</th>
                <td>
                    {{ $origen['razon_social'] ?? '—' }}
                    @if (! empty($origen['cuit']))
                        <br>CUIT {{ $origen['cuit'] }}
                    @endif
                    @if (! empty($origen['domicilio']))
                        <br>{{ $origen['domicilio'] }}
                    @endif
                </td>
            </tr>
            <tr>
                <th>Reparto / dominio</th>
                <td>
                    {{ $pagina['reparto'] !== '' ? $pagina['reparto'] : '—' }}
                    @if ($pagina['patente'] !== '')
                        <br>Dominio {{ $pagina['patente'] }}
                    @endif
                    @if ($pagina['cuit_chofer'] !== '')
                        <br>CUIT chofer {{ $pagina['cuit_chofer'] }}
                    @endif
                </td>
            </tr>
        </table>

        <p class="cot-pie">
            Constancia operativa del COT emitido ante ARBA. Debe acompa&ntilde;ar el remito
            durante el traslado. Conservar junto al comprobante de presentaci&oacute;n.
        </p>
    </div>
@endforeach
</body>
</html>
