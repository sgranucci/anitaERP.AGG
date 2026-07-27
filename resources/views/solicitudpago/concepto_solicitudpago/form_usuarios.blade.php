<p class="text-muted mb-3">
    &Aacute;rbol de aprobaci&oacute;n de las solicitudes de pago con este concepto:
    nivel, usuario firmante y monto desde el cual aplica el nivel.
    Al emitir una SP se notifica a los firmantes del primer nivel aplicable al monto.
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered" id="concepto-usuario-table">
        <thead class="thead-light">
            <tr>
                <th style="width: 6%;">#</th>
                <th style="width: 10%;">Nivel</th>
                <th style="width: 54%;">Usuario</th>
                <th style="width: 18%;">Desde monto</th>
                <th style="width: 8%;"></th>
            </tr>
        </thead>
        <tbody id="tbody-concepto-usuario-table">
            @php
                $filasUsu = old('niveles') !== null
                    ? collect(old('niveles', []))->map(function ($nivel, $i) {
                        return (object) [
                            'nivel' => $nivel,
                            'usuario_id' => old('usuario_ids.'.$i),
                            'desde_monto' => old('desdemontos.'.$i),
                            'usuarios' => null,
                        ];
                    })
                    : collect(isset($data) ? ($data->usuarios ?? []) : []);
            @endphp
            @foreach ($filasUsu as $fila)
                @include('solicitudpago.concepto_solicitudpago.partials.fila_usuario', ['fila' => $fila, 'index' => $loop->iteration])
            @endforeach
        </tbody>
    </table>
</div>
@include('solicitudpago.concepto_solicitudpago.partials.template_usuario')
<div class="row mt-2">
    <div class="col-12 text-right">
        <button type="button" id="agrega_renglon_concepto_usuario" class="btn btn-outline-danger btn-sm">
            <i class="fa fa-plus"></i> Agregar usuario
        </button>
    </div>
</div>
@include('includes.admin.modalconsultausuario')
