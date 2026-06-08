<span class="badge badge-{{ match($estado) {
    'PENDIENTE' => 'warning',
    'SUSPENDIDO' => 'secondary',
    'CERRADO_PARCIAL' => 'info',
    'CERRADO_TOTAL' => 'success',
    'ANULADO' => 'danger',
    default => 'light',
} }}">
    {{ \App\Models\Stock\Recuento::etiquetaEstado($estado) }}
</span>
