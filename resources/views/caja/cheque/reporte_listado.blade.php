@php
    use App\Support\Caja\ChequeDepositoComprobanteSupport;
    use App\Support\Caja\ChequeReporteSupport;
    use App\Support\Configuracion\EmpresaLogoArchivo;
    foreach ($datas as $row) {
        $row->nombreempresa = $row->empresas->nombre ?? '';
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($datas);
    $totalFilas = is_countable($datas) ? count($datas) : 0;
    $filasReporte = $filas ?? ChequeReporteSupport::filasConSubtotalesDiarios(collect($datas), [
        'orden' => 'fechapago',
    ]);
    $esRecibido = (($tipoReporte ?? 'E') === 'R');
    $colspanDatos = 13;
    $textoCero = static function ($valor): string {
        $t = trim((string) ($valor ?? ''));
        if ($t === '' || $t === '0' || preg_match('/^0+$/', $t)) {
            return '';
        }

        return $t;
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>{{ $titulo ?? 'Cheques' }}</title>
	<style>
		/* Sin marco-pdf: en DomPDF el wrapper anidado deja la 2.ª hoja casi vacía. */
		@page { size: legal landscape; margin: 14mm 8mm 16mm 8mm; }
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 7px; color: #1a1a1a; margin: 0; padding: 0; }
		table.data {
			font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
			border-collapse: collapse;
			width: 100%;
			table-layout: fixed;
		}
		table.data td, table.data th {
			border: 1px solid #cccccc;
			text-align: left;
			padding: 2px 3px;
			vertical-align: top;
			word-wrap: break-word;
		}
		table.data thead { display: table-header-group; }
		table.data tbody { display: table-row-group; }
		table.data tr { page-break-inside: avoid; }
		table.data tbody tr:nth-child(even):not(.subtotal):not(.total-general) { background-color: #f5f5f5; }
		table.data thead tr.columnas th {
			background-color: #85C1E9;
			font-size: 6.5px;
			font-weight: bold;
			color: #17202A;
		}
		table.data tr.subtotal td { background-color: #D6EAF8; font-weight: bold; }
		table.data tr.total-general td { background-color: #AED6F1; font-weight: bold; }
		.meta { font-size: 7px; color: #444; margin-top: 6px; }
		.text-right { text-align: right; }
	</style>
</head>
<body>
	<table class="data">
		<thead>
			@include('includes.reportes.pdf_thead_cabecera', [
				'titulo' => $titulo ?? 'Cheques',
				'subtitulo' => $subtitulo ?? '',
				'colspan' => $colspanDatos,
				'logosCabecera' => $logosCabecera,
				'totalFilas' => $totalFilas,
			])
			<tr class="columnas">
				<th style="width: 5%;">Int.</th>
				<th style="width: 6%;">{{ $esRecibido ? 'Fec.Ing.' : 'Fec.Emis.' }}</th>
				<th style="width: 6%;">Fec.Che.</th>
				<th style="width: 9%;">Importe</th>
				<th style="width: 5%;">N.Cli.</th>
				<th style="width: 14%;">Cliente</th>
				<th style="width: 12%;">Destino</th>
				<th style="width: 8%;">Nro. cheque</th>
				<th style="width: 13%;">{{ $esRecibido ? 'Banco' : 'Cuenta' }}</th>
				<th style="width: 4%;">Suc</th>
				<th style="width: 7%;">Cta.libr.</th>
				<th style="width: 5%;">{{ $esRecibido ? 'N.rec.' : 'N.OP' }}</th>
				<th style="width: 6%;">Empresa</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filasReporte as $fila)
				@if (($fila['tipo'] ?? '') === 'cheque')
					@php $data = $fila['cheque']; @endphp
					<tr>
						<td>{{ $data->nro_interno_anita }}</td>
						<td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechaemision) }}</td>
						<td>{{ ChequeDepositoComprobanteSupport::fechaDmy($data->fechapago) }}</td>
						<td class="text-right">{{ number_format((float) $data->monto, 2, ',', '.') }}</td>
						<td>{{ ChequeReporteSupport::codigoCliente($data) }}</td>
						<td>{{ ChequeReporteSupport::nombreCliente($data) }}</td>
						<td>{{ ChequeReporteSupport::destino($data) }}</td>
						<td>{{ $data->numerocheque }}</td>
						<td>{{ ChequeReporteSupport::bancoOCuenta($data) }}</td>
						<td>{{ $textoCero($data->sucursalpago) }}</td>
						<td>{{ $textoCero($data->cuentalibradora) }}</td>
						<td>{{ ChequeReporteSupport::nroDocumentoOrigen($data) }}</td>
						<td>{{ $data->empresas->nombre ?? '' }}</td>
					</tr>
				@elseif (($fila['tipo'] ?? '') === 'total_dia')
					<tr class="subtotal">
						<td colspan="3">{{ $fila['etiqueta'] ?? '' }}</td>
						<td class="text-right">{{ number_format((float) ($fila['monto'] ?? 0), 2, ',', '.') }}</td>
						<td>{{ (int) ($fila['cantidad'] ?? 0) }}</td>
						<td colspan="8"></td>
					</tr>
				@elseif (($fila['tipo'] ?? '') === 'total_general')
					<tr class="total-general">
						<td colspan="3">{{ $fila['etiqueta'] ?? 'Total general' }}</td>
						<td class="text-right">{{ number_format((float) ($fila['monto'] ?? 0), 2, ',', '.') }}</td>
						<td>{{ (int) ($fila['cantidad'] ?? 0) }}</td>
						<td colspan="8"></td>
					</tr>
				@endif
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
</body>
</html>
