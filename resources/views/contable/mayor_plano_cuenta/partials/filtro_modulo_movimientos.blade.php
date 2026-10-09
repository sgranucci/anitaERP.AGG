@php
    $moduloActual = \App\Support\Contable\MayorPlanoCuentaListadoFiltros::moduloMovimientos($filtros ?? []);
    $etiquetaModulo = \App\Support\Contable\MayorPlanoCuenta\MayorPlanoCuentaModuloFiltroSupport::etiqueta($moduloActual);
    $opcionesModulo = [
        '' => ['label' => 'Todos', 'letra' => ''],
        'ventas' => ['label' => 'Ventas', 'letra' => 'V'],
        'compras' => ['label' => 'Compras', 'letra' => 'C'],
        'cuentas_pagar' => ['label' => 'Cuentas a pagar', 'letra' => 'OP'],
        'caja' => ['label' => 'Caja', 'letra' => 'T'],
    ];
@endphp
<style>
    .mpc-modulo-toggle {
        border-radius: 6px;
        font-size: 12px;
        padding: 4px 8px;
    }
    .mpc-modulo-toggle .fa-chevron-down {
        transition: transform .15s ease;
    }
    .mpc-modulo-toggle:not(.collapsed) .fa-chevron-down {
        transform: rotate(180deg);
    }
    .mpc-modulo-opciones {
        display: flex;
        flex-wrap: wrap;
        gap: 4px;
        padding-top: 6px;
    }
    .mpc-modulo-opcion {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        margin: 0;
        padding: 2px 8px;
        border: 1px solid #d5d8dc;
        border-radius: 999px;
        background: #fff;
        font-size: 12px;
        line-height: 1.4;
        cursor: pointer;
        color: #1b4f72;
    }
    .mpc-modulo-opcion input {
        position: absolute;
        opacity: 0;
        pointer-events: none;
    }
    .mpc-modulo-opcion .mpc-modulo-letra {
        font-size: 10px;
        font-weight: 700;
        letter-spacing: .02em;
        color: #2471a3;
        background: #eaf2f8;
        border-radius: 4px;
        padding: 0 4px;
    }
    .mpc-modulo-opcion.activa,
    .mpc-modulo-opcion:has(input:checked) {
        background: #d6eaf8;
        border-color: #2471a3;
        font-weight: 600;
    }
</style>
<div class="mpc-modulo-filtro mb-2">
    <button type="button"
        class="btn btn-sm btn-outline-info btn-block text-left mpc-modulo-toggle d-flex align-items-center collapsed"
        data-toggle="collapse"
        data-target="#mpc-modulo-opciones"
        aria-expanded="false"
        aria-controls="mpc-modulo-opciones">
        <i class="fa fa-sitemap mr-1"></i>
        <span>M&oacute;dulo</span>
        <span class="badge badge-pill {{ $moduloActual === '' ? 'badge-light border' : 'badge-info' }} ml-2">{{ $etiquetaModulo }}</span>
        <i class="fa fa-chevron-down ml-auto"></i>
    </button>
    <div class="collapse" id="mpc-modulo-opciones">
        <div class="mpc-modulo-opciones" role="radiogroup" aria-label="Modulo de movimientos">
            @foreach ($opcionesModulo as $valor => $opcion)
                <label class="mpc-modulo-opcion{{ $moduloActual === $valor ? ' activa' : '' }}">
                    <input type="radio" name="modulo_movimientos" value="{{ $valor }}" @checked($moduloActual === $valor)>
                    <span>{{ $opcion['label'] }}</span>
                    @if ($opcion['letra'] !== '')
                        <span class="mpc-modulo-letra">{{ $opcion['letra'] }}</span>
                    @endif
                </label>
            @endforeach
        </div>
        <small class="text-muted d-block mt-1">
            Subdiario Anita: V ventas, C compras (facturas de proveedor), T caja.
            Cuentas a pagar toma &oacute;rdenes de pago (OPP, OPA, APA).
        </small>
    </div>
</div>
