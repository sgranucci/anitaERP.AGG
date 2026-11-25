@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    use App\Support\Sueldos\ComparativoConceptosListadoFiltros as F;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect());
    $columnas = $resultado['columnas'] ?? [];
    $formato = $resultado['formato'] ?? F::FORMATO_IMPORTE;
    $muestraDif = ! empty($resultado['muestra_diferencia']);
    $mostrarCant = in_array($formato, [F::FORMATO_CANTIDAD, F::FORMATO_AMBOS], true);
    $mostrarImp = in_array($formato, [F::FORMATO_IMPORTE, F::FORMATO_AMBOS], true);
    $fmtN = function ($n, bool $force = false) {
        $n = (float) $n;
        if (! $force && abs($n) < 0.00001) {
            return '';
        }

        return number_format($n, 2, ',', '.');
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="utf-8">
	<title>{{ $titulo }}</title>
	<style>
		body { font-family: DejaVu Sans, sans-serif; font-size: 7px; color: #1a1a1a; }
		table.data { border-collapse: collapse; width: 100%; }
		table.data th, table.data td { border: 1px solid #cccccc; padding: 2px 3px; }
		table.data thead th { background: #85C1E9; color: #17202A; }
		table.data tr:nth-child(even) td { background: #f5f5f5; }
		.total { font-weight: bold; background: #e8e8e8 !important; }
		.grupo { font-weight: bold; background: #d6eaf8 !important; }
		.text-right { text-align: right; }
		.listado-header { width: 100%; margin-bottom: 8px; border-bottom: 2px solid #333; padding-bottom: 4px; }
		.listado-header td { border: none; vertical-align: middle; }
		.meta { font-size: 8px; color: #444; margin-top: 2px; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width:30%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height:48px;max-width:160px;margin-right:8px;">
				@endforeach
			</td>
			<td style="width:45%;text-align:center;">
				<h2 style="margin:0;font-size:14px;">{{ $titulo }}</h2>
				<div class="meta">{{ $resultado['titulo2'] ?? '' }}</div>
				<div class="meta">Generado {{ date('d/m/Y H:i') }} · {{ $subtitulo }}</div>
			</td>
			<td style="width:25%;text-align:right;font-size:8px;">
				{{ $resultado['totales']['filas_dato'] ?? 0 }} filas ·
				{{ $resultado['totales']['empleados'] ?? 0 }} emp.
			</td>
		</tr>
	</table>

	<table class="data">
		<thead>
			<tr>
				<th>N.Leg.</th>
				<th>Nombre</th>
				<th>F.Ingr.</th>
				<th>C.Costo</th>
				<th>Categor&iacute;a</th>
				<th>Sindicato</th>
				<th>N.Con.</th>
				<th>Concepto</th>
				@foreach ($columnas as $col)
					@if ($formato === F::FORMATO_AMBOS)
						<th class="text-right" colspan="2">{{ $col['etiqueta'] }}</th>
					@else
						<th class="text-right">{{ $col['etiqueta'] }}</th>
					@endif
				@endforeach
				@if ($muestraDif)
					@if ($formato === F::FORMATO_AMBOS)
						<th class="text-right" colspan="2">Diferencia</th>
					@else
						<th class="text-right">Diferencia</th>
					@endif
				@endif
				@if ($formato === F::FORMATO_AMBOS)
					<th class="text-right" colspan="2">Total</th>
				@else
					<th class="text-right">Total</th>
				@endif
			</tr>
			@if ($formato === F::FORMATO_AMBOS)
				<tr>
					<th colspan="8"></th>
					@foreach ($columnas as $col)
						<th class="text-right">Cant</th>
						<th class="text-right">Imp.</th>
					@endforeach
					@if ($muestraDif)
						<th class="text-right">Cant</th>
						<th class="text-right">Imp.</th>
					@endif
					<th class="text-right">Cant</th>
					<th class="text-right">Imp.</th>
				</tr>
			@endif
		</thead>
		<tbody>
			@foreach ($filas as $f)
				@if (($f['tipo'] ?? '') === 'grupo')
					<tr class="grupo">
						<td colspan="{{ 8 + count($columnas) * ($formato === F::FORMATO_AMBOS ? 2 : 1) + ($muestraDif ? ($formato === F::FORMATO_AMBOS ? 2 : 1) : 0) + ($formato === F::FORMATO_AMBOS ? 2 : 1) }}">
							{{ $f['grupo_label'] ?? '' }}
						</td>
					</tr>
				@elseif (($f['tipo'] ?? '') === 'total_grupo' || !empty($f['es_total']))
					<tr class="total">
						<td colspan="8">{{ $f['nombre'] ?? 'Total' }}</td>
						@foreach ($columnas as $col)
							@php $v = $f['valores'][$col['clave']] ?? ['importe' => 0, 'cantidad' => 0]; @endphp
							@if ($mostrarCant)<td class="text-right">{{ $fmtN($v['cantidad'] ?? 0, true) }}</td>@endif
							@if ($mostrarImp)<td class="text-right">{{ $fmtN($v['importe'] ?? 0, true) }}</td>@endif
						@endforeach
						@if ($muestraDif)
							@if ($mostrarCant)<td class="text-right">{{ $fmtN($f['diferencia_cantidad'] ?? 0, true) }}</td>@endif
							@if ($mostrarImp)<td class="text-right">{{ $fmtN($f['diferencia_importe'] ?? 0, true) }}</td>@endif
						@endif
						@if ($mostrarCant)<td class="text-right">{{ $fmtN($f['total_cantidad'] ?? 0, true) }}</td>@endif
						@if ($mostrarImp)<td class="text-right">{{ $fmtN($f['total_importe'] ?? 0, true) }}</td>@endif
					</tr>
				@else
					<tr>
						<td>{{ $f['legajo'] ?? '' }}</td>
						<td>{{ $f['nombre'] ?? '' }}</td>
						<td>{{ $f['fecha_ingreso'] ?? '' }}</td>
						<td>{{ $f['centrocosto'] ?? '' }}</td>
						<td>{{ $f['categoria'] ?? '' }}</td>
						<td>{{ $f['sindicato'] ?? '' }}</td>
						<td>{{ $f['concepto_codigo'] ?? '' }}</td>
						<td>{{ $f['concepto_descripcion'] ?? '' }}</td>
						@foreach ($columnas as $col)
							@php $v = $f['valores'][$col['clave']] ?? ['importe' => 0, 'cantidad' => 0]; @endphp
							@if ($mostrarCant)<td class="text-right">{{ $fmtN($v['cantidad'] ?? 0) }}</td>@endif
							@if ($mostrarImp)<td class="text-right">{{ $fmtN($v['importe'] ?? 0) }}</td>@endif
						@endforeach
						@if ($muestraDif)
							@if ($mostrarCant)<td class="text-right">{{ $fmtN($f['diferencia_cantidad'] ?? 0) }}</td>@endif
							@if ($mostrarImp)<td class="text-right">{{ $fmtN($f['diferencia_importe'] ?? 0) }}</td>@endif
						@endif
						@if ($mostrarCant)<td class="text-right">{{ $fmtN($f['total_cantidad'] ?? 0) }}</td>@endif
						@if ($mostrarImp)<td class="text-right">{{ $fmtN($f['total_importe'] ?? 0) }}</td>@endif
					</tr>
				@endif
			@endforeach
		</tbody>
	</table>
</body>
</html>
