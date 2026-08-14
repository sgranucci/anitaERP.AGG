@php
    $cuentaId = (int) ($cuenta['cuentacontable_id'] ?? 0);
    $codigo = (string) ($cuenta['codigo'] ?? '');
    $nombre = (string) ($cuenta['nombre'] ?? '');
@endphp
<tr class="tm-cuenta-campo cuenta-auto-multi-renglon">
    <td>
        <input type="hidden" class="cuentacontable_id" name="cuentas_multiples[{{ $clave }}][]" value="{{ $cuentaId ?: '' }}">
        <div class="input-group input-group-sm">
            <input type="text" class="codigocuentacontable form-control" value="{{ $codigo }}" autocomplete="off" placeholder="C&oacute;digo">
            <div class="input-group-append">
                <button type="button" title="Consulta cuentas" class="btn btn-outline-secondary consultacuentacontable tooltipsC">
                    <i class="fa fa-search"></i>
                </button>
            </div>
        </div>
    </td>
    <td>
        <input type="text" class="nombrecuentacontable form-control form-control-sm" value="{{ $nombre }}" readonly placeholder="Descripci&oacute;n">
    </td>
    <td class="text-center align-middle">
        <button type="button" class="btn btn-sm btn-accion-tabla cuenta-auto-multi-quitar" title="Quitar">
            <i class="fa fa-times-circle text-danger"></i>
        </button>
    </td>
</tr>
