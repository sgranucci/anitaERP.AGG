@php
    $pesos = function ($importe) {
        if ($importe === null || $importe === '') {
            return '';
        }

        return number_format((float) $importe, 2, ',', '.');
    };
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2">
        <h3 class="card-title mb-0">Anexo I</h3>
    </div>
    <div class="card-body pb-2">
        <p class="text-muted small mb-2">
            Día, mes y anual como el listado de Anita.
            @if ($anexo !== null && $anexo['cerrado'])
                Este día Anita lo dejó grabado.
            @else
                De este día Anita solo dejó la caja final. El resto sale de efectivo, bóveda y poder público.
            @endif
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo&amp;recalcular=1">Recalcular</a>
        </p>
        @if ($anexo === null)
            <div class="alert alert-warning mb-0">No se pudo leer Anita. {{ $anexoError }}</div>
        @else
            @foreach ($anexo['avisos'] as $aviso)
                <div class="alert alert-warning py-2">{{ $aviso }}</div>
            @endforeach
            <div class="table-responsive">
                <table class="table table-sm table-bordered mb-0" id="tabla-paginada">
                    <thead style="background:#85C1E9;color:#17202A;">
                        <tr>
                            <th>Concepto</th>
                            <th class="text-right">Día</th>
                            <th class="text-right">Mes</th>
                            <th class="text-right">Anual</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($anexo['filas'] as $fila)
                            @if ($fila['seccion'])
                                <tr style="background:#D6EAF8;font-weight:bold;">
                            @elseif ($fila['total'])
                                <tr style="font-weight:bold;">
                            @else
                                <tr>
                            @endif
                                <td>{{ $fila['concepto'] }}</td>
                                <td class="text-right">{{ $pesos($fila['dia']) }}</td>
                                <td class="text-right">{{ $pesos($fila['mes']) }}</td>
                                <td class="text-right">{{ $pesos($fila['anio']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
