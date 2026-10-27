@php
    use App\Models\Ventas\Cliente;
    use App\Support\Ventas\ClienteAnitaNumeracionSupport;
    use App\Support\Ventas\ClienteCodigoEmpleadoBierzoSupport;
    use App\Support\Ventas\ClienteCodigoSecuenciaSupport;

    $esCliente = isset($data) && $data instanceof Cliente;
    $codigoActual = trim((string) ($esCliente ? ($data->codigo ?? '') : ''));
    $codigoCliente = old('codigo', $codigoActual !== '' ? $codigoActual : ($data->codigo ?? ''));
    $codigoEditable = config('app.empresa') === 'INTERFORMING';
    $esAlta = ! $esCliente;
    $mostrarSecuencia = ClienteCodigoSecuenciaSupport::muestraSelector($codigoActual, $esAlta);
    $secuenciaSel = old('secuencia_codigo');
    if ($secuenciaSel === null) {
        $secuenciaSel = ($esCliente && ClienteCodigoSecuenciaSupport::esGastronomia($codigoActual))
            ? ClienteCodigoSecuenciaSupport::GASTRONOMIA
            : ClienteCodigoSecuenciaSupport::ADMINISTRACION;
    }
    $proximoGastro = $mostrarSecuencia ? ClienteCodigoSecuenciaSupport::proximoGastronomia() : '';
    $actualEsGastro = $esCliente && ClienteCodigoSecuenciaSupport::esGastronomia($codigoActual);
    if ($mostrarSecuencia && $secuenciaSel === ClienteCodigoSecuenciaSupport::GASTRONOMIA && ! $actualEsGastro) {
        $codigoCliente = $proximoGastro;
    }
    if ($esAlta && $mostrarSecuencia && $secuenciaSel !== ClienteCodigoSecuenciaSupport::GASTRONOMIA) {
        $codigoCliente = '';
    }

    $mostrarSecuenciaEmpleado = ClienteCodigoEmpleadoBierzoSupport::muestraSelector($esAlta);
    $secuenciaEmpleadoSel = old('secuencia_codigo', ClienteCodigoEmpleadoBierzoSupport::CLIENTE);
    if (! in_array($secuenciaEmpleadoSel, [
        ClienteCodigoEmpleadoBierzoSupport::CLIENTE,
        ClienteCodigoEmpleadoBierzoSupport::EMPLEADO,
    ], true)) {
        $secuenciaEmpleadoSel = ClienteCodigoEmpleadoBierzoSupport::CLIENTE;
    }
    $proximoEmpleado = $mostrarSecuenciaEmpleado ? ClienteCodigoEmpleadoBierzoSupport::proximoVisible() : '';
    $desdeEmpleado = ClienteCodigoEmpleadoBierzoSupport::desde();
    $hastaEmpleado = ClienteCodigoEmpleadoBierzoSupport::hasta();
    $proximoCliente = ($esAlta && ClienteAnitaNumeracionSupport::estaHabilitada())
        ? ClienteAnitaNumeracionSupport::proximoCodigoVisible()
        : '';
    $serieEmpleado = $mostrarSecuenciaEmpleado
        && $secuenciaEmpleadoSel === ClienteCodigoEmpleadoBierzoSupport::EMPLEADO;
    if ($esAlta && $serieEmpleado && $proximoEmpleado !== '') {
        $codigoCliente = $proximoEmpleado;
    } elseif ($esAlta && $proximoCliente !== '') {
        $codigoCliente = $proximoCliente;
    }
    $codigoIndicativo = $esAlta && ($proximoCliente !== '' || $proximoEmpleado !== '');
@endphp
@if ($mostrarSecuencia)
    <div class="d-inline-flex align-items-center ml-2 mr-1">
        <label for="secuencia_codigo" class="mb-0 mr-2 small font-weight-bold text-white">Secuencia</label>
        <select name="secuencia_codigo"
                id="secuencia_codigo"
                form="form-general"
                class="form-control form-control-sm"
                style="width: auto; min-width: 11rem;"
                title="Administración sigue ERP. Gastronomía sigue 7000, 7001…"
                data-proximo-gastro="{{ $proximoGastro }}"
                data-codigo-actual="{{ $codigoActual }}"
                data-es-alta="{{ $esAlta ? '1' : '0' }}"
                data-actual-gastro="{{ $actualEsGastro ? '1' : '0' }}">
            <option value="{{ ClienteCodigoSecuenciaSupport::ADMINISTRACION }}"
                @if ($actualEsGastro)
                    disabled
                @endif
                @if ($secuenciaSel === ClienteCodigoSecuenciaSupport::ADMINISTRACION)
                    selected
                @endif
            >
                Administración (ERP)
            </option>
            <option value="{{ ClienteCodigoSecuenciaSupport::GASTRONOMIA }}"
                @if ($secuenciaSel === ClienteCodigoSecuenciaSupport::GASTRONOMIA)
                    selected
                @endif
            >
                Gastronomía (7000+)
            </option>
        </select>
    </div>
