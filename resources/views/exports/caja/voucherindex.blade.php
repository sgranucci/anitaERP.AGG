<h2> Vouchers </h2>
<table> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Número</th>
		<th>Fecha</th>
		<th>Talonario Vouchers</th>
		<th>PAX</th>
		<th>Reserva</th>
		<th>Cantidad</th>
		<th>Proveedor</th>
		<th>Servicio</th>
		<th>Forma de pago</th>
		<th>Monto Voucher</th>
		<th>Guías</th>
	</tr>
  	</thead>
    <tbody>
		@foreach ($vouchers as $data)
			<tr data-entry-id="{{ $data->id }}">
				<td>{{$data->id}}</td>
				<td>{{$data->idtalonario}}_{{$data->numerovoucher}}</td>
				<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
				<td>{{$data->nombretalonario}}</td>
				<td>{{$data->nombrepasajero}}</td>
				<td>{{$data->numeroreserva}}</td>
				<td>{{$data->pax+$data->paxfree+$data->incluido+$data->opcional}}</td>
				<td>{{$data->nombreproveedor ?? ''}}</td>
				<td>{{$data->nombreservicio ?? ''}}</td>
				<td>{{$data->nombreformapago ?? ''}}</td>
				<td>{{number_format($data->montovoucher,2)}}</td>
				<td>
					<ul>
					@foreach($data->voucher_guias as $guia)
						<li>{{ $guia->guias->nombre }} Porc. {{ number_format($guia->porcentajecomision,2) }} Comis. {{ number_format($guia->montocomision,2) }}</li>
					@endforeach
					</ul>
				</td>
			</tr>
		@endforeach
	</tbody>
</table>
