@php
    $filasFiscales = $cierresFiscales['filas'] ?? [];
    $errorFiscal = $cierresFiscales['error'] ?? null;
    $marcados = old('cierre_fiscal');
    $cargaManual = $editable;
@endphp
<div class="card card-outline card-info mt-3 mb-0">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Cierres de ventas</h3>
        <span>
            @if ($editable && ($pxsolCodigos ?? []) !== [])
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-traer-pxsol">Traer de PXSol</button>
            @endif
            @if ($editable && $filasFiscales !== [])
                <button type="button" class="btn btn-outline-primary btn-sm" id="btn-aplicar-cierre-fiscal">Aplicar a la rendición</button>
            @endif
        </span>
    </div>
    <div class="card-body pb-2">
        <p class="text-muted small mb-3">
            Pendientes de {{ $comprobante->jornada->fecha->format('d/m/Y') }}:
            primero los del ERP; si falta alguno, se completa con Anita (sin repetir el mismo X/Z).
            Los elegidos quedan grabados en el ERP al guardar. El Z es el cierre fiscal; el X, el informe de hotel. No se modifica Anita.
        </p>
        @if ($editable && ($pxsolCodigos ?? []) !== [])
            <input type="hidden" name="pxsol_aplicado" id="pxsol_aplicado" value="{{ old('pxsol_aplicado') }}">
            <input type="hidden" name="pxsol_iva_facturas" id="pxsol_iva_facturas" value="{{ old('pxsol_iva_facturas', $comprobante->iva_facturas) }}">
            <input type="hidden" name="pxsol_iva_nc" id="pxsol_iva_nc" value="{{ old('pxsol_iva_nc', $comprobante->iva_nc) }}">
            <p class="small mb-3" id="pxsol-aviso">
                @if ((float) $comprobante->iva_facturas != 0.0 || (float) $comprobante->iva_nc != 0.0)
                    IVA facturas {{ number_format((float) $comprobante->iva_facturas, 2, ',', '.') }} · IVA notas de crédito {{ number_format((float) $comprobante->iva_nc, 2, ',', '.') }}
                @else
                    Si este cajero es de hotel, Traer de PXSol completa el X, el Z y las notas de crédito desde la primera habilitación hasta ahora.
                @endif
            </p>
        @endif
        @if ($errorFiscal)
            <div class="alert alert-warning">No se pudieron leer los cierres de ventas. {{ $errorFiscal }}</div>
        @endif
        @if ($cargaManual)
            <details class="mb-3 border rounded p-2">
                <summary class="text-primary" style="cursor:pointer;">Cargar un cierre a mano</summary>
                <p class="small text-muted mt-2 mb-2">Queda pendiente del día para tildarlo y aplicar a la rendición. Sirve cuando Anita no responde o el cierre no llegó.</p>
                <form method="POST" action="{{ route('cierre_fiscal_manual_tesoreria_crown_movimiento', $comprobante->id) }}" autocomplete="off" data-enter-campos="1" class="form-row align-items-end">
                    @csrf
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-sucursal">Sucursal</label>
                        <input type="number" name="sucursal" id="cf-sucursal" class="form-control form-control-sm" min="1" required value="{{ old('sucursal') }}">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-nombre">Nombre</label>
                        <input type="text" name="nombre" id="cf-nombre" class="form-control form-control-sm" maxlength="80" value="{{ old('nombre') }}" placeholder="Opcional">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-hora">Hora</label>
                        <input type="text" name="hora" id="cf-hora" class="form-control form-control-sm" maxlength="8" required value="{{ old('hora', now()->format('H:i:s')) }}" placeholder="HH:MM:SS">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-tipo">Tipo</label>
                        <select name="tipo" id="cf-tipo" class="form-control form-control-sm" required>
                            <option value="Z" @selected(old('tipo', 'Z') === 'Z')>Z</option>
                            <option value="X" @selected(old('tipo') === 'X')>X</option>
                        </select>
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-numero">Número</label>
                        <input type="number" name="numero" id="cf-numero" class="form-control form-control-sm" min="1" required value="{{ old('numero') }}">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-venta">Venta</label>
                        <input type="number" name="venta" id="cf-venta" class="form-control form-control-sm" step="0.01" required value="{{ old('venta', '0') }}">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-credito">Notas de crédito</label>
                        <input type="number" name="credito" id="cf-credito" class="form-control form-control-sm" step="0.01" value="{{ old('credito', '0') }}">
                    </div>
                    <div class="col-auto mb-2">
                        <label class="small mb-0" for="cf-ticket">Último ticket</label>
                        <input type="number" name="ult_ticket" id="cf-ticket" class="form-control form-control-sm" min="0" value="{{ old('ult_ticket', '0') }}">
                    </div>
                    <div class="col-auto mb-2">
                        <button type="submit" class="btn btn-outline-primary btn-sm">Agregar</button>
                    </div>
                </form>
            </details>
        @endif
        @if ($filasFiscales === [])
            <p class="text-muted mb-0">No hay cierres pendientes para esta fecha.</p>
        @else
            @if ($editable)
                <input type="hidden" name="cierre_fiscal_panel" value="1">
            @endif
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-cierres-fiscales">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            @if ($editable)
                                <th style="width:36px;"></th>
                            @endif
                            <th>Sucursal</th>
                            <th>Hora</th>
                            <th>Tipo</th>
                            <th class="text-right">Número</th>
                            <th class="text-right">Venta</th>
                            <th class="text-right">Notas de crédito</th>
                            <th class="text-right">Último ticket</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($filasFiscales as $fila)
                            @php
                                $marcado = is_array($marcados) ? in_array($fila['clave'], $marcados, true) : $fila['elegido'];
                            @endphp
                            <tr class="js-cierre-fiscal"
                                data-tipo="{{ $fila['tipo'] }}"
                                data-numero="{{ $fila['numero'] }}"
                                data-venta="{{ $fila['venta'] }}"
                                data-credito="{{ $fila['credito'] }}"
                                data-ticket="{{ $fila['ult_ticket'] }}">
                                @if ($editable)
                                    <td class="text-center">
                                        <input type="checkbox" name="cierre_fiscal[]" value="{{ $fila['clave'] }}" @checked($marcado)>
                                    </td>
                                @endif
                                <td>{{ $fila['sucursal'] }} {{ $fila['nombre'] }}@if (($fila['fuente'] ?? '') === 'anita') <span class="badge badge-secondary">Anita</span>@endif</td>
                                <td>{{ $fila['hora'] }}</td>
                                <td>{{ $fila['tipo'] }}</td>
                                <td class="text-right">{{ $fila['numero'] }}</td>
                                <td class="text-right">{{ number_format($fila['venta'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($fila['credito'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ $fila['ult_ticket'] > 0 ? $fila['ult_ticket'] : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@if ($editable && ($pxsolCodigos ?? []) !== [])
<script>
(function () {
    var boton = document.getElementById('btn-traer-pxsol');
    if (!boton) {
        return;
    }
    var token = document.querySelector('meta[name="csrf-token"]');
    boton.addEventListener('click', function () {
        boton.disabled = true;
        fetch(@json(route('pxsol_tesoreria_crown_movimiento', $comprobante->id)), {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function (respuesta) {
            return respuesta.json().then(function (datos) {
                return { ok: respuesta.ok, datos: datos };
            });
        }).then(function (resultado) {
            var aviso = document.getElementById('pxsol-aviso');
            if (!resultado.ok || !resultado.datos.ok) {
                if (aviso) {
                    aviso.textContent = (resultado.datos && resultado.datos.mensaje) ? resultado.datos.mensaje : 'PXSol no respondió.';
                }
                return;
            }
            function poner(id, valor) {
                var campo = document.getElementById(id);
                if (campo) {
                    campo.value = valor;
                }
            }
            poner('medio-total_x', resultado.datos.facturado);
            poner('medio-total_z', resultado.datos.facturado);
            poner('medio-total_nc', resultado.datos.credito);
            poner('pxsol_aplicado', '1');
            poner('pxsol_iva_facturas', resultado.datos.iva_facturas);
            poner('pxsol_iva_nc', resultado.datos.iva_nc);
            if (aviso) {
                aviso.textContent = 'PXSol de ' + resultado.datos.desde + ' a ' + resultado.datos.hasta
                    + '. Facturado y Z: ' + resultado.datos.facturado
                    + '. Notas de crédito: ' + resultado.datos.credito
                    + '. IVA facturas: ' + resultado.datos.iva_facturas
                    + '. IVA notas de crédito: ' + resultado.datos.iva_nc
                    + '. Se guardan con el borrador.';
            }
        }).catch(function () {
            var aviso = document.getElementById('pxsol-aviso');
            if (aviso) {
                aviso.textContent = 'PXSol no respondió.';
            }
        }).then(function () {
            boton.disabled = false;
        });
    });
})();
</script>
@endif
@if ($editable && $filasFiscales !== [])
<script>
(function () {
    var boton = document.getElementById('btn-aplicar-cierre-fiscal');
    if (!boton) {
        return;
    }
    function poner(id, valor) {
        var campo = document.getElementById(id);
        if (!campo) {
            return;
        }
        campo.value = valor === '' ? '' : String(valor);
    }
    boton.addEventListener('click', function () {
        var totalX = 0;
        var totalZ = 0;
        var creditoX = 0;
        var creditoZ = 0;
        var ticket = 0;
        var numerosZ = [];
        var numerosX = [];
        var hayZ = false;
        document.querySelectorAll('#tabla-cierres-fiscales .js-cierre-fiscal').forEach(function (fila) {
            var tilde = fila.querySelector('input[type="checkbox"]');
            if (!tilde || !tilde.checked) {
                return;
            }
            var venta = parseFloat(fila.getAttribute('data-venta')) || 0;
            var credito = parseFloat(fila.getAttribute('data-credito')) || 0;
            var ultimo = parseInt(fila.getAttribute('data-ticket'), 10) || 0;
            if (ultimo > ticket) {
                ticket = ultimo;
            }
            if (fila.getAttribute('data-tipo') === 'Z') {
                hayZ = true;
                totalZ += venta;
                creditoZ += credito;
                numerosZ.push(fila.getAttribute('data-numero'));
            } else {
                totalX += venta;
                creditoX += credito;
                numerosX.push(fila.getAttribute('data-numero'));
            }
        });
        var numeros = (hayZ ? numerosZ : numerosX).join(',');
        poner('medio-total_x', totalX.toFixed(2));
        poner('medio-total_z', totalZ.toFixed(2));
        poner('medio-total_nc', (hayZ ? creditoZ : creditoX).toFixed(2));
        poner('medio-nro_z', numeros.slice(0, 20));
        poner('medio-ult_ticket', ticket > 0 ? ticket : '');
    });
})();
</script>
@endif
