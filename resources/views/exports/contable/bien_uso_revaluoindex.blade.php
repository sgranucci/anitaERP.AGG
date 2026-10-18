<table>
    @if (!empty($logosCabecera))
        <tr>
            <td colspan="12">
                @foreach ($logosCabecera as $logo)
                    @if (!empty($logo))
                        <img src="{{ $logo }}" height="40">
                    @endif
                @endforeach
            </td>
        </tr>
    @endif
    <tr>
        <td colspan="12"><strong>Revalúo contable de bienes de uso</strong></td>
    </tr>
    <tr>
        <th>Código</th>
        <th>Descripción</th>
        <th>V.U.</th>
        <th>Trans.</th>
        <th>Valor origen</th>
        <th>Amort. acum.</th>
        <th>Amort. ej.</th>
        <th>Amort. mens.</th>
        <th>V. residual</th>
        <th>Coef.</th>
        <th>V. actual</th>
        <th>Incr. revalúo</th>
    </tr>
    @foreach ($filas as $f)
        <tr>
            <td>{{ $f['codigo_bien'] }}</td>
            <td>{{ $f['descripcion'] }}</td>
            <td>{{ $f['vida_util'] }}</td>
            <td>{{ $f['vida_util_transcurrida'] }}</td>
            <td>{{ (float) $f['valor_origen_cierre'] }}</td>
            <td>{{ (float) $f['amort_acum_inicio'] }}</td>
            <td>{{ (float) $f['amort_ejercicio'] }}</td>
            <td>{{ (float) $f['amort_mensual'] }}</td>
            <td>{{ (float) $f['valor_residual_contable'] }}</td>
            <td>{{ (float) $f['coef_act'] }}</td>
            <td>{{ (float) $f['valor_actual'] }}</td>
            <td>{{ (float) $f['incr_revaluo'] }}</td>
        </tr>
    @endforeach
</table>
