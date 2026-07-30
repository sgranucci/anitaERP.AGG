@php
    $filasExport = [];
    foreach ($datas as $row) {
        $empresas = $row->empresas ?? collect();
        if ($empresas->isEmpty()) {
            $filasExport[] = (object) [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
                'empresa' => '',
                'cuenta' => '',
                'contrap' => '',
                'centrocosto' => '',
                'estado' => $row->estado_label,
            ];
            continue;
        }
        foreach ($empresas as $linea) {
            $filasExport[] = (object) [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
                'empresa' => $linea->empresa->nombre ?? '',
                'cuenta' => trim(($linea->cuentacontable->codigo ?? '').' '.($linea->cuentacontable->nombre ?? '')),
                'contrap' => trim(($linea->cuentacontableContrapartida->codigo ?? '').' '.($linea->cuentacontableContrapartida->nombre ?? '')),
                'centrocosto' => trim(($linea->centrocosto->codigo ?? '').' '.($linea->centrocosto->nombre ?? '')),
                'estado' => $row->estado_label,
            ];
        }
    }
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="8" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="8"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de apertura de gastos</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>C&oacute;digo</th>
			<th>Nombre</th>
			<th>Empresa</th>
			<th>Cuenta contable</th>
			<th>Contrapartida</th>
			<th>Centro costo</th>
			<th>Estado</th>
		</tr>
	</thead>
	<tbody>
		@foreach ($filasExport as $fila)
			<tr>
				<td>{{ $fila->id }}</td>
				<td>{{ $fila->codigo }}</td>
				<td>{{ $fila->nombre }}</td>
				<td>{{ $fila->empresa }}</td>
				<td>{{ $fila->cuenta }}</td>
				<td>{{ $fila->contrap }}</td>
				<td>{{ $fila->centrocosto }}</td>
				<td>{{ $fila->estado }}</td>
			</tr>
		@endforeach
	</tbody>
</table>
