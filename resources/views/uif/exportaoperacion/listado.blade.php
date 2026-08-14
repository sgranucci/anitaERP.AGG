@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Uif\ClienteUifInformeReportablesSupport;

    $filas = collect($premios ?? []);
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($filas);
    $titulo = $titulo ?? ClienteUifInformeReportablesSupport::tituloInformeExcel($periodo ?? '', $empresaInforme ?? null);
    $totalFilas = $filas->count();
    $totalMonto = (float) $filas->sum('monto');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $titulo }}</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 6px; color: #1a1a1a; }
        table.data {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            border-collapse: collapse;
            width: 100%;
            table-layout: fixed;
        }
        table.data td, table.data th {
            border: 1px solid #cccccc;
            text-align: left;
            padding: 2px;
            vertical-align: top;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        table.data tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th {
            font-size: 5.5px;
            font-weight: bold;
            color: #17202A;
        }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { vertical-align: middle; border: none; }
        .meta { font-size: 8px; color: #444; margin-top: 3px; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 48px; max-width: 160px; margin-right: 8px; margin-bottom: 2px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 44%; text-align: center;">
                <h2 style="margin: 0; font-size: 12px; font-weight: bold;">{{ $titulo }}</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                @if (!empty($subtituloFiltros))
                    <div class="meta"><strong>Filtros:</strong> {{ $subtituloFiltros }}</div>
                @endif
            </td>
            <td style="width: 28%; text-align: right; font-size: 8px;">
                @if ($totalFilas > 0)
                    Registros: {{ $totalFilas }}<br>
                    Total premios: {{ number_format($totalMonto, 2, ',', '.') }}
                @endif
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th># Id</th>
                <th>Nombre</th>
                <th>Tipo Doc.</th>
                <th>Nro. Doc.</th>
                <th>Cuit</th>
                <th>F. nac.</th>
                <th>Loc. nac.</th>
                <th>Pais nac.</th>
                <th>Sexo</th>
                <th>Est. civil</th>
                <th>Domicilio</th>
                <th>Localidad</th>
                <th>CP</th>
                <th>Telefono</th>
                <th>Email</th>
                <th>Prof.</th>
                <th>PEP</th>
                <th>SO</th>
                <th>Res. ext.</th>
                <th>Res. paraiso</th>
                <th class="text-right">Premio</th>
                <th>Moneda</th>
                <th>Descripcion</th>
                <th>F. entrega</th>
                <th>F. alta</th>
                <th>H. alta</th>
                <th>Usuario alta</th>
                <th>Estado</th>
                <th>Posicion</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($filas as $data)
                <tr>
                    <td>{{ ClienteUifInformeReportablesSupport::idClienteInforme($data) }}</td>
                    <td>{{ $data->nombrecliente }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::tipoDocumentoInforme($data->abreviaturatipodocumento ?? '') }}</td>
                    <td>{{ $data->numerodocumento }}</td>
                    <td>{{ $data->cuit }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::fechaInforme($data->fechanacimiento ?? null) }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::textoInforme($data->nombrelocalidadnacimiento ?? '') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::paisInforme($data->nombrepaisnacimiento ?? '') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::sexoInforme($data->sexo ?? '') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::textoInforme($data->nombreestadocivil ?? '') }}</td>
                    <td>{{ $data->domicilio }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::textoInforme($data->nombrelocalidad ?? '') }}</td>
                    <td>{{ $data->codigopostal }}</td>
                    <td>{{ $data->telefono }}</td>
                    <td>{{ $data->email }}</td>
                    <td>{{ $data->actividad_uif_id }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::pepInforme($data->nombrepep ?? '') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::soInforme($data->nombreso ?? '') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::resideInforme($data->resideexterior ?? '') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::resideInforme($data->resideparaisofiscal ?? '') }}</td>
                    <td class="text-right">{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::monedaInforme($data->nombremoneda ?? '') }}</td>
                    <td>{{ $data->nombrejuego }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::fechaInforme($data->fechaentrega ?? null) }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::fechaInforme($data->fechaalta ?? null) }}</td>
                    <td>{{ ClienteUifInformeReportablesSupport::horaInforme($data->fechaalta ?? null) }}</td>
                    <td>{{ trim($data->nombreusuarioalta ?? '') }}</td>
                    <td>{{ $data->estado }}</td>
                    <td>{{ $data->posicion }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
