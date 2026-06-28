@php
    $clave = $fila['clave'] ?? '';
    $cuentaId = (int) old('cuentas.'.$clave, $fila['cuentacontable_id'] ?? 0);
    $codigo = old('cuentas.'.$clave.'_codigo', $fila['codigo'] ?? '');
    $nombre = old('cuentas.'.$clave.'_nombre', $fila['nombre'] ?? '');
    $overrideModulo = (bool) ($fila['override_modulo'] ?? false);
    $efectivoId = (int) ($fila['efectivo_id'] ?? 0);
@endphp
<tr class="item-cuenta-contable tm-cuenta-campo" data-clave="{{ $clave }}">
    <td class="align-middle small">{{ $fila['grupo'] ?? '' }}</td>
    <td class="align-middle">
        {{ $fila['descripcion'] ?? '' }}
        <div class="text-muted small">Clave: {{ $clave }}</div>
    </td>
    <td>
        <input type="hidden" class="cuentacontable_id" name="cuentas[{{ $clave }}]" value="{{ $cuentaId ?: '' }}">
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
    <td class="align-middle small text-center">
        @if ($overrideModulo)
            <span class="badge badge-warning" title="El módulo tiene cuenta propia; es la que usa el proceso">Módulo</span>
            @if ($efectivoId > 0)
                <div class="text-muted">ID {{ $efectivoId }}</div>
            @endif
        @elseif ($efectivoId > 0)
            <span class="badge badge-success">Cat&aacute;logo</span>
        @else
            <span class="badge badge-secondary">Falta</span>
        @endif
    </td>
</tr>
