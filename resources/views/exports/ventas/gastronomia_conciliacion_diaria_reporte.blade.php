@php
    $colspan = 38;
@endphp
<table>
    @if (! empty($reservarFilaLogoExcel))
        <tbody>
            <tr>
                <td colspan="{{ $colspan }}" style="height: 52px;">&#160;</td>
            </tr>
        </tbody>
    @endif
    <tbody>
        <tr>
            <td colspan="{{ $colspan }}">
                <strong style="font-size: 16pt;">{{ $titulo ?? 'Conciliación gastronomía' }}</strong>
            </td>
        </tr>
        <tr>
            <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                Generado {{ date('d/m/Y H:i') }}
            </td>
        </tr>
        @if (! empty($subtitulo))
            <tr>
                <td colspan="{{ $colspan }}" style="font-size: 10pt; color: #444;">
                    {{ $subtitulo }}
                </td>
            </tr>
        @endif
    </tbody>
    <thead>
        <tr>
            <th>Empresa id</th>
            <th>Empresa</th>
            <th>Jornada</th>
            <th>Circuito</th>
            <th>Tipo fila</th>
            <th>Tipo PV</th>
            <th>PC (host)</th>
            <th>PV código</th>
            <th>PV CAE</th>
            <th>PV CAEA</th>
            <th>ERP CAE</th>
            <th>ERP CAEA</th>
            <th>ERP total</th>
            <th>Anita CAE</th>
            <th>Anita CAEA</th>
            <th>Anita total</th>
            <th>Rendg Z portadora</th>
            <th>Rendg CAEA neto</th>
            <th>Rendg total</th>
            <th>Δ ERP-Anita</th>
            <th>Δ ERP-Rendg</th>
            <th>Estado</th>
            <th>Cant. fc</th>
            <th>NC ERP</th>
            <th>NC rendg</th>
            <th>Rendg neto</th>
            <th>Legacy Z</th>
            <th>fc_caea dup</th>
            <th>Asiento factura día</th>
            <th>Asiento post-cierre</th>
            <th>Asientos total</th>
            <th>Δ rendg-asientos</th>
            <th>Flash AyB</th>
            <th>Flash estac.</th>
            <th>Flash total</th>
            <th>Δ ERP-flash</th>
            <th>Δ Anita-flash</th>
            <th>Δ rendg-flash</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($filas ?? [] as $fila)
            <tr>
                <td>{{ $fila['empresa_id'] ?? '' }}</td>
                <td>{{ $fila['empresa_nombre'] ?? '' }}</td>
                <td>{{ $fila['fecha_jornada'] ?? '' }}</td>
                <td>{{ $fila['circuito'] ?? '' }}</td>
                <td>{{ $fila['tipo_fila'] ?? '' }}</td>
                <td>{{ $fila['tipo_pv'] ?? '' }}</td>
                <td>{{ $fila['identificador_pc'] ?? '' }}</td>
                <td>{{ $fila['pv_codigo'] ?? '' }}</td>
                <td>{{ $fila['pv_cae'] ?? '' }}</td>
                <td>{{ $fila['pv_caea'] ?? '' }}</td>
                <td>{{ is_numeric($fila['ventas_erp_cae'] ?? null) ? (float) $fila['ventas_erp_cae'] : '' }}</td>
                <td>{{ is_numeric($fila['ventas_erp_caea'] ?? null) ? (float) $fila['ventas_erp_caea'] : '' }}</td>
                <td>{{ is_numeric($fila['ventas_erp_total'] ?? null) ? (float) $fila['ventas_erp_total'] : '' }}</td>
                <td>{{ is_numeric($fila['ventas_anita_cae'] ?? null) ? (float) $fila['ventas_anita_cae'] : '' }}</td>
                <td>{{ is_numeric($fila['ventas_anita_caea'] ?? null) ? (float) $fila['ventas_anita_caea'] : '' }}</td>
                <td>{{ is_numeric($fila['ventas_anita_total'] ?? null) ? (float) $fila['ventas_anita_total'] : '' }}</td>
                <td>{{ is_numeric($fila['rendgastro_z_portadora'] ?? null) ? (float) $fila['rendgastro_z_portadora'] : '' }}</td>
                <td>{{ is_numeric($fila['rendgastro_caea_campo'] ?? null) ? (float) $fila['rendgastro_caea_campo'] : '' }}</td>
                <td>{{ is_numeric($fila['rendgastro_total'] ?? null) ? (float) $fila['rendgastro_total'] : '' }}</td>
                <td>{{ is_numeric($fila['diff_erp_anita'] ?? null) ? (float) $fila['diff_erp_anita'] : '' }}</td>
                <td>{{ is_numeric($fila['diff_erp_rendg'] ?? null) ? (float) $fila['diff_erp_rendg'] : '' }}</td>
                <td>{{ $fila['estado'] ?? '' }}</td>
                <td>{{ $fila['cant_facturas'] ?? '' }}</td>
                <td>{{ is_numeric($fila['nc_erp'] ?? null) ? (float) $fila['nc_erp'] : '' }}</td>
                <td>{{ is_numeric($fila['nc_rendg'] ?? null) ? (float) $fila['nc_rendg'] : '' }}</td>
                <td>{{ is_numeric($fila['rendg_neto'] ?? null) ? (float) $fila['rendg_neto'] : '' }}</td>
                <td>{{ is_numeric($fila['rendg_legacy_z'] ?? null) ? (float) $fila['rendg_legacy_z'] : '' }}</td>
                <td>{{ is_numeric($fila['fc_caea_duplicado'] ?? null) ? (float) $fila['fc_caea_duplicado'] : '' }}</td>
                <td>{{ is_numeric($fila['asiento_factura_dia'] ?? null) ? (float) $fila['asiento_factura_dia'] : '' }}</td>
                <td>{{ is_numeric($fila['asiento_post_cierre'] ?? null) ? (float) $fila['asiento_post_cierre'] : '' }}</td>
                <td>{{ is_numeric($fila['asientos_total'] ?? null) ? (float) $fila['asientos_total'] : '' }}</td>
                <td>{{ is_numeric($fila['diff_rendg_asientos'] ?? null) ? (float) $fila['diff_rendg_asientos'] : '' }}</td>
                <td>{{ is_numeric($fila['flash_ayb'] ?? null) ? (float) $fila['flash_ayb'] : '' }}</td>
                <td>{{ is_numeric($fila['flash_estac'] ?? null) ? (float) $fila['flash_estac'] : '' }}</td>
                <td>{{ is_numeric($fila['total_flash'] ?? null) ? (float) $fila['total_flash'] : '' }}</td>
                <td>{{ is_numeric($fila['diff_erp_flash'] ?? null) ? (float) $fila['diff_erp_flash'] : '' }}</td>
                <td>{{ is_numeric($fila['diff_anita_flash'] ?? null) ? (float) $fila['diff_anita_flash'] : '' }}</td>
                <td>{{ is_numeric($fila['diff_rendg_flash'] ?? null) ? (float) $fila['diff_rendg_flash'] : '' }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
