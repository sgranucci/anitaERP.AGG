@php
    $clave = $fila['clave'] ?? '';
    $multiple = (bool) ($fila['multiple'] ?? false);
    $overrideModulo = (bool) ($fila['override_modulo'] ?? false);
    $efectivoId = (int) ($fila['efectivo_id'] ?? 0);
    $cuentas = old('cuentas_multiples.'.$clave)
        ? collect(old('cuentas_multiples.'.$clave, []))->map(function ($id) {
            $id = (int) $id;
            return [
                'cuentacontable_id' => $id,
                'codigo' => '',
                'nombre' => '',
            ];
        })->all()
        : ($fila['cuentas'] ?? []);
    if ($multiple && $cuentas === []) {
        $cuentas = [[
            'cuentacontable_id' => 0,
            'codigo' => '',
            'nombre' => '',
        ]];
    }
    $cuentaId = (int) old('cuentas.'.$clave, $fila['cuentacontable_id'] ?? 0);
    $codigo = old('cuentas.'.$clave.'_codigo', $fila['codigo'] ?? '');
    $nombre = old('cuentas.'.$clave.'_nombre', $fila['nombre'] ?? '');
@endphp
@if ($multiple)
    <tr class="item-cuenta-contable-multiple" data-clave="{{ $clave }}">
        <td class="align-middle small">{{ $fila['grupo'] ?? '' }}</td>
        <td class="align-middle" colspan="3">
            <div class="mb-1">{{ $fila['descripcion'] ?? '' }}</div>
            <div class="text-muted small mb-2">Clave: {{ $clave }} — puede indicar varias cuentas</div>
            <table class="table table-sm table-bordered mb-2 cuenta-auto-multi-table">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th style="width:28%">C&oacute;digo</th>
                        <th>Descripci&oacute;n</th>
                        <th style="width:10%">Acciones</th>
                    </tr>
                </thead>
                <tbody class="cuenta-auto-multi-body" data-clave="{{ $clave }}">
                    @foreach ($cuentas as $cuenta)
                        @include('contable.cuenta_automatica.partials.fila_multiple_renglon', [
                            'clave' => $clave,
                            'cuenta' => $cuenta,
                        ])
                    @endforeach
                </tbody>
            </table>
            <button type="button" class="btn btn-outline-primary btn-sm cuenta-auto-multi-agregar" data-clave="{{ $clave }}">
                <i class="fa fa-plus"></i> Agregar cuenta
            </button>
        </td>
        <td class="align-middle small text-center">
            @if (count(array_filter($cuentas, fn ($c) => (int) ($c['cuentacontable_id'] ?? 0) > 0)) > 0)
                <span class="badge badge-success">{{ count(array_filter($cuentas, fn ($c) => (int) ($c['cuentacontable_id'] ?? 0) > 0)) }} cuenta(s)</span>
            @else
                <span class="badge badge-secondary">Falta</span>
            @endif
        </td>
    </tr>
@else
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
@endif
