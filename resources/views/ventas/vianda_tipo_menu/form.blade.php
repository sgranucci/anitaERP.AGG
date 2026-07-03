<div class="form-group row">
    <label for="nombre" class="col-lg-3 col-form-label requerido">Nombre</label>
    <div class="col-lg-8">
        <input type="text" name="nombre" id="nombre" class="form-control" maxlength="255" required
               value="{{ old('nombre', $data->nombre ?? '') }}">
    </div>
</div>
<div class="form-group row">
    <label for="estado" class="col-lg-3 col-form-label requerido">Estado</label>
    <div class="col-lg-8">
        <select name="estado" id="estado" class="form-control" required>
            <option value="A" {{ old('estado', $data->estado ?? 'A') === 'A' ? 'selected' : '' }}>Activo</option>
            <option value="I" {{ old('estado', $data->estado ?? 'A') === 'I' ? 'selected' : '' }}>Inactivo</option>
        </select>
    </div>
</div>
@if (! empty($data->codigo_anita))
<div class="form-group row">
    <label class="col-lg-3 col-form-label">C&oacute;d. Anita</label>
    <div class="col-lg-8">
        <input type="text" class="form-control" value="{{ $data->codigo_anita }}" readonly>
        <small class="form-text text-muted">Identificador legacy (<code>tipom_codigo</code>). Solo lectura; se actualiza al sincronizar.</small>
    </div>
</div>
@endif

<hr>
<h5 class="mb-3">Art&iacute;culos ofrecidos por d&iacute;a</h5>
<p class="text-muted small">Indique los art&iacute;culos disponibles para cada d&iacute;a de la semana (1 = lunes &hellip; 7 = domingo, seg&uacute;n Anita <code>artm_dia</code>).</p>

<div class="table-responsive">
    <table class="table table-bordered" id="tabla-vianda-semana">
        <thead>
            <tr>
                @foreach ($diasSemana as $dia => $etiqueta)
                <th style="min-width: 180px;">{{ $etiqueta }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            <tr>
                @foreach ($diasSemana as $dia => $etiqueta)
                <td class="align-top p-2 vianda-dia-col" data-dia="{{ $dia }}">
                    @php
                        $lineasDia = [];
                        $oldDia = old('articulo_por_dia.'.$dia);
                        if (is_array($oldDia)) {
                            foreach ($oldDia as $idx => $articuloId) {
                                $lineasDia[] = (object) [
                                    'articulo_id' => $articuloId,
                                    'sku' => old('codigoarticulos_dia.'.$dia.'.'.$idx),
                                    'descripcion' => old('descripcionarticulos_dia.'.$dia.'.'.$idx),
                                ];
                            }
                        } elseif (($articulosPorDia[$dia] ?? collect())->count() > 0) {
                            foreach ($articulosPorDia[$dia] as $lineaModel) {
                                $lineasDia[] = $lineaModel;
                            }
                        }
                        if ($lineasDia === []) {
                            $lineasDia[] = null;
                        }
                    @endphp
                    <div class="vianda-dia-items" id="vianda-dia-items-{{ $dia }}">
                        @foreach ($lineasDia as $idxLinea => $linea)
                            @include('ventas.vianda_tipo_menu.partials.fila_articulo_dia', [
                                'dia' => $dia,
                                'idxLinea' => $idxLinea,
                                'linea' => $linea,
                            ])
                        @endforeach
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger mt-2 agrega-articulo-dia" data-dia="{{ $dia }}">
                        + Art&iacute;culo
                    </button>
                </td>
                @endforeach
            </tr>
        </tbody>
    </table>
</div>

@include('ventas.vianda_tipo_menu.template_articulo_dia')
@include('includes.stock.modalconsultaarticulo')
