<div class="form-group row">
    <label for="codigo" class="col-lg-3 col-form-label">C&oacute;digo</label>
    <div class="col-lg-3">
        @if (isset($data))
            <input type="text" id="codigo" class="form-control" value="{{ $data->codigo }}" readonly/>
        @else
            <input type="number" name="codigo" id="codigo" class="form-control" min="1"
                   value="{{ old('codigo') }}"
                   placeholder="Autom&aacute;tico si se deja vac&iacute;o"/>
        @endif
    </div>
</div>
<div class="form-group row">
    <label for="descripcion" class="col-lg-3 col-form-label requerido">Descripci&oacute;n</label>
    <div class="col-lg-6">
        <input type="text" name="descripcion" id="descripcion" class="form-control" maxlength="30" required
               value="{{ old('descripcion', $data->descripcion ?? '') }}"/>
    </div>
</div>

<hr>
<h5 class="mb-3">Per&iacute;odos de vacaciones</h5>
<p class="text-muted small">Cada l&iacute;nea es un tramo de <strong>antig&uuml;edad en a&ntilde;os</strong> (desde inclusive, hasta exclusive) con los d&iacute;as de vacaciones que le corresponden. Ej.: 0 a 5 = 14, 5 a 10 = 21, 10 a 20 = 28, 20 a 99 = 35. El primer tramo es la l&iacute;nea 0. El n&uacute;mero de l&iacute;nea se completa solo si lo deja vac&iacute;o.</p>

<table class="table table-bordered table-sm" id="tabla-vacacion-periodos">
    <thead style="background-color:#85C1E9; color:#17202A;">
        <tr>
            <th style="width: 10%;">N&deg; l&iacute;nea</th>
            <th style="width: 18%;">Antig. desde (a&ntilde;os)</th>
            <th style="width: 18%;">Antig. hasta (a&ntilde;os)</th>
            <th style="width: 18%;">Tipo d&iacute;a</th>
            <th style="width: 14%;">Cant. d&iacute;as</th>
            <th style="width: 8%;"></th>
        </tr>
    </thead>
    <tbody id="tbody-vacacion-periodos">
        @php
            $lineasForm = [];
            if (old('anios_desde') !== null) {
                foreach (old('anios_desde', []) as $idx => $desde) {
                    $lineasForm[] = (object) [
                        'nro_linea' => old('nro_linea.'.$idx),
                        'anios_desde' => $desde,
                        'anios_hasta' => old('anios_hasta.'.$idx),
                        'tipo_dia' => old('tipo_dia.'.$idx),
                        'cantidad_dias' => old('cantidad_dias.'.$idx),
                    ];
                }
            } elseif (($data->periodos ?? collect())->count() > 0) {
                foreach ($data->periodos as $lineaModel) {
                    $lineasForm[] = $lineaModel;
                }
            } else {
                $lineasForm[] = null;
            }
        @endphp
        @foreach ($lineasForm as $idxLinea => $linea)
            @include('sueldos.vacacion.partials.fila_periodo', ['linea' => $linea, 'idxLinea' => $idxLinea])
        @endforeach
    </tbody>
</table>
@include('sueldos.vacacion.template_periodo')
<div class="row mb-3">
    <div class="col-md-12">
        <button type="button" id="agrega_renglon_vacacion_periodo" class="pull-right btn btn-danger">+ Agrega per&iacute;odo</button>
    </div>
</div>
