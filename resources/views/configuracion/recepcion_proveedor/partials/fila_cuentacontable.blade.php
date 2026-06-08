@php
    $campoNombre = $campo ?? '';
    $labelTexto = $label ?? '';
    $cuentaId = (int) old($campoNombre, optional($config)->{$campoNombre} ?? 0);
    $relacion = $relacion ?? null;
    $codigo = old($campoNombre.'_codigo', optional($relacion)->codigo ?? '');
    $nombre = old($campoNombre.'_nombre', optional($relacion)->nombre ?? '');
@endphp
<tr class="item-cuenta-contable tm-cuenta-campo" data-campo="{{ $campoNombre }}">
    <td class="align-middle">{{ $labelTexto }}</td>
    <td>
        <input type="hidden" class="cuentacontable_id" id="{{ $campoNombre }}" name="{{ $campoNombre }}" value="{{ $cuentaId ?: '' }}">
        <div class="input-group input-group-sm">
            <input type="text" class="codigocuentacontable form-control" id="{{ $campoNombre }}_codigo" value="{{ $codigo }}" autocomplete="off" placeholder="C&oacute;digo">
            <div class="input-group-append">
                <button type="button" title="Consulta cuentas" class="btn btn-outline-secondary consultacuentacontable tooltipsC">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </div>
    </td>
    <td>
        <input type="text" class="nombrecuentacontable form-control form-control-sm" id="{{ $campoNombre }}_nombre" value="{{ $nombre }}" readonly placeholder="Descripci&oacute;n">
    </td>
</tr>
