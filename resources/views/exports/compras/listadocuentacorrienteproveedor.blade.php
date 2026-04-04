<h2>Cuenta Corriente Proveedor {{$nombreproveedor}}</h2>
<table class="table table-striped table-bordered table-hover"> 
	<thead>
	<tr>
		<th class="width20">ID</th>
		<th>Empresa</th>
		<th>Fecha</th>
		<th>Vencimiento</th>
		<th>Comprobante</th>
		<th>Moneda</th>
		<th>Cotización</th>
		<th style="width: 12%; text-align: right;">Debe</th>
		<th style="width: 12%; text-align: right;">Haber</th>
		<th style="width: 12%; text-align: right;">Saldo</th>		
	</tr>
	</thead>
	<tbody>
		@foreach ($cuentacorriente as $data)
		<tr>
			<td class="cuentacorriente_id">{{$data->numerointerno == 0 ? '' : $data->numerointerno}}</td>
			<td>{{$data->nombreempresa ?? '' }}</td>
			<td>{{date("d/m/Y", strtotime($data->fecha ?? ''))}}</td>
			<td>
				@if ($data->numerointerno != 0)
					{{date("d/m/Y", strtotime($data->fechavencimiento ?? ''))}}
				@endif
			</td>
			<td class="comprobante">{{$data->tipo}} {{$data->letra}}{{$data->sucursal}}-{{$data->numero}}</td>
			<td>
				@foreach ($moneda_query as $moneda)
					@if ($moneda->codigo == $data->codigomoneda)
						<small>{{$moneda->nombre ?? ''}}</small>
					@endif
				@endforeach
				<input type="hidden" name="moneda" class="form-control moneda" value="{{$data->codigomoneda}}"> 
				<input type="hidden" name="codigoproveedor" class="form-control codigoproveedor" value="{{$codigoproveedor}}"> 
			</td>
			<td style="text-align: right;">{{$data->cotizacion}}</td>
			<td class="debe" style="text-align: right;">
				@if ($data->signo != "R")
					{{$data->total}}
				@endif
			</td>
			<td class="haber" style="text-align: right;">
				@if ($data->signo == "R")
					{{abs($data->total)}}
				@endif
			</td>
			<td style="text-align: right;">
				{{$data->saldo}}
			</td>
		</tr>
		@endforeach
	</tbody>
</table>