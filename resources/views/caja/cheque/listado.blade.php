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
		table.data thead { display: table-header-group; }
		table.data thead tr.columnas > th {
			background-color: #85C1E9;
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
@php
    $filasPdf = [];
    foreach ($datas as $data) {
        $filasPdf[] = ['type' => 'row', 'row' => $data];
    }
    $lotesPdf = \App\Support\Listado\ListadoPdfRapidoSupport::lotes($filasPdf);
@endphp
@foreach ($lotesPdf as $indiceLote => $lote)
<table class="data">
    <thead>
        @if ($indiceLote === 0)
            @include('includes.reportes.pdf_thead_cabecera', [
                'titulo' => 'Listado de cheques',
                'subtitulo' => '',
                'colspan' => 13,
                'logosCabecera' => $logosCabecera,
                'totalFilas' => $totalFilas,
            ])
        @endif
        <tr class="columnas">
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
			@foreach ($lote as $item)
				@php $data = $item['row']; @endphp
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
					<td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
					<td>{{ \App\Support\Caja\ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
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
@endforeach
</body>
</html>
