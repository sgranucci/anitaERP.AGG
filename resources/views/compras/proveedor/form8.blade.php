<div id="tab8" class="card card-outline card-info form8 tab-content" style="display: none">
    <div class="card-body">
        @include('seguridad.ingreso_proveedor.partials.solapa_vinculada', [
            'tickets' => $tickets_ingreso ?? collect(),
            'url_nuevo_ticket_ingreso' => $url_nuevo_ticket_ingreso ?? null,
            'ingresoContexto' => [
                'proveedor_id' => $data->id ?? null,
            ],
        ])
    </div>
</div>
