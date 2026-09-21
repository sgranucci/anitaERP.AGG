@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Compras\ProveedorCuentacorrientePreferenciasUsuario;
    use App\Support\Cuentacorriente\CuentacorrienteSaldosPorMoneda;

    foreach ($cuentacorriente as $row) {
        $row->nombreempresa = $row->empresas->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($cuentacorriente);
    $totalFilas = is_countable($cuentacorriente) ? count($cuentacorriente) : 0;
    $modoDeuda = ($modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE)
        === ProveedorCuentacorrientePreferenciasUsuario::MODO_DEUDA;
    $mostrarSaldoCorrido = (bool) ($mostrarSaldoCorrido ?? false);
    $saldosPorMoneda = $saldosPorMoneda ?? [];
    $equivalentePesos = $equivalentePesos ?? [];
    $expresion = CuentacorrienteSaldosPorMoneda::resolverExpresion($expresion ?? null);
    $enPesos = CuentacorrienteSaldosPorMoneda::esExpresionPesos($expresion);
    $abrevLocal = CuentacorrienteSaldosPorMoneda::abreviaturaLocal();
    $tituloReporte = $modoDeuda
        ? 'Deuda de proveedores (facturas, NC y adelantos)'
        : 'Cuenta corriente de proveedores';
    $subtitulo = 'Proveedor: '.trim((($codigoproveedor ?? '') !== '' ? $codigoproveedor.' — ' : '').($nombreproveedor ?? ''))
        .' · Saldo: '.CuentacorrienteSaldosPorMoneda::formatearResumen($saldosPorMoneda, 'saldo_cc')
        .' · Deuda: '.CuentacorrienteSaldosPorMoneda::formatearResumen($saldosPorMoneda, 'deuda')
        .' · Equiv. '.$abrevLocal.' (TC compr.): '.CuentacorrienteSaldosPorMoneda::formatearMonto((float) ($equivalentePesos['saldo_cc'] ?? 0), $abrevLocal);
    if ($enPesos) {
        $subtitulo .= ' · Importes expresados en '.$abrevLocal;
    }
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $tituloReporte }}</title>
    <style>
        @page { margin: 5mm 6mm 6mm 6mm; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 8.5px;
            color: #1a1a1a;
            line-height: 1.2;
            margin: 0;
            padding: 0;
        }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px 3px;
            vertical-align: middle;
            overflow: hidden;
            font-size: 8.5px;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 8px;
            font-weight: bold;
            color: #17202A;
        }
        .text-right { text-align: right; white-space: nowrap; }
        .col-nowrap { white-space: nowrap; }
        .col-texto { white-space: nowrap; overflow: hidden; }
        .listado-header {
            width: 100%;
            margin: 0 0 4px 0;
            border-bottom: 1px solid #333;
            padding-bottom: 2px;
        }
        .listado-header td { vertical-align: top; border: none; padding: 0; }
        .meta { font-size: 8px; color: #444; margin-top: 1px; line-height: 1.25; }
        h2.titulo-reporte { margin: 0; padding: 0; font-size: 13px; font-weight: bold; line-height: 1.15; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 34px; max-width: 130px; margin-right: 6px; vertical-align: top;">
                @endforeach
            </td>
            <td style="width: 50%; text-align: center;">
                <h2 class="titulo-reporte">{{ $tituloReporte }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 22%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}
                @endif
            </td>
        </tr>
    </table>
    <table class="data">
        @include('compras.cuentacorriente.partials.tabla_datos', [
            'filas' => $cuentacorriente,
            'modoVista' => $modoVista ?? ProveedorCuentacorrientePreferenciasUsuario::MODO_CUENTA_CORRIENTE,
            'saldoAnterior' => 0,
            'mostrarSaldoCorrido' => $mostrarSaldoCorrido,
            'expresion' => $expresion,
            'para_pdf' => true,
        ])
    </table>
</body>
</html>
