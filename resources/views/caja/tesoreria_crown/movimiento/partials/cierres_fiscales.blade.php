@php
    $filasFiscales = $cierresFiscales['filas'] ?? [];
    $errorFiscal = $cierresFiscales['error'] ?? null;
    $marcados = old('cierre_fiscal');
@endphp
<div class="card card-outline card-info mt-3 mb-0">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h3 class="card-title mb-0">Cierres de ventas</h3>
        @if ($editable && $filasFiscales !== [])
            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-aplicar-cierre-fiscal">Aplicar a la rendición</button>
        @endif
    </div>
    <div class="card-body pb-2">
        <p class="text-muted small mb-3">Pendientes de {{ $comprobante->jornada->fecha->format('d/m/Y') }} en el cierre de facturación. El Z es el cierre fiscal. El X es el informe que la rendición de hotel deja rendido. No se modifica el cierre en Anita.</p>
        @if ($errorFiscal)
            <div class="alert alert-warning">No se pudieron leer los cierres de ventas. {{ $errorFiscal }}</div>
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
                            <th class="text-right">IVA</th>
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
                                data-iva="{{ $fila['iva'] }}"
                                data-iva-credito="{{ $fila['iva_credito'] }}"
                                data-ticket="{{ $fila['ult_ticket'] }}">
                                @if ($editable)
                                    <td class="text-center">
                                        <input type="checkbox" name="cierre_fiscal[]" value="{{ $fila['clave'] }}" @checked($marcado)>
                                    </td>
                                @endif
                                <td>{{ $fila['sucursal'] }} {{ $fila['nombre'] }}</td>
                                <td>{{ $fila['hora'] }}</td>
                                <td>{{ $fila['tipo'] }}</td>
                                <td class="text-right">{{ $fila['numero'] }}</td>
                                <td class="text-right">{{ number_format($fila['venta'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($fila['credito'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ number_format($fila['iva'], 2, ',', '.') }}</td>
                                <td class="text-right">{{ $fila['ult_ticket'] > 0 ? $fila['ult_ticket'] : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
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
        var iva = 0;
        var ivaNc = 0;
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
            var ivaFila = parseFloat(fila.getAttribute('data-iva')) || 0;
            var ivaCredito = parseFloat(fila.getAttribute('data-iva-credito')) || 0;
            var ultimo = parseInt(fila.getAttribute('data-ticket'), 10) || 0;
            iva += ivaFila;
            ivaNc += ivaCredito;
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
        poner('medio-iva_facturas', iva.toFixed(2));
        poner('medio-iva_nc', ivaNc.toFixed(2));
        poner('medio-nro_z', numeros.slice(0, 20));
        poner('medio-ult_ticket', ticket > 0 ? ticket : '');
    });
})();
</script>
@endif
