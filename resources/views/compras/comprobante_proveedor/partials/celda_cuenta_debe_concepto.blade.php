@php
    $cuentaIdCelda = (int) ($cuentaIdCelda ?? 0);
    $cuentaCodigoCelda = (string) ($cuentaCodigoCelda ?? '');
    $cuentaNombreCelda = (string) ($cuentaNombreCelda ?? '');
    $puedeAbrirAbmCuenta = (bool) ($puedeAbrirAbmCuenta ?? false);
    if ($cuentaIdCelda > 0 && $cuentaCodigoCelda === '') {
        $ctaCelda = \App\Models\Contable\Cuentacontable::query()->find($cuentaIdCelda);
        $cuentaCodigoCelda = (string) ($ctaCelda->codigo ?? '');
        $cuentaNombreCelda = (string) ($ctaCelda->nombre ?? '');
    }
    $editUrlCuenta = ($cuentaIdCelda > 0 && $puedeAbrirAbmCuenta)
        ? route('editar_cuentacontable', ['id' => $cuentaIdCelda, 'origen' => 'modal_consulta', 'vista' => 'consulta'])
        : '#';
@endphp
<div class="tm-cuentacontable-campo d-flex flex-nowrap align-items-center" style="gap:4px;">
    <input type="hidden" class="cuentacontable_id" name="cuentacontabledebe_ids[]" value="{{ $cuentaIdCelda > 0 ? $cuentaIdCelda : '' }}">
    <button type="button" title="Consulta cuenta DEBE" class="btn-accion-tabla consultacuentacontable tooltipsC flex-shrink-0">
        <i class="fa fa-search text-primary"></i>
    </button>
    @if ($puedeAbrirAbmCuenta)
        <a href="{{ $editUrlCuenta }}" target="_blank" rel="noopener"
           class="btn-accion-tabla btn-link-editar-cuentacontable tooltipsC flex-shrink-0 {{ $cuentaIdCelda > 0 ? '' : 'd-none' }}"
           title="Abrir cuenta en ABM">
            <i class="fa fa-edit"></i>
        </a>
    @endif
    <input type="text" class="codigocuentacontable form-control form-control-sm" style="width:5rem;flex-shrink:0;"
           value="{{ $cuentaCodigoCelda }}" placeholder="Cód." autocomplete="off">
    <input type="text" class="nombrecuentacontable form-control form-control-sm text-truncate" readonly
           value="{{ $cuentaNombreCelda }}" placeholder="Descripción" style="min-width:0;flex:1 1 auto;">
</div>
