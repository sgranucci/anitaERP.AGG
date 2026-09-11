{{-- Destino de mercadería / servicio (retención IIBB). Default Buenos Aires. --}}
@php
    $cpDestinoSupport = \App\Support\Compras\ComprobanteProveedorProvinciaDestinoSupport::class;
    $cpDestinoDefault = $cpDestinoSupport::defaultParaFormulario();
    $cpDestinoModelo = ($data ?? null)?->provinciaDestino;
    $cpDestinoId = old(
        'provincia_destino_id',
        ($data ?? null)?->provincia_destino_id ?: $cpDestinoDefault['id']
    );
    $cpDestinoCodigo = old(
        'provincia_destino_codigo',
        $cpDestinoModelo?->codigo ?? $cpDestinoDefault['codigo']
    );
    $cpDestinoNombre = old(
        'provincia_destino_nombre',
        $cpDestinoModelo?->nombre ?? $cpDestinoDefault['nombre']
    );
    $cpDestinoJur = old(
        'provincia_destino_jurisdiccion',
        $cpDestinoModelo?->jurisdiccion ?? $cpDestinoDefault['jurisdiccion']
    );
@endphp
@include('configuracion.partials.campo_consulta_provincia', [
    'label' => $label ?? 'Destino mercadería',
    'inputName' => 'provincia_destino_id',
    'inputId' => 'provincia_destino_id',
    'provinciaId' => $cpDestinoId,
    'codigo' => $cpDestinoCodigo,
    'nombre' => $cpDestinoNombre,
    'jurisdiccion' => $cpDestinoJur,
    'codigoName' => 'provincia_destino_codigo',
    'codigoId' => 'provincia_destino_codigo',
    'nombreName' => 'provincia_destino_nombre',
    'nombreId' => 'provincia_destino_nombre',
    'col_label' => $col_label ?? 'col-lg-4 control-label text-right pr-2',
    'col_input' => $col_input ?? 'col-lg-8',
    'requerido' => $requerido ?? true,
    'solo_lectura' => $solo_lectura ?? false,
    'help' => $help ?? 'Jurisdicción donde se recibe la mercadería o se presta el servicio. Define si se retiene IIBB (ARBA = Buenos Aires).',
])
