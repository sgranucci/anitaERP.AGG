<h2> Reporte de Liquidaci&oacute;n de Tareas </h2>
<h1><strong>Desde Empleado: {{$desdeempleado ?? ''}}-{{$nombredesdeempleado}}</strong>&nbsp;
	<strong>Hasta Empleado: {{$hastaempleado ?? ''}}-{{$nombrehastaempleado}}</strong>&nbsp;&nbsp;
	<strong>Desde: {{date("d/m/Y", strtotime($desdefecha ?? ''))}} </strong>&nbsp;
	<strong>Hasta: {{date("d/m/Y", strtotime($hastafecha ?? ''))}} </strong>&nbsp;
	@if ($estado == 'PENDIENTE')
		<strong>Tareas pendientes</strong>
	@endif
	@if ($estado == 'CUMPLIDA')
		<strong>Tareas cumplidas</strong>
	@endif
	@if ($estado == 'TODAS')
		<strong>Todas las tareas</strong>
	@endif
</h1>
<table>
	<thead>
    <tr>
       	<th>Numero OT</th>
       	<th>Tarea</th>
       	<th>Descripci&oacute;n Tarea</th>
       	<th>Fecha Inicio</th>
       	<th>Fecha Final</th>
       	<th>Art&iacute;culo</th>
		<th>SKU</th>
       	<th>Combinaci&oacute;n</th>
		<th>Pedido</th>
		<th>Pares</th>
		<th>Costo por par</th>
		<th align="right">Total</th>
	</tr>
  	</thead>
    <tbody>
	@php
		$legajoActual = '';
		$nombreEmpleadoActual = '';
	@endphp
	
	@php
		$totalCantidad['legajo'] = 0;
		$totalCosto['legajo'] = 0;
		$totalCantidad['final'] = 0;
		$totalCosto['final'] = 0;
	@endphp
	
	@foreach ($tareas as $data)
			@if ($data['numerolegajo'] != $legajoActual)
				@if ($legajoActual != '')
					@include('exports.produccion.reporteliquidaciontarea.imprimetotalempleado')
				@endif
				@php 
					$legajoActual = $data['numerolegajo']; 
					$nombreEmpleadoActual = $data['nombreempleado']; 
				@endphp
				@php
					$totalCantidad['legajo'] = 0;
					$totalCosto['legajo'] = 0;
				@endphp
			@endif

			@include('exports.produccion.reporteliquidaciontarea.imprimeunatarea')
			@php 
				$totalCantidad['legajo'] += $data['cantidad']; 
				$totalCosto['legajo'] += ($data['costoporpar'] * $data['cantidad']);
				$totalCantidad['final'] += $data['cantidad']; 
				$totalCosto['final'] += ($data['costoporpar'] * $data['cantidad']);
			@endphp
	@endforeach

	@if ($legajoActual != '')
		@include('exports.produccion.reporteliquidaciontarea.imprimetotalempleado')
	@endif
	@include('exports.produccion.reporteliquidaciontarea.imprimetotalfinal')
	</tbody>
</table>
