@php
	use App\Support\Stock\FormulaArticuloGastronomia;
	use App\Support\Stock\FormulaArticuloNumero;
	use Illuminate\Support\Facades\Schema;
	$sep = $separator ?? "\n";
	$enlaces = $enlaces ?? false;
	$gastOpc = FormulaArticuloGastronomia::opcionalesHabilitados();
	$tieneRanura = config('app.empresa') === 'FRASLE' && Schema::hasColumn('formula_articulo_hijo', 'ranura');
	$exportMostrarCodigo = FormulaArticuloNumero::mostrarCodigo();
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
			<a href="{{ route('editar_articulo', ['id' => $h->articulo_id, 'origen' => 'modal_consulta']) }}">{{ $sku !== '' ? $sku : 'Art. '.$h->articulo_id }}</a> — {{ $desc }}
		@else
			Art. {{ $h->articulo_id }} {{ $sku }} — {{ $desc }}
		@endif
		| Cant: {{ $h->cantidad }} | FC: {{ $h->factorcosto }}@if(isset($h->costo_ultima_compra) && $h->costo_ultima_compra !== null) | Costo: {{ number_format((float) $h->costo_ultima_compra, 2, ',', '.') }}@endif
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
		@php
			$exportSubNumero = FormulaArticuloNumero::paraFormula($fh) ?: ('#'.$subId);
		@endphp
		@if($enlaces)
			<a href="{{ route('editar_formula_articulo', ['id' => $subId]) }}">Fórmula {{ $exportSubNumero }}</a> (SKU {{ $subSku }})
		@else
			Fórmula {{ $exportSubNumero }} (SKU {{ $subSku }})
		@endif
		@if ($subDesc !== '')
			— {{ $subDesc }}
		@endif
		| Cant: {{ $h->cantidad }} | FC: {{ $h->factorcosto }}@if(isset($h->costo_ultima_compra) && $h->costo_ultima_compra !== null) | Costo: {{ number_format((float) $h->costo_ultima_compra, 2, ',', '.') }}@endif
		@if ($gastOpc)
			| Opc: {{ $h->esopcional ? 'Sí' : 'No' }}
			@if ($h->esopcional && $h->ordenopcional !== null && $h->ordenopcional !== '')
				| Ord.opc: {{ $h->ordenopcional }}
			@endif
		@endif
		@if (! $exportMostrarCodigo && $fh && trim((string) ($fh->codigo ?? '')) !== '')
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
