@php
    use App\Models\Contable\Cuentacontable;

    $linea = $linea ?? null;
    $empresaId = (int) data_get($linea, 'empresa_id', 0);
    $cuentaId = (int) data_get($linea, 'cuentacontable_id', 0);

    $cuentaCodigo = data_get($linea, 'cuentacontable.codigo', '');
    $cuentaNombre = data_get($linea, 'cuentacontable.nombre', '');
    if ($cuentaId > 0 && ($cuentaCodigo === '' || $cuentaCodigo === null)) {
        $cta = Cuentacontable::query()->find($cuentaId);
        $cuentaCodigo = $cta->codigo ?? '';
        $cuentaNombre = $cta->nombre ?? '';
    }

    $editUrlCuenta = ($cuentaId > 0 && $puedeAbrirAbmCuenta)
        ? route('editar_cuentacontable', ['id' => $cuentaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<tr class="item-imputacion-perdida-empresa">
    <td>
        @include('includes.form-empresa-asignada-control', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'name' => 'empresa_ids[]',
            'select_class' => 'empresa',
            'permite_vacio' => true,
            'opcion_vacia' => '-- Seleccionar --',
            'required' => true,
        ])
    </td>
    <td>
        <div class="tm-cuentacontable-campo ipp-cuenta-compact d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" class="cuentacontable_id" name="cuentacontable_ids[]" value="{{ $cuentaId > 0 ? $cuentaId : '' }}" required>
            <button type="button" title="Consulta cuentas" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbmCuenta)
                <a href="{{ $editUrlCuenta }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $cuentaId > 0 ? '' : 'd-none' }}"
                   title="Abrir cuenta en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5.5rem;flex-shrink:0;"
                   value="{{ $cuentaCodigo }}" placeholder="C&oacute;d." autocomplete="off">
            <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly
                   value="{{ $cuentaNombre }}" placeholder="Descripci&oacute;n" style="min-width:0;flex:1 1 auto;">
        </div>
    </td>
    <td class="text-center text-nowrap">
        <button type="button" title="Replicar cuenta a las dem&aacute;s empresas" class="btn-accion-tabla replicar_imputacion_perdida_empresas tooltipsC">
            <i class="fa fa-copy text-primary"></i>
        </button>
        <button type="button" title="Eliminar rengl&oacute;n" class="btn-accion-tabla eliminar_imputacion_perdida_empresa tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
