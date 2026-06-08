@if((string)($producto->numeroparte ?? '0') === '1')
<div id="form9" class="form9" style="display:none;">
    <div class="card card-outline card-info mt-2">
        <div class="card-header">
            <h3 class="card-title"><i class="fa fa-barcode"></i> Números de parte única</h3>
            <div class="card-tools">
                @if(!empty($puedeActualizarArticulo))
                <button type="button" class="btn btn-sm btn-primary" id="btn-agregar-parte-unica">
                    <i class="fa fa-plus"></i> Asignar siguiente NPU
                </button>
                @endif
            </div>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-2">
                Secuencia global (<code>articulo_parte_unica</code>). Sincronizado con Anita <code>stk_parte_unica</code> (base_admin).
                @if(isset($partesUnicasTotal))
                    Total registrados: <strong>{{ number_format($partesUnicasTotal, 0, ',', '.') }}</strong>
                @endif
            </p>
            <div id="partes-unicas-loading" class="text-center py-3" style="display:none;">
                <i class="fa fa-spinner fa-spin"></i> Cargando…
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-striped" id="tabla-partes-unicas">
                    <thead>
                        <tr>
                            <th>Nº parte</th>
                            <th>Fecha alta</th>
                            @if(!empty($puedeActualizarArticulo))
                            <th style="width:80px"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody id="tbody-partes-unicas"></tbody>
                </table>
            </div>
            <div id="partes-unicas-paginacion" class="d-flex justify-content-center"></div>
        </div>
    </div>
</div>
<input type="hidden" id="articulo-partes-unicas-url" value="{{ url('stock/articulo/'.$producto->id.'/partes-unicas') }}">
<input type="hidden" id="articulo-partes-unicas-puede-editar" value="{{ !empty($puedeActualizarArticulo) ? '1' : '0' }}">
@endif
