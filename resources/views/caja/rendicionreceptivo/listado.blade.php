@php
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $rendicionreceptivos = $rendicionreceptivos ?? ($vouchers ?? collect());
    foreach ($rendicionreceptivos as $v) {
        $v->nombreempresa = $v->nombreempresa ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($rendicionreceptivos);
    $subtitulo = (is_countable($rendicionreceptivos) ? count($rendicionreceptivos) : 0).' registro(s)';
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Rendici&oacute;n de receptivo</title>
    <style>
        body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
        table.data { border-collapse: collapse; width: 100%; }
        table.data td, table.data th { border: 1px solid #cccccc; padding: 3px 5px; text-align: left; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
        table.data thead tr { background-color: #85C1E9; }
        table.data th { font-weight: bold; color: #17202A; }
        table.data ul { margin: 0; padding-left: 12px; }
        .num { text-align: right; }
        .listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
        .listado-header td { border: none; vertical-align: middle; }
        .meta { font-size: 8px; color: #444; margin-top: 2px; }
    </style>
</head>
<body>
    <table class="listado-header">
        <tr>
            <td style="width: 28%;">
                @foreach ($logosCabecera as $logo)
                    <img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 52px; max-width: 160px; margin-right: 8px; vertical-align: middle;">
                @endforeach
            </td>
            <td style="width: 47%; text-align: center;">
                <h2 style="margin: 0; font-size: 14px; font-weight: bold;">Rendici&oacute;n de receptivo</h2>
                <div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
                <div class="meta">{{ $subtitulo }}</div>
            </td>
            <td style="width: 25%; text-align: right; font-size: 8px;">
                Registros: {{ is_countable($rendicionreceptivos) ? count($rendicionreceptivos) : 0 }}
            </td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>ID</th>
                <th>N&uacute;mero</th>
                <th>Fecha</th>
                <th>Talonario Vouchers</th>
                <th>PAX</th>
                <th>Reserva</th>
                <th>Cantidad</th>
                <th>Proveedor</th>
                <th>Servicio</th>
                <th>Forma de pago</th>
                <th class="num">Monto Voucher</th>
                <th>Gu&iacute;as</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rendicionreceptivos as $data)
                <tr>
                    <td>{{ $data->id }}</td>
                    <td>{{ $data->idtalonario }}-{{ $data->numerovoucher }}</td>
                    <td>{{ date('d/m/Y', strtotime($data->fecha ?? '')) }}</td>
                    <td>{{ $data->nombretalonario }}</td>
                    <td>{{ $data->nombrepasajero }}</td>
                    <td>{{ $data->numeroreserva }}</td>
                    <td>{{ $data->pax + $data->paxfree + $data->incluido + $data->opcional }}</td>
                    <td>{{ $data->nombreproveedor ?? '' }}</td>
                    <td>{{ $data->nombreservicio ?? '' }}</td>
                    <td>{{ $data->nombreformapago ?? '' }}</td>
                    <td class="num">{{ number_format((float) $data->montovoucher, 2, ',', '.') }}</td>
                    <td>
                        <ul>
                            @foreach ($data->voucher_guias as $guia)
                                <li>{{ $guia->guias->nombre }} Porc. {{ number_format((float) $guia->porcentajecomision, 2, ',', '.') }} Comis. {{ number_format((float) $guia->montocomision, 2, ',', '.') }}</li>
                            @endforeach
                        </ul>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="12" style="text-align:center;">Sin registros</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
