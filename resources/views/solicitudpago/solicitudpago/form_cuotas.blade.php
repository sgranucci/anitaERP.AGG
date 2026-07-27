@php
    use App\Support\Solicitudpago\ConceptoSolicitudpagoFormaPago;
    use App\Support\Solicitudpago\SolicitudpagoTratamientos;
    $tratamiento = old('tratamiento', $data->tratamiento ?? SolicitudpagoTratamientos::NORMAL);
    $conceptoForma = old('concepto_forma_pago');
    if ($conceptoForma === null && isset($data) && $data->conceptos) {
        $conceptoForma = $data->conceptos->forma_pago;
    }
    $conceptoIdForm = (int) old('concepto_solicitudpago_id', $data->concepto_solicitudpago_id ?? 0);
    $madreIdForm = (int) old('solicitudpago_madre_id', $data->solicitudpago_madre_id ?? 0);
    $esHijaSp = $madreIdForm > 0;
    $mostrarCuotas = ConceptoSolicitudpagoFormaPago::muestraBloqueCuotas(
        $conceptoForma,
        $tratamiento,
        $conceptoIdForm > 0,
        $madreIdForm
    );
@endphp
@if ($esHijaSp)
<div class="alert alert-info mb-0">
    <i class="fa fa-link"></i>
    Esta solicitud es <strong>hija</strong> de un plan: no lleva cuotas propias.
    El plan se consulta y edita en la SP madre
    @if (isset($data) && ($data->madre ?? null))
        <a href="{{ route('editar_solicitudpago', ['id' => $data->madre->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
           class="font-weight-bold" target="_blank" rel="noopener">#{{ $data->madre->codigo }}</a>.
    @else
        .
    @endif
</div>
@endif
<div id="bloque-cuotas" style="{{ $mostrarCuotas ? '' : 'display:none' }}">
    <p class="text-muted mb-2">Cuotas del plan / recurrente. Pod&eacute;s cargar manualmente o importar Excel (columnas: nro, vencimiento, monto — acepta alias).</p>
    @if (isset($data))
        {{-- Fuera del form-general: un <form> anidado rompe el HTML y el required del archivo bloqueaba el grabar --}}
        <div class="form-inline mb-3" id="sp-importar-cuotas-wrap">
            <input type="file" id="archivo_cuotas_import" class="form-control-file mr-2" accept=".xlsx,.xls,.csv">
            <button type="button" id="btn-importar-cuotas-sp" class="btn btn-outline-secondary btn-sm"
                    data-url="{{ route('importar_cuotas_solicitudpago', $data->id) }}">
                <i class="fa fa-upload"></i> Importar Excel
            </button>
        </div>
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
                        : collect(($mostrarCuotas && isset($data)) ? ($data->cuotas ?? []) : []);
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
<div id="bloque-cuotas-aviso" class="alert alert-secondary" style="{{ ($mostrarCuotas || $esHijaSp) ? 'display:none' : '' }}">
    Las cuotas solo son obligatorias si el <strong>concepto</strong> tiene forma de pago &quot;Cuotas&quot;
    y la solicitud es la madre del plan. Las SP hijas no cargan cuotas (aunque el concepto sea de cuotas).
</div>
