@php
    use App\Models\Contable\Cuentacontable;

    $linea = $linea ?? null;
    $empresaId = (int) data_get($linea, 'empresa_id', 0);
    $debeId = (int) data_get($linea, 'cuentacontabledebe_id', 0);
    $haberId = (int) data_get($linea, 'cuentacontablehaber_id', 0);

    $debeCodigo = data_get($linea, 'cuentacontabledebe.codigo', '');
    $debeNombre = data_get($linea, 'cuentacontabledebe.nombre', '');
    if ($debeId > 0 && ($debeCodigo === '' || $debeCodigo === null)) {
        $cta = Cuentacontable::query()->find($debeId);
        $debeCodigo = $cta->codigo ?? '';
        $debeNombre = $cta->nombre ?? '';
    }

    $haberCodigo = data_get($linea, 'cuentacontablehaber.codigo', '');
    $haberNombre = data_get($linea, 'cuentacontablehaber.nombre', '');
    if ($haberId > 0 && ($haberCodigo === '' || $haberCodigo === null)) {
        $cta = Cuentacontable::query()->find($haberId);
        $haberCodigo = $cta->codigo ?? '';
        $haberNombre = $cta->nombre ?? '';
    }

    $editUrlDebe = ($debeId > 0 && $puedeAbrirAbmCuenta)
        ? route('editar_cuentacontable', ['id' => $debeId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
    $editUrlHaber = ($haberId > 0 && $puedeAbrirAbmCuenta)
        ? route('editar_cuentacontable', ['id' => $haberId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<tr class="item-concepto-ivacompra-empresa">
    <td>
        @include('includes.form-empresa-asignada-control', [
            'empresa_query' => $empresa_query,
            'empresa_id' => $empresaId > 0 ? $empresaId : null,
            'name' => 'empresa_ids[]',
            'select_class' => 'empresa',
            'permite_vacio' => true,
            'opcion_vacia' => '-- Seleccionar --',
            'required' => false,
        ])
    </td>
    <td>
        <div class="tm-cuentacontable-campo cic-cuenta-compact d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" class="cuentacontable_id" name="cuentacontabledebe_ids[]" value="{{ $debeId > 0 ? $debeId : '' }}">
            <button type="button" title="Consulta cuenta Debe" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbmCuenta)
                <a href="{{ $editUrlDebe }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $debeId > 0 ? '' : 'd-none' }}"
                   title="Abrir cuenta en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5.5rem;flex-shrink:0;"
                   value="{{ $debeCodigo }}" placeholder="Cód." autocomplete="off">
            <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly
                   value="{{ $debeNombre }}" placeholder="Descripción" style="min-width:0;flex:1 1 auto;">
        </div>
    </td>
    <td>
        <div class="tm-cuentacontable-campo cic-cuenta-compact d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" class="cuentacontable_id" name="cuentacontablehaber_ids[]" value="{{ $haberId > 0 ? $haberId : '' }}">
            <button type="button" title="Consulta cuenta Haber" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbmCuenta)
                <a href="{{ $editUrlHaber }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $haberId > 0 ? '' : 'd-none' }}"
                   title="Abrir cuenta en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5.5rem;flex-shrink:0;"
                   value="{{ $haberCodigo }}" placeholder="Cód." autocomplete="off">
            <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly
                   value="{{ $haberNombre }}" placeholder="Descripción" style="min-width:0;flex:1 1 auto;">
        </div>
    </td>
    <td class="text-center text-nowrap">
        <button type="button" title="Replicar cuentas a las demás empresas" class="btn-accion-tabla replicar_concepto_ivacompra_empresas tooltipsC">
            <i class="fa fa-copy text-primary"></i>
        </button>
        <button type="button" title="Eliminar renglón" class="btn-accion-tabla eliminar_concepto_ivacompra_empresa tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
