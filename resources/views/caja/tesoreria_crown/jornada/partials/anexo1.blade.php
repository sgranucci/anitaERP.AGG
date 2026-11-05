@php
    $pesos = function ($importe) {
        if ($importe === null || $importe === '') {
            return '';
        }

        return number_format((float) $importe, 2, ',', '.');
    };
    $variante = (string) ($anexoVariante ?? 'clasico');
    $urlAnexo = function (string $formatoAnexo) use ($jornada): string {
        return route('ver_tesoreria_crown_jornada', $jornada->id).'?'.http_build_query([
            'panel' => 'anexo',
            'formato_anexo' => $formatoAnexo,
        ]);
    };
@endphp
<div class="card card-outline card-info mb-3">
    <div class="card-header py-2 d-flex flex-wrap align-items-center">
        <h3 class="card-title mb-0 mr-auto"><i class="fa fa-file-text-o"></i> Anexo I</h3>
        <div class="btn-group btn-group-sm" role="group" aria-label="Formato del anexo">
            <a href="{{ $urlAnexo('clasico') }}" class="btn {{ $variante === 'clasico' ? 'btn-info' : 'btn-outline-info' }}" title="Como l-anexo1.c">
                Día / mes / anual
            </a>
            <a href="{{ $urlAnexo('nuevo') }}" class="btn {{ $variante === 'nuevo' ? 'btn-info' : 'btn-outline-info' }}" title="Como l-anexo1new.c">
                Débito / crédito / saldo
            </a>
        </div>
    </div>
    <div class="card-body pb-2">
        @include('caja.tesoreria_crown.jornada.partials.anexos_tabs')
        <p class="text-muted small mb-2">
            @if ($variante === 'nuevo')
                Formato de <code>l-anexo1new.c</code>: concepto, fecha, débito, crédito y saldo corrido.
            @else
                Formato de <code>l-anexo1.c</code>: concepto con día, mes y anual (incluye apuestas deportivas como ingreso).
            @endif
            @if ($anexo !== null && $anexo['cerrado'])
                Este día Anita lo dejó grabado.
            @else
                De este día Anita solo dejó la caja final. El resto sale de efectivo, bóveda y poder público.
            @endif
            <a href="{{ route('ver_tesoreria_crown_jornada', $jornada->id) }}?panel=anexo&amp;formato_anexo={{ $variante }}&amp;recalcular=1">Recalcular</a>
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
                        @if ($variante === 'nuevo')
                            <tr>
                                <th>Concepto</th>
                                <th>Fecha</th>
                                <th class="text-right">Débito</th>
                                <th class="text-right">Crédito</th>
                                <th class="text-right">Saldo</th>
                            </tr>
                        @else
                            <tr>
                                <th>Concepto</th>
                                <th class="text-right">Día</th>
                                <th class="text-right">Mes</th>
                                <th class="text-right">Anual</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        @foreach ($anexo['filas'] as $fila)
                            @if ($fila['seccion'])
                                <tr style="background:#D6EAF8;font-weight:bold;">
                            @elseif (! empty($fila['total']))
                                <tr style="font-weight:bold;">
                            @else
                                <tr>
                            @endif
                                <td>{{ $fila['concepto'] }}</td>
                                @if ($variante === 'nuevo')
                                    <td>{{ $fila['fecha'] ?? '' }}</td>
                                    <td class="text-right">{{ $pesos($fila['debito'] ?? null) }}</td>
                                    <td class="text-right">{{ $pesos($fila['credito'] ?? null) }}</td>
                                    <td class="text-right">{{ $pesos($fila['saldo'] ?? null) }}</td>
                                @else
                                    <td class="text-right">{{ $pesos($fila['dia']) }}</td>
                                    <td class="text-right">{{ $pesos($fila['mes']) }}</td>
                                    <td class="text-right">{{ $pesos($fila['anio']) }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
