<p class="text-muted mb-3">
    &Aacute;rbol de aprobaci&oacute;n de las solicitudes de pago con este concepto:
    nivel, usuario firmante, monto desde el cual aplica y estado al aprobar
    (convenci&oacute;n: 1=Emitida auto, 2=Controlada, 3=Autorizada, 4=Aviso pago / IE).
    El nivel <strong>Aviso pago (IE)</strong> solo notifica a pagadores (mail con link a Ingresos/egresos);
    el estado <strong>Pagada</strong> lo registra el IE/OP, no el &aacute;rbol.
    Varios firmantes del mismo nivel: alcanza con que uno apruebe (como el &aacute;rbol general).
</p>
<div class="table-responsive">
    <table class="table table-sm table-bordered" id="concepto-usuario-table">
        <thead class="thead-light">
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 8%;">Nivel</th>
                <th style="width: 42%;">Usuario</th>
                <th style="width: 14%;">Desde monto</th>
                <th style="width: 18%;">Estado al aprobar</th>
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
                            'documento_estado_al_aprobar' => old('documento_estado_al_aprobar.'.$i),
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
