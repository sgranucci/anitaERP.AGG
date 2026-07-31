<table>
@if (!empty($reservarFilaLogoExcel))
    <tr><td colspan="13" style="height: 52px;"></td></tr>
@endif
<tr>
    <td colspan="13"><strong style="font-size:16px;">{{ $titulo }}</strong></td>
</tr>
<tr>
    <td colspan="13">Generado {{ date('d/m/Y H:i') }}</td>
</tr>
<tr>
    <td colspan="13">
        Fecha {{ $filtros['fecha'] ?? '' }}
        · Cuenta {{ $filtros['cuenta_codigo'] ?? '' }}
        · Sistema {{ $filtros['sistema_subdiario'] ?? '' }}
        · Diff neto {{ number_format((float)(($resumen['diff_neto'] ?? 0)), 2, ',', '.') }}
        · {{ count($filas ?? []) }} filas
    </td>
</tr>
<thead>
<tr>
    <th>Estado</th>
    <th>Clave</th>
    <th>Tipo</th>
    <th>Nro</th>
    <th>PV CC</th>
    <th>PV mayor</th>
    <th>Neto CC</th>
    <th>Neto mayor</th>
    <th>Diff</th>
    <th>Debe mayor</th>
    <th>Haber mayor</th>
    <th>Lado / mov</th>
    <th>Aviso / detalle</th>
</tr>
</thead>
<tbody>
@foreach ($filas as $row)
    @php $f = is_array($row) ? $row : (array) $row; @endphp
    <tr>
        <td>{{ $f['estado'] ?? '' }}</td>
        <td>{{ $f['clave'] ?? '' }}</td>
        <td>{{ $f['tipo'] ?? '' }}</td>
        <td>{{ $f['nro'] ?? '' }}</td>
        <td>{{ $f['sucursal_cc'] ?? $f['sucursal'] ?? '' }}</td>
        <td>{{ $f['sucursal_mayor'] ?? $f['sucursal'] ?? '' }}</td>
        <td>{{ (float) ($f['neto_cc'] ?? 0) }}</td>
        <td>{{ (float) ($f['neto_mayor'] ?? 0) }}</td>
        <td>{{ (float) ($f['diff'] ?? 0) }}</td>
        <td>{{ (float) ($f['mayor_debe'] ?? 0) }}</td>
        <td>{{ (float) ($f['mayor_haber'] ?? 0) }}</td>
        <td>{{ trim(($f['lado'] ?? '').' '.($f['tipo_mov'] ?? '')) }}</td>
        <td>{{ trim(($f['aviso'] ?? '').' '.($f['desc'] ?? '')) }}</td>
    </tr>
@endforeach
</tbody>
</table>