@endif
@if ($mostrarSecuenciaEmpleado)
    <div class="d-inline-flex align-items-center ml-3">
        <label for="secuencia_codigo" class="mb-0 mr-1 small font-weight-normal text-white" style="opacity:.85;">Secuencia</label>
        <select name="secuencia_codigo"
                id="secuencia_codigo"
                form="form-general"
                class="form-control form-control-sm"
                style="width: auto; min-width: 6.5rem;"
                title="Cliente: numeración habitual, próximo {{ $proximoCliente }}. Empleado: {{ $desdeEmpleado }} a {{ $hastaEmpleado }}, próximo {{ $proximoEmpleado }}. Se confirma al guardar."
                data-proximo-empleado="{{ $proximoEmpleado }}"
                data-proximo-cliente="{{ $proximoCliente }}">
            <option value="{{ ClienteCodigoEmpleadoBierzoSupport::CLIENTE }}"
                @if ($secuenciaEmpleadoSel === ClienteCodigoEmpleadoBierzoSupport::CLIENTE)
                    selected
                @endif
            >
                Cliente
            </option>
            <option value="{{ ClienteCodigoEmpleadoBierzoSupport::EMPLEADO }}"
                @if ($secuenciaEmpleadoSel === ClienteCodigoEmpleadoBierzoSupport::EMPLEADO)
                    selected
                @endif
            >
                Empleado
            </option>
        </select>
    </div>
@endif
<div class="d-inline-flex align-items-center ml-2">
    <label for="codigo" class="mb-0 mr-1 small font-weight-normal text-white" style="opacity:.85;">Código</label>
    <input type="text"
           name="codigo"
           id="codigo"
           form="form-general"
           class="form-control form-control-sm"
           style="width: 5.5rem;"
           value="{{ $codigoCliente }}"
           placeholder="{{ $codigoEditable || $codigoIndicativo ? '' : 'Automático' }}"
           autocomplete="off"
           title="{{ $codigoIndicativo ? 'Indicativo. Se confirma al guardar.' : 'Código Anita del cliente' }}"
           @if (! $codigoEditable)
               readonly
           @endif
           @if ($codigoEditable)
               required
           @endif
    >
</div>
@if ($mostrarSecuenciaEmpleado)
    <script>
    (function () {
        var sel = document.getElementById('secuencia_codigo');
        var cod = document.getElementById('codigo');
        if (!sel || !cod) {
            return;
        }
        var proximoEmpleado = sel.getAttribute('data-proximo-empleado') || '';
        var proximoCliente = sel.getAttribute('data-proximo-cliente') || '';
        sel.addEventListener('change', function () {
            if (sel.value === 'empleado') {
                cod.value = proximoEmpleado;
                return;
            }
            cod.value = proximoCliente;
        });
    })();
    </script>
@endif
@if ($mostrarSecuencia)
    <span class="d-block w-100 small font-weight-normal mt-1" style="opacity:.95;">
        @if ($actualEsGastro)
            Este cliente está en la serie de gastronomía. El próximo libre es {{ $proximoGastro }}.
        @else
            Administración sigue ERP. Gastronomía sigue 7000, 7001… Al elegir Gastronomía el código pasa a {{ $proximoGastro }}.
        @endif
    </span>
    <script>
    (function () {
        var sel = document.getElementById('secuencia_codigo');
        var cod = document.getElementById('codigo');
        if (!sel || !cod) {
            return;
        }
        var proximoGastro = sel.getAttribute('data-proximo-gastro') || '';
        var codigoActual = sel.getAttribute('data-codigo-actual') || '';
        var esAlta = sel.getAttribute('data-es-alta') === '1';
        var actualGastro = sel.getAttribute('data-actual-gastro') === '1';
        sel.addEventListener('change', function () {
            if (sel.value === 'gastronomia') {
                cod.value = actualGastro ? codigoActual : proximoGastro;
                return;
            }
            cod.value = esAlta ? '' : codigoActual;
        });
    })();
    </script>
@endif
