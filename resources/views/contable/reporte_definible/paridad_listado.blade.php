@php
    use App\Services\Contable\ReporteDefinibleParidadService;
    use App\Support\Configuracion\EmpresaLogoArchivo;

    $p = $parametros ?? [];
    $verdad = $p['verdad'] ?? [];
    $logos = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(
        ReporteDefinibleParidadService::coleccionEmpresasParaLogos((array) ($p['empresa_ids'] ?? []))
    );
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 18px 16px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 8px; color: #17202A; }
        .cab { width: 100%; margin-bottom: 6px; }
        .cab td { vertical-align: middle; }
        .titulo { font-size: 13px; font-weight: bold; }
        .meta { font-size: 8px; color: #555555; }
        table.data { width: 100%; border-collapse: collapse; }
        table.data th { background: #85C1E9; color: #17202A; border: 1px solid #cccccc; padding: 3px; font-size: 8px; }
        table.data td { border: 1px solid #cccccc; padding: 2px 3px; font-size: 7px; }
        table.data tr:nth-child(even) td { background: #f5f5f5; }
        .num { text-align: right; }
        .mal { color: #C0392B; font-weight: bold; }
        .cuenta td { font-style: italic; color: #444444; }
        .aviso { border: 1px solid #cccccc; padding: 4px; margin-bottom: 6px; font-size: 8px; }
    </style>
</head>
<body>
@if (!empty($mostrarCabecera ?? true))
    <table class="cab">
        <tr>
            <td style="width: 220px;">
                @foreach ($logos as $logo)
                    <img src="{{ $logo['uri'] }}" style="height: 38px; margin-right: 8px;" alt="{{ $logo['nombre'] }}">
                @endforeach
            </td>
            <td>
                <div class="titulo">Paridad anitaERP vs Anita — {{ $reporte->nombre ?? '' }}</div>
                <div class="meta">Generado {{ now()->format('d/m/Y H:i') }}</div>
                <div class="meta">
                    Empresas: {{ implode(', ', (array) ($p['empresa_ids'] ?? [])) }}
                    | {{ $p['fecha_desde'] ?? '' }} a {{ $p['fecha_hasta'] ?? '' }}
                    | base {{ $p['base_saldo'] ?? '' }}
                    | tolerancia {{ number_format((float) ($p['tolerancia'] ?? 0), 2, ',', '.') }}
                    | {{ count($filas) }} línea(s){{ !empty($solo_diferencias) ? ' (solo diferencias)' : '' }}
                </div>
                <div class="meta">
                    Fuente de verdad del período: <strong>{{ $verdad['etiqueta'] ?? '' }}</strong>.
                    {{ $verdad['detalle'] ?? '' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="aviso">
        Rubros comparados: {{ (int) ($resumen['rubros'] ?? 0) }}
        | con diferencia vs Anita: {{ (int) ($resumen['con_diferencia'] ?? 0) }}
        | informe impreso vs asientos: {{ (int) ($resumen['con_diferencia_motor'] ?? 0) }}
        (fuente {{ $resumen['fuente_impreso'] ?? '' }})
        | movimientos ERP {{ (int) ($resultado['stats']['movimientos_erp'] ?? 0) }}
        · Anita {{ (int) ($resultado['stats']['movimientos_anita'] ?? 0) }}
        (ctamov {{ (int) ($resultado['stats']['ctamov_filas'] ?? 0) }},
        subdiario {{ (int) ($resultado['stats']['subdiario_filas'] ?? 0) }})
    </div>
@endif

<table class="data">
    <thead>
        <tr>
            <th style="width: 46px;">Línea</th>
            <th>Rubro</th>
            <th style="width: 88px;">Informe</th>
            <th style="width: 88px;">Asientos ERP</th>
            <th style="width: 88px;">Anita</th>
            <th style="width: 84px;">Dif. motor</th>
            <th style="width: 88px;">Dif. Anita</th>
            <th style="width: 48px;">%</th>
        </tr>
    </thead>
    <tbody>
    @forelse ($filas as $fila)
        <tr>
            <td>{{ $fila['codigo'] }}</td>
            <td style="padding-left: {{ 3 + max(0, ((int) $fila['nivel']) - 1) * 8 }}px;">{{ $fila['nombre'] }}</td>
            <td class="num">{{ $fila['impreso'] !== null ? number_format((float) $fila['impreso'], 2, ',', '.') : '' }}</td>
            <td class="num">{{ number_format((float) $fila['erp'], 2, ',', '.') }}</td>
            <td class="num">{{ number_format((float) $fila['anita'], 2, ',', '.') }}</td>
            <td class="num {{ empty($fila['cuadra_motor']) ? 'mal' : '' }}">
                {{ empty($fila['cuadra_motor']) ? number_format((float) $fila['diferencia_motor'], 2, ',', '.') : '' }}
            </td>
            <td class="num {{ empty($fila['cuadra']) ? 'mal' : '' }}">
                {{ empty($fila['cuadra']) ? number_format((float) $fila['diferencia'], 2, ',', '.') : '' }}
            </td>
            <td class="num">
                {{ (empty($fila['cuadra']) && $fila['diferencia_pct'] !== null) ? number_format((float) $fila['diferencia_pct'], 2, ',', '.') : '' }}
            </td>
        </tr>
        @foreach ($fila['cuentas'] ?? [] as $cuenta)
            <tr class="cuenta">
                <td></td>
                <td style="padding-left: {{ 3 + ((int) $fila['nivel']) * 8 }}px;">Cuenta {{ $cuenta['codigo_fmt'] }}</td>
                <td></td>
                <td class="num">{{ number_format((float) $cuenta['erp'], 2, ',', '.') }}</td>
                <td class="num">{{ number_format((float) $cuenta['anita'], 2, ',', '.') }}</td>
                <td></td>
                <td class="num">{{ number_format((float) $cuenta['diferencia'], 2, ',', '.') }}</td>
                <td></td>
            </tr>
        @endforeach
    @empty
        <tr><td colspan="8" style="text-align: center;">Sin diferencias para listar.</td></tr>
    @endforelse
    </tbody>
</table>

@if (!empty($resultado['cuentas_fuera_plan']))
    <p style="margin: 8px 0 3px; font-weight: bold;">
        Cuentas con movimiento en Anita que no existen en el plan ERP
        ({{ count($resultado['cuentas_fuera_plan']) }})
    </p>
    <table class="data">
        <thead>
            <tr>
                <th style="width: 110px;">Cuenta</th>
                <th style="width: 120px;">Movimiento Anita</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
        @foreach ($resultado['cuentas_fuera_plan'] as $cuenta)
            <tr>
                <td>{{ $cuenta['codigo_fmt'] }}</td>
                <td class="num">{{ number_format((float) $cuenta['anita'], 2, ',', '.') }}</td>
                <td>Falta darla de alta como cuenta imputable en el plan del ERP.</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
</body>
</html>
