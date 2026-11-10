{{-- Primera firma (o PIN blanqueado): el cajero elige su PIN delante de tesorería. --}}
@if (session('tc_pin_nuevo'))
    <div class="alert alert-warning py-2 px-3 small mb-2">
        El cajero tiene que elegir un PIN propio de {{ \App\Support\Caja\TesoreriaCrownFirmaSupport::PIN_MIN }} a {{ \App\Support\Caja\TesoreriaCrownFirmaSupport::PIN_MAX }} dígitos.
        No puede ser su número ni su legajo, ni dígitos repetidos o seguidos (1111, 1234).
    </div>
    <div class="form-row">
        <div class="col-6 mb-2">
            <input type="password" name="{{ $nuevoName }}" class="form-control form-control-sm" placeholder="PIN nuevo" inputmode="numeric" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" required>
        </div>
        <div class="col-6 mb-2">
            <input type="password" name="{{ $repetirName }}" class="form-control form-control-sm" placeholder="Repetir PIN" inputmode="numeric" autocomplete="new-password" data-lpignore="true" data-1p-ignore="true" required>
        </div>
    </div>
@endif
