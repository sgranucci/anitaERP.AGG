@php
	use App\Support\Stock\FormulaArticuloGastronomia;
	use Illuminate\Support\Facades\Schema;
	$sep = $separator ?? "\n";
	$enlaces = $enlaces ?? false;
	$gastOpc = FormulaArticuloGastronomia::opcionalesHabilitados();
	$tieneRanura = config('app.empresa') === 'FRASLE' && Schema::hasColumn('formula_articulo_hijo', 'ranura');
@endphp
@foreach ($data->formula_articulo_hijos ?? [] as $h)
	@php
		$sku = $h->articulos->sku ?? '';
		$desc = $h->articulos->descripcion ?? '';
		$subId = $h->formula_hija_id;
		$subSku = optional(optional($h->formula_hija)->articulos)->sku ?? '';
		$subDesc = optional(optional($h->formula_hija)->articulos)->descripcion ?? '';
		$fh = $h->formula_hija;
		$dep = $h->depositos ?? null;
		$depStr = $dep ? trim(($dep->codigo ?? '').' '.($dep->nombre ?? '')) : '';
		$ranVal = $tieneRanura ? ($h->ranura ?? null) : null;
	@endphp
	@if($sku !== '' || $desc !== '')
		@if($enlaces && !empty($h->articulo_id))
			<a href="{{ route('editar_articulo', ['id' => $h->articulo_id]) }}">Art. {{ $h->articulo_id }}</a>
		@else
			Art. {{ $h->articulo_id }} {{ $sku }} — {{ $desc }}
		@endif
		| Cant: {{ $h->cantidad }} | FC: {{ $h->factorcosto }}
		@if ($gastOpc)
			| Opc: {{ $h->esopcional ? 'Sí' : 'No' }}
			@if ($h->esopcional && $h->ordenopcional !== null && $h->ordenopcional !== '')
				| Ord.opc: {{ $h->ordenopcional }}
			@endif
		@endif
		@if ($depStr !== '')
			| Dep: {{ $depStr }}
		@endif
		@if ($tieneRanura && $ranVal !== null && $ranVal !== '')
			| Ranura: {{ $ranVal }}
		@endif
	@elseif($subId)
		@if($enlaces)
			<a href="{{ route('editar_formula_articulo', ['id' => $subId]) }}">Fórmula {{ $subId }}</a> (SKU {{ $subSku }})
		@else
			Fórmula {{ $subId }} (SKU {{ $subSku }})
		@endif
		@if ($subDesc !== '')
			— {{ $subDesc }}
		@endif
		| Cant: {{ $h->cantidad }} | FC: {{ $h->factorcosto }}
		@if ($gastOpc)
			| Opc: {{ $h->esopcional ? 'Sí' : 'No' }}
			@if ($h->esopcional && $h->ordenopcional !== null && $h->ordenopcional !== '')
				| Ord.opc: {{ $h->ordenopcional }}
			@endif
		@endif
		@if ($fh && trim((string) ($fh->codigo ?? '')) !== '')
			| Cód.subf: {{ $fh->codigo }}
		@endif
		@if ($fh && trim((string) ($fh->detalle ?? '')) !== '')
			| Det.subf: {{ $fh->detalle }}
		@endif
		@if ($depStr !== '')
			| Dep: {{ $depStr }}
		@endif
		@if ($tieneRanura && $ranVal !== null && $ranVal !== '')
			| Ranura: {{ $ranVal }}
		@endif
	@endif
	{!! $sep !!}
@endforeach
