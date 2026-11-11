@extends("theme.$theme.layout")

@section('titulo')
    Facturaci&oacute;n juego
@endsection

@section('contenido')
@php
    $puedeConfigurarJuego = can('configurar-facturacion-juego', false);
    $puedeEjecutarJuego = can('ejecutar-facturacion-juego', false);
@endphp
<div class="row">
    <div class="col-12">
        <div class="card card-info">
            <div class="card-header">
                <h3 class="card-title">Facturaci&oacute;n juego</h3>
                <div class="card-tools">
                    @include('ventas.facturacion_juego.partials.help_cuentas', [
                        'helpSalas' => $helpSalas ?? [],
                        'helpModalId' => 'modal-help-facturacion-juego-index',
                        'helpBtnClass' => 'btn btn-outline-light btn-sm',
                        'soloBoton' => true,
                    ])
                    <a href="#"
                        id="btn-ir-conciliacion-juego"
                        class="btn btn-outline-light btn-sm ml-1"
                        data-base="{{ route('facturacion_juego_conciliacion') }}"
                        title="Conciliar Flash, Ventas, Contab. ERP y Anita">
                        <i class="fa fa-balance-scale"></i> Conciliaci&oacute;n
                    </a>
                    @if ($puedeConfigurarJuego)
                        <a href="{{ route('facturacion_juego_configuracion') }}" class="btn btn-outline-light btn-sm ml-1">
                            <i class="fa fa-cogs"></i> Configuraci&oacute;n
                        </a>
                    @endif
                </div>
            </div>
            <div class="card-body">
                @include('includes.mensaje')

                <p class="text-muted mb-3">
                    Genera comprobantes internos (letra B) de mesas, tragamonedas, ruleta electr&oacute;nica,
                    Del R&iacute;o online (FOL/COL, PV 67) y Betsur (FOL/COL, PV 68)
                    a partir del Flash Crown, con asiento contable. Paridad Anita <code>p-resultado</code>.
                </p>

                <form method="get" action="{{ route('facturacion_juego') }}" class="mb-3" id="form-facturacion-juego">
                    <input type="hidden" name="consultar" value="1">
                    <div class="form-row align-items-end">
                        <div class="form-group col-md-2">
                            <label for="fecha_desde" class="control-label">Desde</label>
                            <input type="date" name="fecha_desde" id="fecha_desde" class="form-control"
                                value="{{ $fechaDesde }}">
                        </div>
                        <div class="form-group col-md-2">
                            <label for="fecha_hasta" class="control-label">Hasta</label>
                            <input type="date" name="fecha_hasta" id="fecha_hasta" class="form-control"
                                value="{{ $fechaHasta }}">
                        </div>
                        <div class="form-group col-md-5">
                            <label class="control-label d-block">Sala</label>
                            <input type="hidden" name="sala_id" id="sala_id" value="{{ (int) $salaId }}">
                            <div class="btn-group btn-group-sm flex-wrap" role="group">
                                <button type="button" class="btn {{ (int)$salaId === 0 ? 'btn-info' : 'btn-outline-info' }} js-sala-chip" data-sala="0">Todas</button>
                                @foreach ($salas as $sala)
                                    <button type="button"
                                        class="btn {{ (int)$salaId === (int)$sala->id ? 'btn-info' : 'btn-outline-info' }} js-sala-chip"
                                        data-sala="{{ $sala->id }}">{{ $sala->codigo }}</button>
                                @endforeach
                            </div>
                        </div>
                        <div class="form-group col-md-3">
                            <button type="submit" class="btn btn-info btn-sm">
                                <i class="fa fa-search"></i> Consultar
                            </button>
                            <button type="button" id="btn-ir-conciliacion-juego-form" class="btn btn-outline-info btn-sm"
                                data-base="{{ route('facturacion_juego_conciliacion') }}"
                                title="Usa las fechas y sala del formulario">
                                <i class="fa fa-balance-scale"></i> Conciliaci&oacute;n
                            </button>
                            @if ($puedeEjecutarJuego && $consultar && $listos > 0)
                                <button type="submit" form="form-ejecutar-juego" class="btn btn-primary btn-sm"
                                    onclick="return confirm('¿Generar {{ $listos }} comprobante(s) listos?');">
                                    <i class="fa fa-check"></i> Generar listos ({{ $listos }})
                                </button>
                            @endif
                        </div>
                    </div>
                </form>

                @if ($puedeEjecutarJuego)
                    <form method="post" action="{{ route('ejecutar_facturacion_juego') }}" id="form-ejecutar-juego">
                        @csrf
                        <input type="hidden" name="fecha_desde" value="{{ $fechaDesde }}">
                        <input type="hidden" name="fecha_hasta" value="{{ $fechaHasta }}">
                        <input type="hidden" name="sala_id" value="{{ $salaId }}">
                    </form>
                @endif

                @if ($consultar)
                    <div class="mb-2">
                        <span class="badge badge-info">Pendientes: {{ count($preview) }}</span>
                        <span class="badge badge-success">Listos: {{ $listos }}</span>
                        @if ($pendientesConfig > 0)
                            <span class="badge badge-warning">Sin config: {{ $pendientesConfig }}</span>
                        @endif
                        <span class="badge badge-secondary">
                            Monto abs.: {{ number_format((float)$totalMonto, 2, ',', '.') }}
                        </span>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-bordered table-striped" id="tabla-paginada">
                            <thead style="background:#85C1E9;color:#17202A;">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Sala</th>
                                    <th>Rubro</th>
                                    <th>Tipo</th>
                                    <th class="text-right">Resultado</th>
                                    <th class="text-right">Monto</th>
                                    <th>PV</th>
                                    <th>Estado</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($preview as $fila)
                                    <tr>
                                        <td>{{ \Carbon\Carbon::parse($fila['fecha'])->format('d/m/Y') }}</td>
                                        <td>{{ $fila['sala_codigo'] }} — {{ $fila['sala_nombre'] }}</td>
                                        <td>{{ $fila['rubro_label'] }}</td>
                                        <td>{{ $fila['tipo'] }} {{ $fila['letra'] }}</td>
                                        <td class="text-right">{{ number_format((float)$fila['resultado'], 2, ',', '.') }}</td>
                                        <td class="text-right">{{ number_format((float)$fila['monto'], 2, ',', '.') }}</td>
                                        <td>{{ $fila['sucursal_anita'] ?: '—' }}</td>
                                        <td>
                                            @if ($fila['listo'])
                                                <span class="text-success">Listo</span>
                                            @else
                                                <span class="text-warning" title="{{ implode(', ', $fila['faltantes']) }}">
                                                    Falta config
                                                </span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">
                                            No hay d&iacute;as pendientes de facturar en el rango.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@include('ventas.facturacion_juego.partials.help_cuentas', [
    'helpSalas' => $helpSalas ?? [],
    'helpModalId' => 'modal-help-facturacion-juego-index',
    'soloModal' => true,
])
@endsection

