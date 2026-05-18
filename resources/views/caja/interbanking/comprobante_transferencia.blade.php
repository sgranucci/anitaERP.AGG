@php
    $credito = $datos['credito'] ?? [];
    $debito = $datos['debito'] ?? [];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $datos['titulo'] ?? 'Comprobante de transferencia' }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10px;
            color: #1a1a1a;
            margin: 28px 36px;
        }
        h1 {
            text-align: center;
            font-size: 15px;
            font-weight: bold;
            margin: 0 0 22px 0;
            letter-spacing: 0.2px;
        }
        .seccion-titulo {
            font-weight: bold;
            font-size: 10px;
            margin: 14px 0 6px 0;
            text-decoration: underline;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 4px;
        }
        table.datos td {
            padding: 2px 0;
            vertical-align: top;
            border: none;
        }
        .lbl {
            width: 28%;
            font-weight: normal;
        }
        .val {
            width: 72%;
        }
        .bloque-central {
            margin: 16px 0;
            border-top: 1px solid #ccc;
            border-bottom: 1px solid #ccc;
            padding: 10px 0;
        }
        .importe {
            font-size: 12px;
            font-weight: bold;
            margin-top: 6px;
        }
        .codigo-validacion {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 8px;
            word-break: break-all;
            margin-top: 10px;
            line-height: 1.35;
        }
        .seguridad {
            font-weight: bold;
            margin-top: 12px;
            font-size: 9px;
        }
        .legal {
            margin-top: 14px;
            font-size: 7.5px;
            color: #333;
            text-align: justify;
            line-height: 1.45;
        }
    </style>
</head>
<body>
    <h1>{{ $datos['titulo'] }}</h1>

    <div class="seccion-titulo">Datos de la cuenta crédito</div>
    <table class="datos">
        <tr>
            <td class="lbl">Banco:</td>
            <td class="val">{{ $credito['banco'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Denominación:</td>
            <td class="val">{{ $credito['denominacion'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cuit:</td>
            <td class="val">{{ $credito['cuit'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">CBU:</td>
            <td class="val">{{ $credito['cbu'] ?? '—' }}</td>
        </tr>
    </table>

    <div class="bloque-central">
        <table class="datos">
            <tr>
                <td class="lbl">Fecha:</td>
                <td class="val">{{ $datos['fecha'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Tipo de transferencia:</td>
                <td class="val">{{ $datos['tipo_transferencia'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Concepto:</td>
                <td class="val">{{ $datos['concepto'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="lbl">Motivo:</td>
                <td class="val">{{ $datos['motivo'] ?? '' }}</td>
            </tr>
            <tr>
                <td class="lbl">Nro. de transferencia:</td>
                <td class="val">{{ $datos['nro_transferencia'] ?? '—' }}</td>
            </tr>
            <tr>
                <td class="lbl">Nro. de red:</td>
                <td class="val">{{ $datos['nro_red'] ?? '—' }}</td>
            </tr>
        </table>
        <div class="importe">Importe: {{ $datos['importe'] ?? '—' }}</div>
    </div>

    <div class="seccion-titulo">Datos de la cuenta débito</div>
    <table class="datos">
        <tr>
            <td class="lbl">Banco:</td>
            <td class="val">{{ $debito['banco'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Denominación:</td>
            <td class="val">{{ $debito['denominacion'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Cuit:</td>
            <td class="val">{{ $debito['cuit'] ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">CBU:</td>
            <td class="val">{{ $debito['cbu'] ?? '—' }}</td>
        </tr>
    </table>

    @if (!empty($datos['codigo_validacion']))
        <div class="codigo-validacion">{{ $datos['codigo_validacion'] }}</div>
    @endif

    <div class="seguridad">Nivel de seguridad: confidencial</div>

    <p class="legal">
        Este comprobante acredita la existencia de una transferencia electrónica de fondos cursada a través de la Red Interbanking. La
        causa de la transferencia, su imputación y efectos dependerán de lo acordado entre las partes en el marco de su relación, de la
        que no es parte Interbanking S.A. Este documento no sustituye los comprobantes oficiales que deban ser emitidos y entregados
        por las partes conforme lo establezcan las normas aplicables. El presente comprobante se emite con carácter confidencial y para
        uso exclusivo de las partes involucradas
    </p>
</body>
</html>
