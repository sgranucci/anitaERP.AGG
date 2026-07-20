@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $fmt = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', '.'), '0'), ',');
    $logo = null;
    $nombreEmpresa = optional($entrega->empleado->empresa)->nombre;
    if ($nombreEmpresa) {
        $dat = EmpresaLogoArchivo::dataUriDesdeNombre($nombreEmpresa);
        $logo = $dat['uri'] ?? null;
    }
    // CUIL formateado XX-XXXXXXXX-X (requerido por TuLegajo para asociar el documento al empleado).
    $cuilRaw = preg_replace('/\D/', '', (string) ($entrega->empleado->cuil ?? ''));
    $cuilFmt = strlen($cuilRaw) === 11
        ? substr($cuilRaw, 0, 2).'-'.substr($cuilRaw, 2, 8).'-'.substr($cuilRaw, 10, 1)
        : ($entrega->empleado->cuil ?? '');
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #17202A; }
        .cab { width: 100%; margin-bottom: 12px; border-bottom: 2px solid #85C1E9; padding-bottom: 6px; }
        .cab td { vertical-align: middle; }
        h1 { font-size: 16px; margin: 0; }
        .sub { font-size: 10px; color: #555; }
        .datos td { padding: 2px 6px; font-size: 11px; }
        .datos .lbl { color: #555; width: 110px; }
        table.items { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.items th { background: #85C1E9; border: 1px solid #ccc; padding: 5px; text-align: left; }
        table.items td { border: 1px solid #ccc; padding: 5px; }
        .text-right { text-align: right; }
        .firma { margin-top: 60px; width: 100%; }
        .firma td { text-align: center; font-size: 10px; padding-top: 4px; }
        .firma .linea { border-top: 1px solid #333; width: 60%; margin: 0 auto; }
        .nota { margin-top: 18px; font-size: 9px; color: #555; }
    </style>
</head>
<body>
    <table class="cab">
        <tr>
            <td style="width:120px">
                @if ($logo)<img src="{{ $logo }}" style="height:46px">@endif
            </td>
            <td>
                <h1>Comprobante de entrega de indumentaria</h1>
                <div class="sub">
                    {{ $nombreEmpresa }} · Comprobante #{{ $entrega->id }} ·
                    Fecha {{ optional($entrega->fecha)->format('d/m/Y') }}
                </div>
            </td>
        </tr>
    </table>

    <table class="datos">
        <tr>
            <td class="lbl">Legajo</td><td><strong>{{ $entrega->empleado->legajo }}</strong></td>
            <td class="lbl">Empleado</td><td><strong>{{ $entrega->empleado->nombre }}</strong></td>
        </tr>
        <tr>
            <td class="lbl">CUIL</td><td><strong>{{ $cuilFmt }}</strong></td>
            <td class="lbl">Agrupamiento</td><td>{{ optional($entrega->empleado->agrupamiento)->descripcion }}</td>
        </tr>
        <tr>
            <td class="lbl">Depósito</td><td>{{ optional($entrega->deposito)->nombre }}</td>
            <td class="lbl">Fecha</td><td>{{ optional($entrega->fecha)->format('d/m/Y') }}</td>
        </tr>
        @if ($entrega->observacion)
            <tr><td class="lbl">Observación</td><td colspan="3">{{ $entrega->observacion }}</td></tr>
        @endif
    </table>

    <table class="items">
        <thead>
            <tr>
                <th>Prenda</th><th>Color</th><th>Talle</th><th>SKU</th><th class="text-right">Cantidad</th><th>Vence</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($entrega->articulos as $a)
                <tr>
                    <td>{{ optional($a->prenda)->codigo }} - {{ optional($a->prenda)->descripcion }}</td>
                    <td>{{ optional($a->color)->nombre }}</td>
                    <td>{{ optional($a->talle)->nombre }}</td>
                    <td>{{ $a->sku }}</td>
                    <td class="text-right">{{ $fmt($a->cantidad) }}</td>
                    <td>{{ $a->vence_el ? \Carbon\Carbon::parse($a->vence_el)->format('d/m/Y') : '' }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="firma">
        <tr>
            <td><div class="linea"></div>Firma del empleado</td>
            <td><div class="linea"></div>Entregó ({{ optional($entrega->usuario)->nombre }})</td>
        </tr>
    </table>

    <div class="nota">
        Recibí de conformidad las prendas detalladas, comprometiéndome a su uso y cuidado según las normas de la empresa.
    </div>
    <div class="nota" style="text-align:right; margin-top:6px;">
        CUIL {{ $cuilFmt }} · Comprobante #{{ $entrega->id }}
    </div>
</body>
</html>
