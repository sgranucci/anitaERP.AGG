@php
    use App\Models\Contable\Centrocosto;
    use App\Models\Contable\Cuentacontable;

    $linea = $linea ?? null;
    $empresaId = (int) data_get($linea, 'empresa_id', 0);
    $cuentaId = (int) data_get($linea, 'cuentacontable_id', 0);
    $contrapId = (int) data_get($linea, 'cuentacontable_contrapartida_id', 0);
    $ccId = (int) data_get($linea, 'centrocosto_id', 0);

    $cuentaCodigo = data_get($linea, 'cuentacontable.codigo', '');
    $cuentaNombre = data_get($linea, 'cuentacontable.nombre', '');
    if ($cuentaId > 0 && ($cuentaCodigo === '' || $cuentaCodigo === null)) {
        $cta = Cuentacontable::query()->find($cuentaId);
        $cuentaCodigo = $cta->codigo ?? '';
        $cuentaNombre = $cta->nombre ?? '';
    }

    $contrapCodigo = data_get($linea, 'cuentacontableContrapartida.codigo', '');
    $contrapNombre = data_get($linea, 'cuentacontableContrapartida.nombre', '');
    if ($contrapId > 0 && ($contrapCodigo === '' || $contrapCodigo === null)) {
        $cta = Cuentacontable::query()->find($contrapId);
        $contrapCodigo = $cta->codigo ?? '';
        $contrapNombre = $cta->nombre ?? '';
    }

    $ccCodigo = data_get($linea, 'centrocosto.codigo', '');
    $ccNombre = data_get($linea, 'centrocosto.nombre', '');
    if ($ccId > 0 && ($ccCodigo === '' || $ccCodigo === null)) {
        $cc = Centrocosto::query()->find($ccId);
        $ccCodigo = $cc->codigo ?? '';
        $ccNombre = $cc->nombre ?? '';
    }

    $editUrlCuenta = ($cuentaId > 0 && $puedeAbrirAbmCuenta)
        ? route('editar_cuentacontable', ['id' => $cuentaId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
    $editUrlContrap = ($contrapId > 0 && $puedeAbrirAbmCuenta)
        ? route('editar_cuentacontable', ['id' => $contrapId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
    $editUrlCc = ($ccId > 0 && $puedeAbrirAbmCc)
        ? route('editar_centrocosto', ['id' => $ccId, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<tr class="item-apertura-gasto-empresa">
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
        <div class="tm-cuentacontable-campo apg-cuenta-compact d-flex flex-nowrap align-items-center" style="gap:4px;">
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
    <td>
        <div class="tm-cuentacontable-campo apg-cuenta-compact d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" class="cuentacontable_id" name="cuentacontable_contrapartida_ids[]" value="{{ $contrapId > 0 ? $contrapId : '' }}">
            <button type="button" title="Consulta contrapartida" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbmCuenta)
                <a href="{{ $editUrlContrap }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $contrapId > 0 ? '' : 'd-none' }}"
                   title="Abrir contrapartida en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5.5rem;flex-shrink:0;"
                   value="{{ $contrapCodigo }}" placeholder="C&oacute;d." autocomplete="off">
            <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly
                   value="{{ $contrapNombre }}" placeholder="Descripci&oacute;n" style="min-width:0;flex:1 1 auto;">
        </div>
    </td>
    <td>
        <div class="tm-centrocosto-campo apg-cuenta-compact d-flex flex-nowrap align-items-center" style="gap:4px;">
            <input type="hidden" class="centrocosto_id" name="centrocosto_ids[]" value="{{ $ccId > 0 ? $ccId : '' }}">
            <button type="button" title="Consulta centro de costo" class="btn-accion-tabla consultacentrocosto tooltipsC flex-shrink-0">
                <i class="fa fa-search text-primary"></i>
            </button>
            @if ($puedeAbrirAbmCc)
                <a href="{{ $editUrlCc }}" target="_blank" rel="noopener"
                   class="btn-accion-tabla btn-link-editar-centrocosto tooltipsC flex-shrink-0 {{ $ccId > 0 ? '' : 'd-none' }}"
                   title="Abrir centro de costo en ABM">
                    <i class="fa fa-edit"></i>
                </a>
            @endif
            <input type="text" class="codigocentrocosto form-control form-control-sm" style="width:4.5rem;flex-shrink:0;"
                   value="{{ $ccCodigo }}" placeholder="C&oacute;d." autocomplete="off">
            <input type="text" class="descripcioncentrocosto form-control form-control-sm text-truncate" readonly
                   value="{{ $ccNombre }}" placeholder="Descripci&oacute;n" style="min-width:0;flex:1 1 auto;">
        </div>
    </td>
    <td class="text-center text-nowrap">
        <button type="button" title="Replicar cuentas a las dem&aacute;s empresas" class="btn-accion-tabla replicar_apertura_gasto_empresas tooltipsC">
            <i class="fa fa-copy text-primary"></i>
        </button>
        <button type="button" title="Eliminar rengl&oacute;n" class="btn-accion-tabla eliminar_apertura_gasto_empresa tooltipsC">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
