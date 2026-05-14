@php
    use App\Support\Stock\FormulaArticuloGastronomia;
    $formulaGastronomiaOpcional = FormulaArticuloGastronomia::opcionalesHabilitados();
@endphp
<div class="table-responsive">
    <table class="table table-sm table-bordered">
        <tbody>
            <tr><th style="width:18%">ID f&oacute;rmula</th><td><a href="{{ route('editar_formula_articulo', ['id' => $data->id]) }}" target="_blank" rel="noopener">{{ $data->id }}</a></td></tr>
            <tr><th>C&oacute;digo f&oacute;rmula</th><td><span class="text-monospace">@if(! empty($data->codigo)){{ $data->codigo }}@else<span class="text-muted">&mdash;</span>@endif</span></td></tr>
            <tr><th>Art&iacute;culo cabecera</th><td>
                @if ($data->articulo_id)
                    <a href="{{ route('editar_articulo', ['id' => $data->articulo_id]) }}" target="_blank" rel="noopener">{{ optional($data->articulos)->sku ?? '' }}</a>
                    — {{ optional($data->articulos)->descripcion ?? '' }}
                @else
                    <span class="text-muted">Sin art&iacute;culo asignado en cabecera</span>
                @endif
            </td></tr>
            <tr><th>Cantidad unidad</th><td>{{ number_format((float) ($data->cantidadunidad ?? 0), 2, ',', '.') }}</td></tr>
            <tr><th>Estado</th><td>{{ $data->estado }}</td></tr>
            <tr><th>Detalle</th><td>{{ $data->detalle }}</td></tr>
        </tbody>
    </table>
    <h6>&Iacute;tems</h6>
    <table class="table table-striped table-bordered">
        <thead>
            <tr>
                <th>Componente / subf&oacute;rmula</th>
                <th class="text-right">Cantidad</th>
                <th class="text-right">Factor costo</th>
                @if ($formulaGastronomiaOpcional)
                <th>Opcional</th>
                <th>Orden opc.</th>
                @endif
                <th>Dep&oacute;sito</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($data->formula_articulo_hijos ?? [] as $h)
            <tr>
                <td>
                    @if($h->articulo_id)
                        <a href="{{ route('editar_articulo', ['id' => $h->articulo_id]) }}" target="_blank" rel="noopener">{{ $h->articulos->sku ?? '' }}</a>
                        <small>{{ $h->articulos->descripcion ?? '' }}</small>
                    @elseif($h->formula_hija_id)
                        <a href="{{ route('editar_formula_articulo', ['id' => $h->formula_hija_id]) }}" target="_blank" rel="noopener">F&oacute;rmula #{{ $h->formula_hija_id }}</a>
                        <small>{{ $h->formula_hija->articulos->sku ?? '' }}</small>
                    @endif
                </td>
                <td class="text-right">{{ number_format((float) $h->cantidad, 2, ',', '.') }}</td>
                <td class="text-right">{{ number_format((float) $h->factorcosto, 2, ',', '.') }}</td>
                @if ($formulaGastronomiaOpcional)
                <td>{{ $h->esopcional ? 'Sí' : 'No' }}</td>
                <td>{{ $h->esopcional ? ($h->ordenopcional ?? '—') : '—' }}</td>
                @endif
                <td><small>{{ $h->depositos?->nombre ?? '' }}</small></td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
