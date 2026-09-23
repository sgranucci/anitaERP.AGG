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
    $premiosFicha = isset($data) ? collect($data->cliente_premios_uif ?? []) : collect();
    $premiosTotal = (int) ($data->cliente_premios_uif_count ?? $premiosFicha->count());
    $premiosMostrados = $premiosFicha->count();
    $urlPremiosFicha = $clienteUifId
        ? route('premios_ficha_cliente_uif', ['id' => $clienteUifId])
        : '';
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
        @if ($premiosTotal > 0)
            <p class="text-muted small mb-2" id="premios-ficha-resumen"
               data-total="{{ $premiosTotal }}"
               data-mostrados="{{ $premiosMostrados }}">
                @if ($premiosTotal > $premiosMostrados)
                    Mostrando <span class="premios-ficha-n">{{ $premiosMostrados }}</span> más recientes de {{ number_format($premiosTotal, 0, ',', '.') }} premios.
                    Usá «Cargar más anteriores» para seguir viendo el historial (no se envían al Actualizar el cliente).
                @else
                    {{ number_format($premiosTotal, 0, ',', '.') }} premio(s). Se gestionan por su propia pantalla; «Actualizar» el cliente no los regraba.
                @endif
            </p>
        @endif
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
    		<tbody id="tbody-tabla-premio"
                   data-premios-url="{{ $urlPremiosFicha }}"
                   data-premios-offset="{{ $premiosMostrados }}"
                   data-premios-total="{{ $premiosTotal }}"
                   data-premios-origen="{{ $consultaPremiosCliente ? 'modal_consulta' : '' }}">
		 		@if ($premiosFicha->count() > 0)
					{{-- Solo lectura en ficha: sin name= (no van en el POST del cliente). --}}
					@foreach ($premiosFicha as $premio)
                        @include('uif.cliente_uif.partials.premio_renglon_ficha', [
                            'premio' => $premio,
                            'indice' => $loop->iteration,
                            'detalleClienteUifRestringido' => $detalleClienteUifRestringido,
                            'suffixConsultaPremios' => $suffixConsultaPremios,
                        ])
           			@endforeach
				@endif
       		</tbody>
       	</table>
        @if ($premiosTotal > $premiosMostrados && $urlPremiosFicha !== '')
            <div class="text-center mb-2">
                <button type="button" id="cargar-mas-premios-ficha" class="btn btn-outline-secondary btn-sm">
                    <i class="fa fa-history"></i> Cargar más anteriores
                    (<span class="text-muted">(<span id="premios-ficha-restantes">{{ number_format($premiosTotal - $premiosMostrados, 0, ',', '.') }}</span> restantes)</span>
                </button>
            </div>
        @endif
		@include('uif.cliente_uif.template2')
    </div>
</div>