@section('scripts')
<script>
(function () {
    function irConciliacion(base) {
        var desde = document.getElementById('fecha_desde');
        var hasta = document.getElementById('fecha_hasta');
        var sala = document.getElementById('sala_id');
        if (!desde || !hasta || !desde.value || !hasta.value) {
            alert('Indique fecha desde y hasta.');
            return;
        }
        var url = new URL(base, window.location.origin);
        url.searchParams.set('fecha_desde', desde.value);
        url.searchParams.set('fecha_hasta', hasta.value);
        url.searchParams.set('consultar', '1');
        var salaId = sala ? (sala.value || '0') : '0';
        if (parseInt(salaId, 10) > 0) {
            url.searchParams.set('sala_id', salaId);
        } else {
            url.searchParams.delete('sala_id');
        }
        window.location.href = url.toString();
    }

    document.querySelectorAll('.js-sala-chip').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('sala_id').value = btn.getAttribute('data-sala') || '0';
            document.getElementById('form-facturacion-juego').submit();
        });
    });

    ['btn-ir-conciliacion-juego', 'btn-ir-conciliacion-juego-form'].forEach(function (id) {
        var el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('click', function (e) {
            e.preventDefault();
            irConciliacion(el.getAttribute('data-base') || el.href);
        });
    });
})();
</script>
@endsection
