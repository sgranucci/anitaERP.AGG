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
        ? 'Deuda de proveedores (facturas impagas)'
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
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; line-height: 1.35; }
        table.data {
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 4px;
            vertical-align: top;
            word-wrap: break-word;
        }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 7px;
            font-weight: bold;
            color: #17202A;
        }
        .text-right { text-align: right; white-space: nowrap; }
        .listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 4px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 32%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 46%; text-align: center;">
                <h2 style="margin: 0; font-size: 18px; font-weight: bold;">{{ $tituloReporte }}</h2>
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
