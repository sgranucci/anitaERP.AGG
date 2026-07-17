@php
    use App\Support\Solicitudpago\SolicitudpagoTratamientos;
    $tratamiento = old('tratamiento', $data->tratamiento ?? SolicitudpagoTratamientos::NORMAL);
    $conceptoForma = old('concepto_forma_pago');
    if ($conceptoForma === null && isset($data) && $data->conceptos) {
        $conceptoForma = $data->conceptos->forma_pago;
    }
    $mostrarCuotas = SolicitudpagoTratamientos::usaCuotas($tratamiento) || $conceptoForma === 'CUOTAS';
@endphp
<div id="bloque-cuotas" style="{{ $mostrarCuotas ? '' : 'display:none' }}">
    <p class="text-muted mb-2">Cuotas del plan / recurrente. Pod&eacute;s cargar manualmente o importar Excel (columnas: nro, vencimiento, monto — acepta alias).</p>
    @if (isset($data))
        <form action="{{ route('importar_cuotas_solicitudpago', $data->id) }}" method="POST" enctype="multipart/form-data" class="form-inline mb-3">
            @csrf
            <input type="file" name="archivo_cuotas" class="form-control-file mr-2" accept=".xlsx,.xls,.csv" required>
            <button type="submit" class="btn btn-outline-secondary btn-sm">
                <i class="fa fa-upload"></i> Importar Excel
            </button>
        </form>
    @endif
    <div class="table-responsive">
        <table class="table table-sm table-bordered" id="solicitudpago-cuota-table">
            <thead class="thead-light">
                <tr>
                    <th style="width: 12%;">Nro</th>
                    <th style="width: 22%;">Vencimiento</th>
                    <th style="width: 22%;">Monto</th>
                    <th style="width: 34%;">SP hija</th>
                    <th style="width: 10%;"></th>
                </tr>
            </thead>
            <tbody id="tbody-solicitudpago-cuota-table">
                @php
                    $filasCuota = old('nro_cuotas') !== null
                        ? collect(old('nro_cuotas', []))->map(function ($nro, $i) {
                            return (object) [
                                'nro_cuota' => $nro,
                                'fecha_vencimiento' => old('fecha_vencimientos_cuota.'.$i),
                                'monto' => old('montos_cuota.'.$i),
                                'solicitudpago_hija_id' => old('solicitudpago_hija_ids.'.$i),
                                'hijas' => null,
                            ];
                        })
                        : collect(isset($data) ? ($data->cuotas ?? []) : []);
                @endphp
                @foreach ($filasCuota as $fila)
                    @include('solicitudpago.solicitudpago.partials.fila_cuota', ['fila' => $fila])
                @endforeach
            </tbody>
        </table>
    </div>
    @include('solicitudpago.solicitudpago.partials.template_cuota')
    <div class="row mt-2">
        <div class="col-12 text-right">
            <button type="button" id="agrega_renglon_sp_cuota" class="btn btn-outline-danger btn-sm">
                <i class="fa fa-plus"></i> Agregar cuota
            </button>
        </div>
    </div>
</div>
<div id="bloque-cuotas-aviso" class="alert alert-secondary" style="{{ $mostrarCuotas ? 'display:none' : '' }}">
    Las cuotas aplican cuando el tratamiento es Plan de pago / Recurrente, o el concepto tiene forma de pago Cuotas.
</div>
