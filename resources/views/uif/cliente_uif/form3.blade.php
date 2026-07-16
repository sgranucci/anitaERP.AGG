@php
    $detalleClienteUifRestringido = esSoloVisualizacionClienteUif();
    $consultaPremiosCliente = request()->query('origen') === 'modal_consulta' && request()->query('uif_tab') === '3';
    $suffixConsultaPremios = $consultaPremiosCliente ? '&origen=modal_consulta&vista=consulta' : '';
    $suffixAltaPremio = ! empty($ocultarVolver ?? false) ? '&origen=modal_consulta&vista=consulta' : '';
    $mostrarForm3Directo = ! empty($soloSolapaPremios ?? false);
    $clienteUifId = isset($data) ? ($data->id ?? null) : null;
    $puedeAgregarPremioUif = can('crear-cliente-premio-uif', false);
    $urlAltaPremioUif = ($puedeAgregarPremioUif && $clienteUifId)
        ? route('crea_cliente_premio_uif', ['id' => $clienteUifId]).'?return_cliente_tab=3'.$suffixAltaPremio
        : null;
@endphp
<div class="form3"@if (! $mostrarForm3Directo) style="display: none"@endif>
    @include('uif.cliente_premio_uif.partials.foto_estilos')
    <div class="card-body">
        <div class="d-flex flex-wrap align-items-center mb-2" style="gap: 0.5rem;">
            @if ($clienteUifId && esSupervisorUif())
            <div>
                <a href="{{ route('lista_premios_cliente_uif', ['id' => $clienteUifId, 'formato' => 'PDF']) }}" class="btn btn-app bg-danger" target="_blank" rel="noopener">
                    <i class="fas fa-file-pdf"></i> Pdf
                </a>
                <a href="{{ route('lista_premios_cliente_uif', ['id' => $clienteUifId, 'formato' => 'EXCEL']) }}" class="btn btn-app bg-success" target="_blank" rel="noopener">
                    <i class="fas fa-file-excel"></i> Excel
                </a>
                <a href="{{ route('lista_premios_cliente_uif', ['id' => $clienteUifId, 'formato' => 'CSV']) }}" class="btn btn-app bg-warning" target="_blank" rel="noopener">
                    <i class="fas fa-file-csv"></i> Csv
                </a>
            </div>
            @endif
            @if ($puedeAgregarPremioUif)
                @if ($urlAltaPremioUif)
                <a id="agrega_renglon_premio"
                   href="{{ $urlAltaPremioUif }}"
                   class="btn btn-warning ml-auto">
                    <i class="fa fa-plus-circle"></i> + Agrega premio
                </a>
                @else
                <button type="button"
                        id="agrega_renglon_premio"
                        class="btn btn-warning ml-auto"
                        title="Guarda el cliente y abre el alta de premio">
                    <i class="fa fa-plus-circle"></i> + Agrega premio
                </button>
                <input type="hidden" name="ir_a_agregar_premio" id="ir_a_agregar_premio" value="0">
                @endif
            @endif
        </div>
    	<table class="table" id="premio-table">
    		<thead>
    			<tr>
    				<th>Fecha de Entrega</th>
					<th>Sala</th>
					<th>Juego</th>
					<th>Nro. de Tito</th>
					<th style="text-align: right;">Monto Premio</th>
                    <th style="text-align: center; width: 72px;">Foto</th>
    				<th></th>
    			</tr>
    		</thead>
    		<tbody id="tbody-tabla-premio">
		 		@if (isset($data) && isset($data->cliente_premios_uif) && count($data->cliente_premios_uif) > 0)
					@foreach (old('premio', $data->cliente_premios_uif->count() ? $data->cliente_premios_uif : ['']) as $premio)
            			<tr class="item-premio">
                			<td>
                				<input type="hidden" name="premios[]" class="form-control iipremio" readonly value="{{ $loop->index+1 }}" />
								<input type="hidden" name="premio_ids[]" class="form-control premio_id" value="{{ $premio->id ?? '' }}" />
								<input type="datetime" name="fechaentregas[]" class="form-control fechaentrega" value="{{ $premio->fechaentrega->format('d-m-Y H:i:s') }}" />
                			</td>
							<td>
                				<input type="text" name="salas[]" class="form-control sala" readonly value="{{ $premio->salas->nombre }}" />
                			</td>
							<td>
                				<input type="text" name="detalles[]" class="form-control detalle" value="{{ $premio->juegos_uif->nombre }}" />
                			</td>
							<td>
                				<input type="text" name="numerotitos[]" class="form-control numerotito" value="{{ $premio->numerotito }}" />
                			</td>
							<td>
                				<input type="text" name="montopremios[]" class="form-control montopremio" style="text-align: right;" value="{{ number_format((float) ($premio->monto ?? 0), 2, ',', '.') }}" />
                			</td>
                            <td class="text-center align-middle premio-foto-preview">
                                @include('uif.cliente_premio_uif.partials.foto_celda', [
                                    'foto' => $premio->foto ?? null,
                                    'premioId' => $premio->id ?? null,
                                ])
                            </td>
							<td>
								@if (can('editar-cliente-premio-uif', false) && ! $detalleClienteUifRestringido)
                                	<a href="{{ route('edita_cliente_premio_uif', ['id' => $premio->id]) }}?return_cliente_tab=3{{ $suffixConsultaPremios }}" class="btn-accion-tabla tooltipsC" title="Editar este registro">
                                    <i class="fa fa-edit"></i>
                                	</a>
								@endif
								@if (can('editar-cliente-premio-uif', false) && ! $detalleClienteUifRestringido)
                                	<a href="{{route('lista_un_cliente_premio_uif', ['id' => $premio->id])}}" class="btn-accion-tabla tooltipsC" title="Listar el premio">
                                    <i class="fa fa-print"></i>
                                	</a>
								@endif
								@if (can('borrar-cliente-premio-uif', false) && ! $detalleClienteUifRestringido)
									<button style="width: 7%;" type="button" title="Elimina el premio" class="btn-accion-tabla eliminar_premio tooltipsC">
										<i class="fa fa-times-circle text-danger"></i>
									</button>
								@endif
                			</td>
                		</tr>
           			@endforeach
				@endif
       		</tbody>
       	</table>
		@include('uif.cliente_uif.template2')
    </div>
</div>
