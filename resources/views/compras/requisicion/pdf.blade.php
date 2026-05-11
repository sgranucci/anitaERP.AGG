<!DOCTYPE html>
<html lang="es">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Requisición {{ $data->numerorequisicion }}</title>
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 9px; color: #222; }
        h1 { font-size: 15px; margin: 0 0 8px 0; }
        h2 { font-size: 11px; margin: 14px 0 6px 0; border-bottom: 1px solid #333; padding-bottom: 2px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
        th, td { border: 1px solid #444; padding: 3px 4px; vertical-align: top; }
        th { background: #e8e8e8; font-weight: bold; text-align: left; }
        .no-border { border: none; }
        .cabecera td { width: 25%; }
        .cabecera .lbl { background: #f0f0f0; font-weight: bold; width: 14%; }
        .muted { color: #555; font-size: 8px; }
        .pdf-cabecera { margin-bottom: 10px; }
        .pdf-cabecera td { border: none !important; vertical-align: top; }
        /* Misma caja visual que logos del listado PDF (cabecera requisiciones) */
        .pdf-cabecera .logo-empresa {
            max-width: 180px;
            max-height: 56px;
            width: auto;
            height: auto;
            object-fit: contain;
            vertical-align: middle;
        }
        .items th, .items td { font-size: 10px; padding: 4px 5px; word-wrap: break-word; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .cen { text-align: center; }
        .bloque-texto { white-space: pre-wrap; word-wrap: break-word; max-width: 100%; }
        .subtotal { font-weight: bold; background: #f5f5f5; }
    </style>
</head>
<body>
    @php
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
                <h1 style="margin-top: 0;">Requisición Nº {{ $data->numerorequisicion }}</h1>
                <p class="muted" style="margin: 0;">Generado el {{ date('d/m/Y H:i') }}</p>
            </td>
        </tr>
    </table>

    <h2>Datos generales</h2>
    <table class="cabecera">
        <tr>
            <td class="lbl">Empresa</td>
            <td>{{ $data->empresas->nombre ?? '—' }}</td>
            <td class="lbl">Centro de costo</td>
            <td>{{ optional($data->centrocostos)->codigo ?? '' }} {{ optional($data->centrocostos)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Fecha</td>
            <td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '—' }}</td>
            <td class="lbl">Fecha entrega</td>
            <td>{{ $data->fechaentrega ? date('d/m/Y', strtotime($data->fechaentrega)) : '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Estado</td>
            <td>{{ $data->estado ?? '—' }}</td>
            <td class="lbl">Oficina compra</td>
            <td>{{ optional($data->oficinacompras)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Proveedor sugerido</td>
            <td colspan="3">
                @if($data->proveedores)
                    {{ $data->proveedores->codigo ?? '' }} — {{ $data->proveedores->nombre ?? '' }}
                @else
                    —
                @endif
            </td>
        </tr>
        <tr>
            <td class="lbl">Forma de pago</td>
            <td>{{ optional($data->formapagos)->nombre ?? '—' }}</td>
            <td class="lbl">Usuario alta</td>
            <td>{{ optional($data->usuarios)->nombre ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Tratamiento</td>
            <td>{{ $data->tratamiento ?? '—' }}</td>
            <td class="lbl">Motivo tratamiento</td>
            <td>{{ $data->motivotratamiento ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Contratación directa</td>
            <td>{{ $data->contrataciondirecta ?? '—' }}</td>
            <td class="lbl">Comentario</td>
            <td>{{ $data->comentario ?? '—' }}</td>
        </tr>
        <tr>
            <td class="lbl">Total (primer ítem, con conversión)</td>
            <td class="num">{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
            <td class="lbl">Moneda referencia</td>
            <td>{{ $data->monedacabecera_abreviatura ?? '—' }}</td>
        </tr>
    </table>

    <table>
        <tr>
            <td class="lbl" style="width:12%;">Detalle</td>
            <td class="bloque-texto">{{ $data->detalle ?? '—' }}</td>
        </tr>
    </table>

    <h2>Ítems</h2>
    <table class="items">
        <thead>
            <tr>
                <th class="cen">#</th>
                <th>SKU</th>
                <th>Descripción artículo</th>
                <th class="num">Cant.</th>
                <th class="num">P. unit.</th>
                <th>Mon.</th>
                <th>F. entrega línea</th>
                <th class="num">Cant. alt.</th>
                <th>CC destino</th>
                <th>Partida</th>
                <th>CAPEX</th>
                <th class="num">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @php $totalGeneral = 0.0; @endphp
            @foreach ($data->requisicion_articulos as $i => $linea)
                @php
                    $sub = (float) $linea->cantidad * (float) $linea->precio;
                    $totalGeneral += $sub;
                    $ccDest = $linea->centrocostos_destino;
                    $part = $linea->partidagastos;
                    $cpx = $linea->capexs;
                    $art = $linea->articulos;
                @endphp
                <tr>
                    <td class="cen">{{ $i + 1 }}</td>
                    <td>{{ $art->sku ?? '—' }}</td>
                    <td>{{ $art->descripcion ?? '—' }}</td>
                    <td class="num">{{ number_format((float) $linea->cantidad, 4, ',', '.') }}</td>
                    <td class="num">{{ number_format((float) $linea->precio, 4, ',', '.') }}</td>
                    <td>{{ optional($linea->monedas)->abreviatura ?? optional($linea->monedas)->nombre ?? '—' }}</td>
                    <td>{{ $linea->fechaentrega ? date('d/m/Y', strtotime($linea->fechaentrega)) : '—' }}</td>
                    <td class="num">{{ number_format((float) ($linea->cantidadalternativa ?? 0), 4, ',', '.') }}</td>
                    <td>{{ optional($ccDest)->codigo ?? '' }} {{ optional($ccDest)->nombre ?? '' }}</td>
                    <td>{{ $part->codigo ?? '' }} @if($part) — {{ optional($part->articulos)->detalle ?? $part->detalle ?? '' }} @endif</td>
                    <td>{{ $cpx->codigo ?? '' }} @if($cpx) — {{ $cpx->nombre ?? '' }} @endif</td>
                    <td class="num">{{ number_format($sub, 2, ',', '.') }}</td>
                </tr>
            @endforeach
            <tr class="subtotal">
                <td colspan="11" style="text-align:right;">Total ítems (suma cant × precio por línea)</td>
                <td class="num">{{ number_format($totalGeneral, 2, ',', '.') }}</td>
            </tr>
        </tbody>
    </table>

    @include('compras.requisicion.partials.pdf_presupuestos_resumen', ['data' => $data])

    <h2>Historia de estados</h2>
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Estado</th>
                <th>Usuario</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($data->requisicion_estados->sortBy('fecha') as $h)
                <tr>
                    <td>{{ $h->fecha ? date('d/m/Y H:i', strtotime($h->fecha)) : '—' }}</td>
                    <td>{{ $h->estado ?? '—' }}</td>
                    <td>{{ optional($h->usuarios)->nombre ?? '—' }}</td>
                    <td class="bloque-texto">{{ $h->observacion ?? '' }}</td>
                </tr>
            @empty
                <tr><td colspan="4">Sin registros de historia.</td></tr>
            @endforelse
        </tbody>
    </table>

</body>
</html>
