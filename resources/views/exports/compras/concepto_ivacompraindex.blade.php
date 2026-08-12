@php
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
                'cuenta_debe' => '',
                'cuenta_haber' => '',
                'columna' => $row->columna_ivacompras->nombre ?? '',
            ];
            continue;
        }
        foreach ($empresas as $linea) {
            $filasExport[] = (object) [
                'id' => $row->id,
                'codigo' => $row->codigo,
                'nombre' => $row->nombre,
                'tipo' => $row->desc_tipo_concepto,
                'ret_gan' => $row->desc_retiene_ganancia,
                'ret_iibb' => $row->desc_retiene_iibb,
                'empresa' => $linea->empresa->nombre ?? '',
                'cuenta_debe' => trim(($linea->cuentacontabledebe->codigo ?? '').' '.($linea->cuentacontabledebe->nombre ?? '')),
                'cuenta_haber' => trim(($linea->cuentacontablehaber->codigo ?? '').' '.($linea->cuentacontablehaber->nombre ?? '')),
                'columna' => $row->columna_ivacompras->nombre ?? '',
            ];
        }
    }
@endphp
<table>
	@if (!empty($reservarFilaLogoExcel))
		<tbody>
			<tr>
				<td colspan="10" style="height: 52px;">&#160;</td>
			</tr>
		</tbody>
	@endif
	<tbody>
		<tr>
			<td colspan="10"><h2 style="margin: 0; font-size: 18pt; font-weight: bold;">Listado de conceptos del Libro de IVA Compras</h2></td>
		</tr>
	</tbody>
	<thead>
		<tr>
			<th>ID</th>
			<th>Código</th>
			<th>Nombre</th>
			<th>Tipo</th>
			<th>Ret. Gan.</th>
			<th>Ret. IIBB</th>
			<th>Empresa</th>
			<th>Cuenta Debe</th>
			<th>Cuenta Haber</th>
			<th>Columna IVA</th>
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
