@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $coleccionLogos = collect($grupos ?? [])->map(function ($f) {
        return (object) ['nombreempresa' => $f['nombreempresa'] ?? $f['empresa'] ?? ''];
    });
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion($coleccionLogos);
    $totalFilas = is_countable($grupos) ? count($grupos) : 0;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Historial depósitos CHT</title>
	<style>
		body { font-family: DejaVu Sans, Helvetica, Arial, sans-serif; font-size: 8px; color: #1a1a1a; }
		table.data {
			border-collapse: collapse;
			width: 100%;
		}
		table.data thead { display: table-header-group; }
		table.data tr { page-break-inside: avoid; }
		table.data td, table.data th {
			border: 1px solid #cccccc;
			padding: 4px;
			vertical-align: top;
		}
		table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }
		table.data th {
			font-weight: bold;
			color: #17202A;
			background-color: #85C1E9;
		}
		.text-right { text-align: right; }
	</style>
</head>
<body>
<table class="data">
	<thead>
		@include('includes.reportes.pdf_thead_cabecera', [
			'titulo' => 'Historial de depósitos CHT',
			'subtitulo' => $subtitulo ?? '',
			'colspan' => 8,
			'logosCabecera' => $logosCabecera,
			'totalFilas' => $totalFilas,
		])
		<tr>
			<th>Fecha dep.</th>
			<th>Nro. boleta</th>
			<th>Cuenta</th>
			<th>Empresa</th>
			<th class="text-right">Cheques</th>
			<th class="text-right">Monto</th>
			<th>Estado</th>
			<th>Detalle</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($grupos as $g)
			@php
				$detalleEst = [];
				if (($g['estado_counts']['transito'] ?? 0) > 0) {
					$detalleEst[] = 'Tránsito '.$g['estado_counts']['transito'];
				}
				if (($g['estado_counts']['acreditado'] ?? 0) > 0) {
					$detalleEst[] = 'Acred. '.$g['estado_counts']['acreditado'];
				}
				if (($g['estado_counts']['rechazado'] ?? 0) > 0) {
					$detalleEst[] = 'Rech. '.$g['estado_counts']['rechazado'];
				}
				$monedasTxt = collect($g['totales_moneda'] ?? [])->map(function ($tm) {
					return $tm['moneda'].' '.number_format((float) $tm['monto'], 2, ',', '.');
				})->implode(' | ');
			@endphp
			<tr>
				<td>{{ $g['fecha_deposito_dmy'] ?? $g['fecha_deposito'] }}</td>
				<td>{{ $g['nro_boleta'] !== '' ? $g['nro_boleta'] : '(sin boleta)' }}</td>
				<td>{{ $g['cuenta'] }}</td>
				<td>{{ $g['empresa'] }}</td>
				<td class="text-right">{{ $g['cantidad'] }}</td>
				<td class="text-right">{{ $monedasTxt !== '' ? $monedasTxt : number_format((float) $g['monto'], 2, ',', '.') }}</td>
				<td>{{ $g['estado_label'] }}</td>
				<td>{{ implode(' · ', $detalleEst) }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
</body>
</html>
