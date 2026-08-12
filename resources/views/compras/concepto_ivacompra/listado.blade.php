@php
    use App\Support\Configuracion\EmpresaLogoArchivo;
    $filasExport = [];
    foreach ($datas as $row) {
        $empresas = $row->concepto_ivacompra_empresas ?? collect();
        if ($empresas->isEmpty()) {
            $filasExport[] = (object) [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
                'tipo' => $row->desc_tipo_concepto,
                'ret_gan' => $row->desc_retiene_ganancia,
                'ret_iibb' => $row->desc_retiene_iibb,
                'empresa' => '',
                'cuenta_debe' => trim(($row->cuentacontablesdebe->codigo ?? '').' '.($row->cuentacontablesdebe->nombre ?? '')),
                'cuenta_haber' => trim(($row->cuentacontableshaber->codigo ?? '').' '.($row->cuentacontableshaber->nombre ?? '')),
                'columna' => $row->columna_ivacompras->nombre ?? '',
                'nombreempresa' => '',
            ];
            continue;
        }
        foreach ($empresas as $linea) {
            $nombreEmp = $linea->empresa->nombre ?? '';
            $filasExport[] = (object) [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
                'tipo' => $row->desc_tipo_concepto,
                'ret_gan' => $row->desc_retiene_ganancia,
                'ret_iibb' => $row->desc_retiene_iibb,
                'empresa' => $nombreEmp,
                'cuenta_debe' => trim(($linea->cuentacontabledebe->codigo ?? '').' '.($linea->cuentacontabledebe->nombre ?? '')),
                'cuenta_haber' => trim(($linea->cuentacontablehaber->codigo ?? '').' '.($linea->cuentacontablehaber->nombre ?? '')),
                'columna' => $row->columna_ivacompras->nombre ?? '',
                'nombreempresa' => $nombreEmp,
            ];
        }
    }
    $logosCabecera = EmpresaLogoArchivo::logosCabeceraDesdeColeccion(collect($filasExport));
    $totalFilas = count($filasExport);
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
	<title>Conceptos IVA Compras</title>
	<style>
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
				<h2 style="margin: 0; font-size: 18px; font-weight: bold;">Listado de conceptos del Libro de IVA Compras</h2>
				<div class="meta">Generado {{ date('d/m/Y H:i') }}</div>
				<div class="meta">{{ $totalFilas }} registro(s)</div>
			</td>
			<td style="width: 25%;"></td>
		</tr>
	</table>
	<table class="data">
		<thead>
			<tr>
				<th style="width:5%;">ID</th>
				<th style="width:7%;">Código</th>
				<th style="width:16%;">Nombre</th>
				<th style="width:10%;">Tipo</th>
				<th style="width:6%;">Ret.Gan</th>
				<th style="width:6%;">Ret.IIBB</th>
				<th style="width:12%;">Empresa</th>
				<th style="width:14%;">Cuenta Debe</th>
				<th style="width:14%;">Cuenta Haber</th>
				<th style="width:10%;">Columna</th>
			</tr>
		</thead>
		<tbody>
			@foreach ($filasExport as $fila)
				<tr>
					<td>{{ $fila->id }}</td>
					<td>{{ $fila->codigo }}</td>
					<td>{{ $fila->nombre }}</td>
					<td>{{ $fila->tipo }}</td>
					<td>{{ $fila->ret_gan }}</td>
					<td>{{ $fila->ret_iibb }}</td>
					<td>{{ $fila->empresa }}</td>
					<td>{{ $fila->cuenta_debe }}</td>
					<td>{{ $fila->cuenta_haber }}</td>
					<td>{{ $fila->columna }}</td>
				</tr>
			@endforeach
		</tbody>
	</table>
</body>
</html>
