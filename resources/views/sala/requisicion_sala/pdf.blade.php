<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Requisición de sala {{ $data->numerorequisicion }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 8px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #85C1E9; font-weight: bold; text-align: left; color: #17202A; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 14%; }
        .muted { color: #555; font-size: 8px; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        .pdf-cabecera .logo-empresa { max-width: 180px; max-height: 56px; }
        .items th, .items td { font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .cen { text-align: center; }
    </style>
</head>
<body>
@php
    use App\Models\Sala\RequisicionSalaArticulo;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $nombreEmpresaLogo = optional($data->empresas)->nombre;
    $logoEmpresaDat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresaLogo);
    $logoEmpresaDataUri = $logoEmpresaDat['uri'] ?? null;
@endphp
<table class="pdf-cabecera">
    <tr>
        <td style="width:55%;">
            @if ($logoEmpresaDataUri)
                <img class="logo-empresa" src="{{ $logoEmpresaDataUri }}" alt="">
            @endif
            <div style="font-size: 12px; font-weight: bold; margin-top: 4px;">{{ $nombreEmpresaLogo ?? '—' }}</div>
        </td>
        <td style="text-align: right;">
            <h1 style="margin-top: 0;">Requisición de sala Nº {{ $data->numerorequisicion }}</h1>
            <p class="muted" style="margin: 0;">Generado el {{ date('d/m/Y H:i') }}</p>
        </td>
    </tr>
</table>

<h2>Datos generales</h2>
<table class="cabecera">
    <tr>
        <td class="lbl">Empresa</td>
        <td>{{ optional($data->empresas)->nombre ?? '—' }}</td>
        <td class="lbl">Centro de costo</td>
        <td>{{ optional($data->centrocostos)->codigo ?? '' }} {{ optional($data->centrocostos)->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Depósito</td>
        <td>{{ optional($data->depositos)->nombre ?? '—' }}</td>
        <td class="lbl">Zona de sala</td>
        <td>{{ optional($data->zona_salas)->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Prioridad</td>
        <td>{{ optional($data->prioridad_salas)->nombre ?? '—' }}</td>
        <td class="lbl">Estado</td>
        <td>{{ $data->estado ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Fecha</td>
        <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—' }}</td>
        <td class="lbl">Fecha entrega</td>
        <td>{{ $data->fecha_entrega ? date('d/m/Y', strtotime($data->fecha_entrega)) : '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Solicitante</td>
        <td colspan="3">{{ optional($data->solicitante)->nombre ?? optional($data->usuarios)->nombre ?? '—' }}</td>
    </tr>
    <tr>
        <td class="lbl">Comentario</td>
        <td colspan="3">{{ $data->comentario !== null && $data->comentario !== '' ? $data->comentario : '—' }}</td>
    </tr>
    @if($data->detalle)
    <tr>
        <td class="lbl">Detalle</td>
        <td colspan="3">{{ $data->detalle }}</td>
    </tr>
    @endif
</table>

<h2>Artículos</h2>
<table class="items">
    <thead>
        <tr>
            <th>SKU</th>
            <th>Descripción</th>
            <th>Leyenda</th>
            <th class="num">Cantidad</th>
            <th class="cen">Fuera serv.</th>
            <th>UID</th>
            <th>Nº parte única</th>
            <th>Destino</th>
            <th>Estado</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data->requisicion_sala_articulos as $linea)
        @php
            $destinoLinea = RequisicionSalaArticulo::destinoNombrePorValor(trim((string) ($linea->destino ?? '')));
            $estadoLinea = RequisicionSalaArticulo::estadoLineaNombrePorValor(trim((string) ($linea->estado ?? ' ')) ?: ' ');
            $motivoParcial = RequisicionSalaArticulo::estadoParcialNombrePorValor($linea->estadoparcial ?? null);
            $leyendaLinea = trim((string) ($linea->detalle ?? ''));
            $descripcionLinea = trim((string) (optional($linea->articulos)->descripcion ?? ''));
            if ($descripcionLinea === '') {
                $descripcionLinea = trim((string) (optional($linea->articulos)->detalle ?? ''));
            }
        @endphp
        <tr>
            <td>{{ optional($linea->articulos)->sku ?? '—' }}</td>
            <td>{{ $descripcionLinea !== '' ? $descripcionLinea : '—' }}</td>
            <td>{{ $leyendaLinea !== '' ? $leyendaLinea : '—' }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $linea->cantidad, 4, '.', ''), '0'), '.') }}</td>
            <td class="cen">{{ $linea->fueradeservicio ? 'Sí' : 'No' }}</td>
            <td>{{ $linea->uid ?: '—' }}</td>
            <td>{{ $linea->numeroparte ?: '—' }}</td>
            <td>{{ $destinoLinea !== '' ? $destinoLinea : '—' }}</td>
            <td>
                {{ $estadoLinea }}
                @if ($motivoParcial !== '')
                    <span class="muted"> — {{ $motivoParcial }}</span>
                @endif
            </td>
        </tr>
        @empty
        <tr><td colspan="9">Sin ítems.</td></tr>
        @endforelse
    </tbody>
</table>
</body>
</html>
