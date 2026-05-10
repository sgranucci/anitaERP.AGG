@php
    $mesesPeriodoCapex = [
        '01' => 'Enero',
        '02' => 'Febrero',
        '03' => 'Marzo',
        '04' => 'Abril',
        '05' => 'Mayo',
        '06' => 'Junio',
        '07' => 'Julio',
        '08' => 'Agosto',
        '09' => 'Septiembre',
        '10' => 'Octubre',
        '11' => 'Noviembre',
        '12' => 'Diciembre',
    ];
@endphp
<template id="template-renglon-partida-monto">
	<tr class="item-partida-monto">
		<td>
			<input type="hidden" name="items_monto[]" class="item_monto" value="" />
			<input type="hidden" name="capex_partida_ids_monto[]" class="capex_partida_id_monto" value="" />
			<input type="hidden" name="creousuario_ids_monto[]" class="creousuario_id_monto" value="{{ auth()->id() }}" />
			<input type="hidden" name="periodos[]" class="periodo" value="">
			<div class="form-row align-items-center mx-0 capex-periodo-picker">
				<div class="col-6 col-md-5 pr-md-1 px-0 mb-1 mb-md-0">
					<label class="d-md-none small text-muted mb-0">Año</label>
					<select class="form-control periodo-anio" title="Año del período" aria-label="Año del período"></select>
				</div>
				<div class="col-6 col-md-7 pl-md-1 px-0">
					<label class="d-md-none small text-muted mb-0">Mes</label>
					<select class="form-control periodo-mes" title="Mes del período" aria-label="Mes del período">
						<option value="">Mes</option>
						@foreach ($mesesPeriodoCapex as $num => $nombre)
							<option value="{{ $num }}">{{ $nombre }}</option>
						@endforeach
					</select>
				</div>
			</div>
		</td>
		<td>
			<input type="text" name="montos[]" class="form-control monto" value="">
		</td>
		<td>
			<button style="width: 7%;" type="button" class="btn-accion-tabla eliminar_renglon_partida_monto tooltipsC">
				<i class="fa fa-times-circle text-danger"></i>
			</button>
		</td>
	</tr>
</template>
