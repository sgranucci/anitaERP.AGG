<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Env&iacute;o {{ $venta->codigo ?? $venta->id }}</title>
    <style type="text/css">
        @page { margin: 12mm 14mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #000;
            margin: 0;
            padding: 0;
        }
        /* DomPDF: borde en TABLE (no en div+tabla anidada); si no, el contenido queda fuera del recuadro. */
        table.etiqueta {
            width: 100%;
            border: 2.5pt solid #000;
            border-collapse: collapse;
            margin: 0 0 10mm 0;
            page-break-inside: avoid;
        }
        table.etiqueta-ultima { margin-bottom: 0; }
        table.etiqueta-salto { page-break-after: always; margin-bottom: 0; }
        td.remitente {
            height: 45mm;
            vertical-align: top;
            padding: 6px 10px 8px 10px;
            border-bottom: 1.5pt solid #000;
        }
        td.destinatario {
            height: 73mm;
            vertical-align: top;
            padding: 8px 10px 10px 10px;
        }
        .rotulo {
            font-size: 11px;
            font-weight: normal;
            line-height: 1.2;
        }
        .razon-social {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 16px 4px 8px 4px;
            line-height: 1.15;
        }
        .destinatario-nombre {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 0.5px;
            padding: 6px 4px 12px 4px;
            line-height: 1.15;
        }
        /* Empuja domicilio/TE hacia el borde inferior del bloque remitente (como el modelo). */
        table.linea-remitente {
            width: 100%;
            border-collapse: collapse;
            margin-top: 18mm;
        }
        table.linea-remitente td {
            font-size: 12px;
            vertical-align: bottom;
            padding: 0;
        }
        table.linea-remitente td.izq { text-align: left; width: 34%; }
        table.linea-remitente td.cen { text-align: center; width: 33%; }
        table.linea-remitente td.der { text-align: right; width: 33%; }
        .dest-domicilio {
            font-size: 13px;
            line-height: 1.35;
            padding: 0 2px;
        }
        .entrega-en {
            margin-top: 22mm;
            font-size: 12px;
            line-height: 1.3;
        }
        .entrega-en-valor {
            font-size: 14px;
            font-weight: bold;
            margin-top: 2px;
        }
    </style>
</head>
<body>
@php
    $rem = $etiqueta['remitente'] ?? [];
    $des = $etiqueta['destinatario'] ?? [];
    $telMostrar = trim((string) ($rem['telefono'] ?? ''));
    $cantidadEnvios = max(1, (int) ($cantidadEnvios ?? 1));
@endphp
@for ($i = 0; $i < $cantidadEnvios; $i++)
    @php
        $esUltima = $i === ($cantidadEnvios - 1);
        $cierraHoja = ($i % 2 === 1) || $esUltima;
        $saltoDespues = ($i % 2 === 1) && ! $esUltima;
        $clasesEtiqueta = 'etiqueta';
        if ($cierraHoja) {
            $clasesEtiqueta .= ' etiqueta-ultima';
        }
        if ($saltoDespues) {
            $clasesEtiqueta .= ' etiqueta-salto';
        }
    @endphp
    <table class="{{ $clasesEtiqueta }}">
        <tr>
            <td class="remitente">
                <div class="rotulo">ENVIO DE:</div>
                <div class="razon-social">{{ $rem['razon_social'] ?? '' }}</div>
                <table class="linea-remitente">
                    <tr>
                        <td class="izq">{{ $rem['domicilio'] ?? '' }}</td>
                        <td class="cen">{{ $rem['localidad'] ?? '' }}</td>
                        <td class="der">
                            @if ($telMostrar !== '')
                                T.E. {{ $telMostrar }}
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td class="destinatario">
                <div class="rotulo">Sres.:</div>
                <div class="destinatario-nombre">{{ $des['nombre'] ?? '' }}</div>
                <div class="dest-domicilio">
                    {{ $des['domicilio'] ?? '' }}<br>
                    {{ $des['localidad_cp'] ?? '' }}<br>
                    {{ $des['provincia'] ?? '' }}
                </div>
                @if (trim((string) ($des['entrega_en'] ?? '')) !== '')
                    <div class="entrega-en">
                        Entrega en:<br>
                        <div class="entrega-en-valor">{{ $des['entrega_en'] }}</div>
                    </div>
                @endif
            </td>
        </tr>
    </table>
@endfor
</body>
</html>
