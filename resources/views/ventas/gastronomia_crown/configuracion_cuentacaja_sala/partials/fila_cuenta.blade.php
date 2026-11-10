@php
    $salaId = $salaId ?? 0;
    $indice = $indice ?? 0;
    $linea = $linea ?? [];
    $puedeEditar = $puedeEditar ?? false;
    $prefix = 'sala_'.$salaId.'_'.$indice;
    $ccId = $linea['cuentacaja_id'] ?? '';
    $codigo = $linea['codigo'] ?? '';
    $nombre = $linea['nombre'] ?? '';
    $moneda = $linea['moneda_abreviatura'] ?? '';
    $esDefault = ! empty($linea['es_default_f5']);
    $orden = (int) ($linea['orden'] ?? 0);
@endphp
<tr class="gc-sala-linea" data-prefix="{{ $prefix }}">
    <td>
        @include('caja.partials.campo_consulta_cuentacaja', [
            'prefix' => $prefix,
            'layout' => 'inline',
            'inputName' => 'salas['.$salaId.'][lineas]['.$indice.'][cuentacaja_id]',
            'inputId' => 'cuentacaja_id_'.$prefix,
            'cuentacajaId' => $ccId ?: '',
            'codigo' => $codigo,
            'nombre' => $nombre,
            'required' => false,
            'solo_lectura' => ! $puedeEditar,
            'mostrar_editar' => true,
            'label' => '',
        ])
    </td>
    <td class="align-middle gc-moneda-label">{{ $moneda !== '' ? $moneda : '—' }}</td>
    <td class="align-middle text-center">
        <input type="checkbox"
               class="gc-default-f5"
               name="salas[{{ $salaId }}][lineas][{{ $indice }}][es_default_f5]"
               value="1"
               {{ $esDefault ? 'checked' : '' }}
               {{ $puedeEditar ? '' : 'disabled' }}
               title="Cuenta usada por F5 (efectivo directo)">
    </td>
    <td>
        <input type="number"
               class="form-control form-control-sm gc-orden"
               name="salas[{{ $salaId }}][lineas][{{ $indice }}][orden]"
               value="{{ $orden > 0 ? $orden : '' }}"
               min="0"
               {{ $puedeEditar ? '' : 'readonly' }}>
    </td>
    <td class="align-middle text-center">
        @if ($puedeEditar)
            <button type="button" class="btn-accion-tabla gc-quitar-linea" title="Quitar">
                <i class="fa fa-times-circle text-danger"></i>
            </button>
        @endif
    </td>
</tr>
