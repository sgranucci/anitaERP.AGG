@php
    $cliente       = $cliente_premio_uif->clientes_uif ?? null;
    $tipoDoc       = $cliente?->tipodocumentos?->abreviatura ?? '';
    $sala          = $cliente_premio_uif->salas->nombre ?? '';
    $juego         = $cliente_premio_uif->juegos_uif->nombre ?? '';
    $moneda        = $cliente_premio_uif->monedas->nombre ?? '';
    $monedaSimbolo = $cliente_premio_uif->monedas->abreviatura ?? '';
    $formaPago     = $cliente_premio_uif->formapagos->nombre ?? '';
    $operador      = $cliente_premio_uif->usuarios->nombre ?? '';

    $fmtFecha = function ($valor) {
        if (empty($valor)) return '';
        try { return \Carbon\Carbon::parse($valor)->format('d/m/Y'); }
        catch (\Throwable $e) { return ''; }
    };
    $fmtFechaHora = function ($valor) {
        if (empty($valor)) return '';
        try { return \Carbon\Carbon::parse($valor)->format('d/m/Y H:i'); }
        catch (\Throwable $e) { return ''; }
    };

    $domicilioPartes = array_filter([
        trim((string) ($cliente->domicilio ?? '')),
        ($cliente->piso ?? '') !== '' ? 'Piso '.$cliente->piso : '',
        ($cliente->departamento ?? '') !== '' ? 'Dpto. '.$cliente->departamento : '',
    ], fn($v) => $v !== '');

    $ubicacionPartes = array_filter([
        $cliente->localidades_uif->nombre ?? '',
        $cliente->provincias_uif->nombre ?? '',
        $cliente->paises_uif->nombre ?? '',
    ], fn($v) => $v !== '');

    $logoAggPath = public_path('storage/imagenes/logos/AGG.png');
    $logoAguasPath = public_path('storage/imagenes/logos/logoAguas.jpg');
    $logoMime = 'jpeg';
    $logoPath = $logoAguasPath;
    if (config('app.empresa') == 'AGG' && is_file($logoAggPath)) {
        $logoPath = $logoAggPath;
        $logoMime = 'png';
    }
    $logoData = is_file($logoPath) ? base64_encode(file_get_contents($logoPath)) : null;

    $fotoNombre = $cliente_premio_uif->foto ?? '';
    $fotoPath   = $fotoNombre ? public_path('storage/imagenes/fotos_uif/'.$fotoNombre) : '';
    $fotoData   = ($fotoPath && is_file($fotoPath)) ? base64_encode(file_get_contents($fotoPath)) : null;

    $pieLegalPath = resource_path('text/uif/premio_pie_legal.txt');
    $pieLegalParrafos = [];
    if (is_file($pieLegalPath)) {
        $pieLegalParrafos = array_values(array_filter(
            preg_split('/\r\n|\r|\n/', file_get_contents($pieLegalPath)),
            fn ($linea) => trim((string) $linea) !== ''
        ));
    }
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Identificación de Cliente Ganador de Premio Nro. {{ $cliente_premio_uif->id }}</title>
    <style type="text/css">
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 11px; color: #222; }
        h1, h2, h3, h4 { margin: 0; padding: 0; }
        .header { width: 100%; border-bottom: 2px solid #444; padding-bottom: 6px; margin-bottom: 10px; }
        .header td { vertical-align: middle; }
        .titulo { font-size: 14px; font-weight: bold; letter-spacing: 0.3px; }
        .sub-titulo { font-size: 10px; color: #555; }
        .meta { text-align: right; font-size: 10px; }
        .meta strong { font-size: 11px; }

        .seccion { width: 100%; border-collapse: collapse; margin-top: 8px; }
        .seccion-titulo {
            background: #2e3b4e; color: #fff; padding: 4px 6px; font-weight: bold;
            font-size: 11px; margin-top: 10px; margin-bottom: 0;
        }
        .seccion-cuerpo { border: 1px solid #2e3b4e; padding: 6px 8px; }

        .fichas { width: 100%; border-collapse: collapse; }
        .fichas td { padding: 3px 4px; vertical-align: top; }
        .label { color: #555; font-size: 10px; width: 28%; }
        .valor { font-weight: bold; font-size: 11px; }

        .tabla-detalle { width: 100%; border-collapse: collapse; font-size: 10px; margin-top: 4px; }
        .tabla-detalle th, .tabla-detalle td { border: 1px solid #aaa; padding: 4px 6px; }
        .tabla-detalle th { background: #f0f3f7; text-align: left; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        .observaciones { min-height: 40px; border: 1px solid #aaa; padding: 6px; font-size: 10px; }

        .firmas { width: 100%; margin-top: 40px; }
        .firmas td { width: 50%; vertical-align: top; padding: 0 12px; }
        .linea-firma { border-top: 1px solid #444; margin-top: 40px; padding-top: 4px; text-align: center; font-size: 10px; }

        .bloque-legal-uif {
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 7px;
            color: #333;
            text-align: justify;
            line-height: 1.3;
        }
        .texto-legal-parrafo {
            margin: 0 0 5px 0;
        }
        .pie {
            margin-top: 14px;
            padding-top: 8px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #777;
            text-align: center;
        }
        .foto-jugador { max-width: 110px; max-height: 130px; border: 1px solid #aaa; padding: 2px; }
    </style>
</head>
<body>

@for ($c = 1; $c <= 2; $c++)
@if ($c === 2)
<div style="page-break-before: always;"></div>
@endif
<div class="copia-premio-uif-pdf">

<table class="header">
    <tr>
        <td style="width: 35%;">
            @if ($logoData)
                <img src="data:image/{{ $logoMime }};base64,{{ $logoData }}" alt="" style="max-width: 220px; max-height: 70px;">
            @endif
        </td>
        <td style="width: 40%; text-align: center;">
            <div class="titulo">IDENTIFICACIÓN DE CLIENTES<br>GANADORES DE PREMIOS</div>
            <div class="sub-titulo">Resolución UIF</div>
        </td>
        <td class="meta" style="width: 25%;">
            <strong>Premio Nro.: {{ $cliente_premio_uif->id }}</strong><br>
            Fecha emisión: {{ date('d/m/Y H:i') }}<br>
            Fecha entrega: {{ $fmtFecha($cliente_premio_uif->fechaentrega) }}
        </td>
    </tr>
</table>

<div class="seccion-titulo">Datos del Cliente</div>
<div class="seccion-cuerpo">
    <table class="fichas">
        <tr>
            <td class="label">Nombre y apellido</td>
            <td class="valor" colspan="3">{{ $cliente->nombre ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Tipo y Nro. de documento</td>
            <td class="valor">{{ trim($tipoDoc.' '.($cliente->numerodocumento ?? '')) }}</td>
            <td class="label">CUIT/CUIL</td>
            <td class="valor">{{ $cliente->cuit ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de nacimiento</td>
            <td class="valor">{{ $fmtFecha($cliente->fechanacimiento ?? '') }}</td>
            <td class="label">Sexo</td>
            <td class="valor">{{ $cliente->sexo ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Nacionalidad</td>
            <td class="valor">{{ $cliente->pais_nacimientos->nombre ?? '' }}</td>
            <td class="label">Estado civil</td>
            <td class="valor">{{ $cliente->estadociviles_uif->nombre ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Domicilio</td>
            <td class="valor" colspan="3">{{ implode(' - ', $domicilioPartes) }}</td>
        </tr>
        <tr>
            <td class="label">Localidad / Provincia / País</td>
            <td class="valor" colspan="3">
                {{ implode(' - ', $ubicacionPartes) }}
                @if (! empty($cliente->codigopostal)) (CP {{ $cliente->codigopostal }}) @endif
            </td>
        </tr>
        <tr>
            <td class="label">Teléfono</td>
            <td class="valor">{{ $cliente->telefono ?? '' }}</td>
            <td class="label">Email</td>
            <td class="valor">{{ $cliente->email ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Actividad</td>
            <td class="valor" colspan="3">{{ $cliente->actividades_uif->nombre ?? '' }}</td>
        </tr>
    </table>
</div>

<div class="seccion-titulo">Datos del Premio</div>
<div class="seccion-cuerpo">
    <table class="fichas">
        <tr>
            <td class="label" style="width: 18%;">Sala</td>
            <td class="valor" style="width: 32%;">{{ $sala }}</td>
            <td class="label" style="width: 18%;">Juego</td>
            <td class="valor" style="width: 32%;">{{ $juego }}</td>
        </tr>
        <tr>
            <td class="label">Fecha de entrega</td>
            <td class="valor">{{ $fmtFechaHora($cliente_premio_uif->fechaentrega) }}</td>
            <td class="label">Fecha de TITO</td>
            <td class="valor">{{ $fmtFechaHora($cliente_premio_uif->fechatito) }}</td>
        </tr>
        <tr>
            <td class="label">Nro. de TITO</td>
            <td class="valor">{{ $cliente_premio_uif->numerotito ?? '' }}</td>
            <td class="label">Nro. de Posición</td>
            <td class="valor">{{ $cliente_premio_uif->posicion ?? '' }}</td>
        </tr>
        <tr>
            <td class="label">Pide recibo de pago</td>
            <td class="valor">{{ $cliente_premio_uif->piderecibopago ?? '' }}</td>
            <td class="label">Operador</td>
            <td class="valor">{{ $operador }}</td>
        </tr>
    </table>

    <table class="tabla-detalle">
        <thead>
            <tr>
                <th>Forma de pago</th>
                <th>Detalle</th>
                <th>Moneda</th>
                <th class="text-right">Monto</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{{ $formaPago }}</td>
                <td>{{ $cliente_premio_uif->detalle ?? '' }}</td>
                <td class="text-center">{{ $moneda }}</td>
                <td class="text-right">
                    {{ trim($monedaSimbolo.' '.number_format((float) ($cliente_premio_uif->monto ?? 0), 2, ',', '.')) }}
                </td>
            </tr>
        </tbody>
    </table>
</div>

@if ($fotoData)
    <div class="seccion-titulo">Foto del jugador</div>
    <div class="seccion-cuerpo" style="text-align: center;">
        <img class="foto-jugador" src="data:image/jpeg;base64,{{ $fotoData }}">
    </div>
@endif

<div class="seccion-titulo">Observaciones</div>
<div class="observaciones">&nbsp;</div>

@if (count($pieLegalParrafos) > 0)
<div class="bloque-legal-uif">
    @foreach ($pieLegalParrafos as $parrafo)
        <p class="texto-legal-parrafo">{{ $parrafo }}</p>
    @endforeach
</div>
@endif

<table class="firmas">
    <tr>
        <td>
            <div class="linea-firma">Firma y aclaración del cliente</div>
        </td>
        <td>
            <div class="linea-firma">Firma y aclaración del operador / cajero</div>
        </td>
    </tr>
</table>

<div class="pie">
    Premio Nro. {{ $cliente_premio_uif->id }} - Cliente UIF {{ $cliente->id ?? '' }} - Generado el {{ date('d/m/Y H:i') }}
</div>

</div>
@endfor

</body>
</html>
