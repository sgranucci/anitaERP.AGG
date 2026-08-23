@php
    $oc = $ordencompra ?? $data?->ordencompras ?? null;
    $colLabel = $col_label ?? 'col-lg-3';
    $colInput = $col_input ?? 'col-lg-6';
@endphp
@if ($oc && $oc->id)
    @php
        $fechaOc = $oc->fecha ?? null;
        if (is_string($fechaOc) && $fechaOc !== '') {
            try {
                $fechaOc = \Carbon\Carbon::parse($fechaOc);
            } catch (\Throwable $e) {
                $fechaOc = null;
            }
        }
        $puedeVerOc = can('editar-ordencompra', false) || can('listar-ordencompra', false);
    @endphp
    <div class="form-group row ingreso-dato-ordencompra">
        <label class="{{ $colLabel }} control-label text-right pr-2">Orden de compra</label>
        <div class="{{ $colInput }}">
            <div class="border rounded px-2 py-2 bg-light">
                <div>
                    <strong>OC {{ $oc->numeroordencompra }}</strong>
                    @if ($puedeVerOc)
                        <a href="{{ route('editar_ordencompra', ['id' => $oc->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
                           class="text-primary ml-1" target="_blank" rel="noopener">Abrir</a>
                    @endif
                </div>
                <div class="small text-muted">
                    @if ($fechaOc)
                        Fecha {{ $fechaOc->format('d/m/Y') }}
                    @endif
                    @if (!empty($oc->estadoordencompra))
                        · {{ $oc->estadoordencompra }}
                    @endif
                    @if (!empty($oc->es_contrato))
                        · Contrato
                    @endif
                    @if (!empty($oc->contrato_exige_ingresos))
                        · Exige ingresos
                    @endif
                </div>
            </div>
        </div>
    </div>
@endif
