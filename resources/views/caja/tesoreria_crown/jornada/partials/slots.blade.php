@php
    $resumenSlots = $resumenSlots ?? ['drops' => 0, 'drop_total' => 0, 'recuentos' => 0, 'recuento_total' => 0];
    $textoImporte = function (float $monto): string {
        return number_format($monto, 2, ',', '.');
    };
    $urlSlot = function (?int $id = null) use ($jornada): string {
        $query = ['panel' => 'slots'];
        $texto = trim((string) request('q', ''));
        if ($texto !== '') {
            $query['q'] = $texto;
        }
        if ($id !== null) {
            $query['slot'] = $id;
        }

        return route('ver_tesoreria_crown_jornada', $jornada->id).'?'.http_build_query($query);
    };
    $puedeCrearSlot = $jornada->estaAbierta() && can('crear-tesoreria-crown-slot', false);
    $puedeEliminarBorrador = $jornada->estaAbierta() && can('eliminar-borrador-tesoreria-crown-slot', false);
@endphp

<div class="d-flex flex-wrap align-items-center mb-3">
    <div class="border rounded px-3 py-2 mr-2 mb-2">
        <div class="text-muted small">Drops</div>
        <strong>{{ $resumenSlots['drops'] }}</strong>
        <span class="text-muted">· {{ $textoImporte((float) $resumenSlots['drop_total']) }}</span>
    </div>
    <div class="border rounded px-3 py-2 mr-2 mb-2">
        <div class="text-muted small">Recuentos</div>
        <strong>{{ $resumenSlots['recuentos'] }}</strong>
        <span class="text-muted">· {{ $textoImporte((float) $resumenSlots['recuento_total']) }}</span>
    </div>
    @if ($puedeCrearSlot)
        <a href="{{ route('nueva_tesoreria_crown_slot', ['jornada_id' => $jornada->id]) }}" class="btn btn-primary btn-sm ml-auto mb-2">
            <i class="fa fa-plus"></i> Nuevo recuento
        </a>
    @endif
</div>

<form method="get" action="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}" class="form-inline mb-3">
    <input type="hidden" name="panel" value="slots">
    <label for="q-slot" class="mr-2">Buscar</label>
    <input type="text" name="q" id="q-slot" class="form-control form-control-sm mr-2" value="{{ request('q') }}" placeholder="Máquina, nombre o número">
    <button type="submit" class="btn btn-outline-info btn-sm">Buscar</button>
</form>

@if ($slotDetalle)
    <div class="card card-outline card-info mb-3">
        <div class="card-header d-flex flex-wrap align-items-center">
            <h3 class="card-title mb-0 mr-auto">
                {{ \App\Models\Caja\TesoreriaCrownSlot::tipo($slotDetalle->tipo) }}
                {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($slotDetalle->numero) }}
                · {{ $slotDetalle->maquina }} {{ $slotDetalle->maquina_nombre }}
            </h3>
            <a href="{{ route('editar_tesoreria_crown_slot', $slotDetalle->id) }}" class="btn-accion-tabla tooltipsC mr-2" title="{{ $slotDetalle->esBorrador() ? 'Editar' : 'Consultar' }}">
                <i class="fa fa-edit"></i>
            </a>
            <a href="{{ $urlSlot() }}" class="small">Ver el día</a>
        </div>
        <div class="card-body p-0">
            <table class="table table-sm table-bordered mb-0">
                <thead style="background:#85C1E9;color:#17202A;">
                    <tr>
                        <th>Denominación</th>
                        <th class="text-right">Cantidad</th>
                        <th class="text-right">Importe</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($slotDetalle->lineas as $linea)
                        <tr>
                            <td>{{ $linea->valor->tipoValor->codigo ?? '' }} {{ number_format((float) $linea->valor->denominacion, 0, ',', '.') }}</td>
                            <td class="text-right">{{ number_format((float) $linea->cantidad, 0, ',', '.') }}</td>
                            <td class="text-right">{{ $textoImporte((float) $linea->cantidad * (float) $linea->valor->denominacion) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="3" class="text-muted">Sin billetes cargados.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endif

<div class="table-responsive">
    <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
        <thead style="background:#85C1E9;color:#17202A;">
            <tr>
                <th>Número</th>
                <th>Máquina</th>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Hora</th>
                <th class="text-right">Total</th>
                <th class="text-left" style="width:90px;">Acciones</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($slots as $fila)
                <tr>
                    <td>
                        <a class="text-primary" href="{{ route('editar_tesoreria_crown_slot', $fila->id) }}">
                            {{ \App\Support\Caja\TesoreriaCrownNumeradorSupport::visible($fila->numero) }}
                        </a>
                    </td>
                    <td>{{ $fila->maquina }}</td>
                    <td>{{ $fila->maquina_nombre }}</td>
                    <td>{{ \App\Models\Caja\TesoreriaCrownSlot::tipo($fila->tipo) }}</td>
                    <td>{{ \App\Models\Caja\TesoreriaCrownSlot::estado($fila->estado) }}</td>
                    <td>{{ $fila->hora }}</td>
                    <td class="text-right">{{ $textoImporte((float) $fila->total) }}</td>
                    <td class="text-left text-nowrap">
                        <a href="{{ route('editar_tesoreria_crown_slot', $fila->id) }}" class="btn-accion-tabla tooltipsC" title="{{ $fila->esBorrador() ? 'Editar' : 'Consultar' }}">
                            <i class="fa fa-edit"></i>
                        </a>
                        @if ($puedeEliminarBorrador && $fila->esBorrador())
                            <form method="POST" action="{{ route('eliminar_tesoreria_crown_slot', $fila->id) }}" class="d-inline" onsubmit="return confirm('¿Eliminar este borrador?');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-accion-tabla tooltipsC" title="Eliminar borrador">
                                    <i class="fa fa-times-circle text-danger"></i>
                                </button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="text-muted">Esta jornada no tiene drop ni recuento de slots.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
