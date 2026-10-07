@php
    use App\Support\Compras\ProveedorLeyendaCuentaCorrienteSupport;
    $tildeLeyendaId = $inputId ?? 'imprimir_leyenda_cuenta_corriente';
@endphp
@if (ProveedorLeyendaCuentaCorrienteSupport::activa())
    <div class="tilde-leyenda-ferli {{ $claseExtra ?? 'mt-2' }}">
        <div class="custom-control custom-checkbox">
            <input type="checkbox"
                   class="custom-control-input"
                   id="{{ $tildeLeyendaId }}"
                   checked
                   disabled>
            <label class="custom-control-label" for="{{ $tildeLeyendaId }}">
                Imprimir leyenda en la cuenta corriente
            </label>
        </div>
        <small class="text-muted d-block">Siempre activo en Ferli. {{ $ayuda ?? 'La leyenda del proveedor sale al listar o imprimir la cuenta corriente.' }}</small>
    </div>
    <style>
        .tilde-leyenda-ferli .custom-control-input:disabled:checked ~ .custom-control-label::before {
            background-color: #007bff;
            border-color: #007bff;
        }
        .tilde-leyenda-ferli .custom-control-input:disabled ~ .custom-control-label {
            color: #212529;
            cursor: default;
        }
    </style>
@endif
