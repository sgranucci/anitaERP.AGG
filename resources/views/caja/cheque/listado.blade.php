@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresas->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Cheques</title>
	<style>
		@include('includes.reportes.estilos_pdf_pagina', [
			'pdf_size' => 'legal landscape',
			'pdf_margin' => '14mm 16mm',
		])
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
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
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data thead tr { background-color: #85C1E9; }
		table.data th {
			font-size: 7px;
			font-weight: bold;
			color: #17202A;
		}
		.listado-header { width: 100%; margin-bottom: 10px; border-bottom: 2px solid #333; padding-bottom: 6px; }
		.listado-header td { vertical-align: middle; border: none; }
		.meta { font-size: 8px; color: #444; margin-top: 4px; }
		.text-right { text-align: right; }
	</style>
</head>
<body>
<table class="marco-pdf"><tr>
	<td class="marco-lat"></td>
	<td class="marco-centro">
	<table class="listado-header">
		<tr>
			<td style="width: 35%;">
				@foreach ($logosCabecera as $logo)
					<img src="{{ $logo['uri'] }}" alt="{{ $logo['nombre'] }}" style="max-height: 56px; max-width: 180px; margin-right: 10px; margin-bottom: 4px; vertical-align: middle;">
				@endforeach
			</td>
			<td style="width: 40%; text-align: center;">
				<h2 style="margin: 0; font-size: 20px; font-weight: bold;">Listado de cheques</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
			</td>
			<td style="width: 25%; text-align: right; font-size: 8px;">
				@if ($totalFilas > 0)
					Registros: {{ $totalFilas }}
				@endif
			</td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width: 4%;">ID</th>
				<th style="width: 8%;">N&uacute;mero</th>
				<th style="width: 6%;">Int. Anita</th>
				<th style="width: 7%;">Origen</th>
				<th style="width: 5%;">Tipo</th>
				<th style="width: 8%;">Estado</th>
				<th style="width: 7%;">Emisi&oacute;n</th>
				<th style="width: 7%;">Pago</th>
				<th style="width: 12%;">Cuenta / Banco</th>
				<th style="width: 10%;">Empresa</th>
				<th style="width: 8%;">Monto</th>
				<th style="width: 5%;">Moneda</th>
				<th style="width: 18%;">Beneficiario</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				@php
					$origenLabel = collect($origen_enum ?? [])->firstWhere('valor', $data->origen);
					$estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
				@endphp
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->numerocheque }}</td>
					<td>{{ $data->nro_interno_anita }}</td>
					<td>{{ $origenLabel['nombre'] ?? $data->origen }}</td>
					<td>{{ \App\Support\Caja\ChequePropioInstrumentoSupport::etiquetaNegociable($data->negociable ?? null) }}</td>
					<td>{{ $estadoLabel['nombre'] ?? $data->estado }}</td>
					<td>{{ $data->fechaemision }}</td>
					<td>{{ $data->fechapago }}</td>
					<td>
						@if (($data->origen ?? '') === 'E')
							{{ $data->cuentacajas->nombre ?? '' }}
						@else
							{{ $data->bancos->nombre ?? '' }}
						@endif
					</td>
					<td>{{ $data->empresas->nombre ?? '' }}</td>
					<td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
					<td>{{ $data->monedas->abreviatura ?? ($data->monedas->nombre ?? '') }}</td>
					<td>{{ $data->entregado ?? $data->anombrede }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
	</td>
	<td class="marco-lat"></td>
</tr></table>
</body>
</html>
