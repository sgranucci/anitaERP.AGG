@php
    $arbolMovimientos = collect($arbolMovimientos ?? []);
    $spIdArbol = (int) (($data->id ?? 0) ?: 0);
    $conceptoArbol = ($data->conceptos ?? null);
@endphp
<input type="hidden" id="solicitudpago_id" value="{{ $spIdArbol > 0 ? $spIdArbol : '' }}">

@if ($conceptoArbol)
    <p class="text-muted small mb-2">
        &Aacute;rbol del concepto
        <strong>{{ $conceptoArbol->codigo ?? '' }} {{ $conceptoArbol->nombre ?? '' }}</strong>
        (firmantes en la solapa Usuarios del concepto).
        @if (can('editar-concepto-solicitud-pago', false) || can('listar-concepto-solicitud-pago', false))
            <a href="{{ route('editar_concepto_solicitudpago', ['id' => $conceptoArbol->id, 'origen' => 'modal_consulta', 'vista' => 'consulta']) }}"
               target="_blank" rel="noopener" class="text-primary">
                Abrir concepto
            </a>
        @endif
    </p>
@else
    <p class="text-muted small mb-2">Sin concepto cargado: no hay &aacute;rbol de aprobaci&oacute;n aplicable.</p>
@endif

<div id="sp-panel-ia-arbol" class="d-none mb-3"></div>

<div id="sp-detalle-nivel-actual" class="d-none alert alert-info py-2 mb-3" role="status"></div>

<h5 class="mb-2">Movimientos &aacute;rbol de aprobaci&oacute;n</h5>
<div class="table-responsive">
    <table class="table table-sm table-bordered table-striped" id="solicitudpago-arbol-table">
        <thead class="thead-light">
            <tr>
                <th style="width: 12%;">Env&iacute;o</th>
                <th style="width: 16%;">Envi&oacute;</th>
                <th style="width: 6%;">Nivel</th>
                <th style="width: 12%;">Estado</th>
                <th style="width: 12%;">Proceso</th>
                <th style="width: 18%;">Destinatario</th>
                <th>Obs.</th>
            </tr>
        </thead>
        <tbody class="container-arbol">
            @if ($spIdArbol <= 0)
                <tr>
                    <td colspan="7" class="text-center text-muted">Guarde la solicitud para disparar el &aacute;rbol del concepto.</td>
                </tr>
            @elseif ($arbolMovimientos->isEmpty())
                <tr>
                    <td colspan="7" class="text-center text-muted">Sin movimientos registrados en el &aacute;rbol.</td>
                </tr>
            @else
                @foreach ($arbolMovimientos as $mov)
                    <tr>
                        <td>{{ $mov->fechaenvio ? \Illuminate\Support\Carbon::parse($mov->fechaenvio)->format('d/m/Y H:i') : '—' }}</td>
                        <td>{{ optional($mov->enviousuarios)->nombre ?? '—' }}</td>
                        <td>{{ $mov->nivel ?? '—' }}</td>
                        <td>{{ $mov->estado ?? '—' }}</td>
                        <td>{{ $mov->fechaproceso ? \Illuminate\Support\Carbon::parse($mov->fechaproceso)->format('d/m/Y H:i') : '—' }}</td>
                        <td>{{ optional($mov->destinatariousuarios)->nombre ?? '—' }}</td>
                        <td>{{ $mov->observacion !== null && $mov->observacion !== '' ? $mov->observacion : '—' }}</td>
                    </tr>
                @endforeach
            @endif
        </tbody>
    </table>
</div>
