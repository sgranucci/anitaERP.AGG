@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($requisicion);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Requisiciones</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; }
		table.data {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			table-layout: fixed;
		}
		table.data td, table.data th {
			border: 1px solid #cccccc;
			text-align: left;
			padding: 4px;
			vertical-align: top;
			word-wrap: break-word;
		}
		table.data tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #d4e6f1; }
		table.data th { font-size: 7px; font-weight: bold; color: #1a1a1a; }
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
		.cell-items { font-size: 7px; white-space: normal; }
		.num { text-align: right; white-space: nowrap; }
	</style>
</head>
<body>
	<table class="listado-header">
		<tr>
			<td style="width: 35%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de requisiciones</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
			<td style="width: 25%;"></td>
		</tr>
	</table>
	<table class="data">
		<colgroup>
			<col style="width: 3%;">
			<col style="width: 5%;">
			<col style="width: 4%;">
			<col style="width: 4%;">
			<col style="width: 6%;">
			<col style="width: 5%;">
			<col style="width: 5%;">
			<col style="width: 3%;">
			<col style="width: 6%;">
			<col style="width: 4%;">
			<col style="width: 2%;">
			<col style="width: 4%;">
			<col style="width: 3%;">
			<col style="width: 4%;">
			<col style="width: 5%;">
			<col style="width: 4%;">
			<col style="width: 5%;">
			<col style="width: 5%;">
			<col style="width: 5%;">
			<col style="width: 3%;">
			<col style="width: 14%;">
		</colgroup>
		<thead>
			<tr>
				<th>ID</th>
				<th>Número</th>
				<th>Fecha</th>
				<th>F. entrega</th>
				<th>Empresa</th>
				<th>Centro costo</th>
				<th>Oficina compra</th>
				<th>Prov. código</th>
				<th>Proveedor</th>
				<th>Forma pago</th>
				<th>Mon.</th>
				<th class="num">Monto</th>
				<th>Tratam.</th>
				<th>Motivo trat.</th>
				<th>Contratación dir.</th>
				<th>Estado</th>
				<th>Usuario alta</th>
				<th>Comentario</th>
				<th>Detalle cab.</th>
				<th>Nro inscr.</th>
				<th>Ítems (líneas)</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($requisicion as $data)
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->numerorequisicion }}</td>
					<td>{{ $data->fecha ? date('d/m/Y', strtotime($data->fecha)) : '' }}</td>
					<td>{{ $data->fechaentrega ? date('d/m/Y', strtotime($data->fechaentrega)) : '' }}</td>
					<td>{{ $data->nombreempresa }}</td>
					<td><small>{{ trim(($data->codigocentrocosto ?? '').' '.$data->nombrecentrocosto) }}</small></td>
					<td><small>{{ $data->nombreoficinacompra ?? '' }}</small></td>
					<td>{{ $data->codigoproveedor ?? '' }}</td>
					<td><small>{{ $data->nombreproveedor ?? '' }}</small></td>
					<td><small>{{ $data->nombreformapago ?? '' }}</small></td>
					<td>{{ $data->monedacabecera_abreviatura ?? '' }}</td>
					<td class="num">{{ number_format((float) ($data->monto ?? 0), 2, ',', '.') }}</td>
					<td>{{ $data->tratamiento }}</td>
					<td><small>{{ $data->motivotratamiento ?? '' }}</small></td>
					<td><small>{{ $data->contrataciondirecta ?? '' }}</small></td>
					<td><small>{{ $data->estado }}</small></td>
					<td><small>{{ $data->nombreusuario }}</small></td>
					<td><small>{{ $data->comentario }}</small></td>
					<td><small>{{ $data->detalle }}</small></td>
					<td><small>{{ $data->nroinscripcion ?? '' }}</small></td>
					<td class="cell-items">@include('compras.requisicion.partials.export_items_detalle', ['data' => $data, 'separator' => '<br>'])</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
