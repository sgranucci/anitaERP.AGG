@php
    use App\Support\Caja\ChequeDepositoComprobanteSupport;
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
	<title>{{ $titulo ?? 'Cheques' }}</title>
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">{{ $titulo ?? 'Cheques' }}</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				@if (!empty($subtitulo))
					<div class="meta">{{ $subtitulo }}</div>
				@endif
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
				<th style="width: 5%;">ID</th>
				<th style="width: 9%;">N&uacute;mero</th>
				<th style="width: 6%;">Int.</th>
				<th style="width: 10%;">Estado</th>
				<th style="width: 8%;">{{ $etiquetaFechaDoc ?? 'Emisi&oacute;n' }}</th>
				<th style="width: 8%;">Fecha cheque</th>
				<th style="width: 14%;">Cuenta / Banco</th>
				<th style="width: 12%;">Empresa</th>
				<th style="width: 8%;">Monto</th>
				<th style="width: 5%;">Mon</th>
				<th style="width: 15%;">Beneficiario</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($datas as $data)
				@php
					$estadoLabel = collect($estado_enum ?? [])->firstWhere('valor', $data->estado);
				@endphp
				<tr>
					<td>{{ $data->id }}</td>
					<td>{{ $data->numerocheque }}</td>
					<td>{{ $data->nro_interno_anita }}</td>
					<td>{{ $estadoLabel['nombre'] ?? $data->estado }}</td>
					<td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
					<td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
					<td>
						@if (($data->origen ?? '') === 'E')
							{{ $data->cuentacajas->nombre ?? '' }}
						@else
							{{ $data->bancos->nombre ?? '' }}
						@endif
					</td>
					<td>{{ $data->empresas->nombre ?? '' }}</td>
					<td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
					<td>{{ $data->monedas->abreviatura ?? '' }}</td>
					<td>{{ $data->entregado ?? $data->anombrede }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
	@if (($totales ?? collect())->isNotEmpty())
		<p class="meta">
			@foreach ($totales as $tot)
				Total {{ $tot->moneda ?? '' }}: {{ number_format((float) $tot->monto, 2, ',', '.') }} ({{ (int) $tot->cantidad }})
				@if (! $loop->last) · @endif
			@endforeach
		</p>
	@endif
	</td>
	<td class="marco-lat"></td>
</tr></table>
</body>
</html>
