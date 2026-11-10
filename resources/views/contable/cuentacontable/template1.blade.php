<template id="template-renglon-centrocosto">
    @include('contable.cuentacontable.partials.renglon_centrocosto', [
        'fila' => ['id' => '', 'codigo' => '', 'nombre' => ''],
        'soloLectura' => false,
    ])
</template>
