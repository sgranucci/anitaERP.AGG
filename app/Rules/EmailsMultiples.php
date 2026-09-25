<?php

namespace App\Rules;

use App\Support\Mail\EmailsMultiplesSupport;
use Illuminate\Contracts\Validation\Rule;

/**
 * Acepta vacío, un email o varios separados por , ; o espacio.
 */
class EmailsMultiples implements Rule
{
    private string $detalle = '';

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if ($value === null || trim((string) $value) === '') {
            return true;
        }

        $invalidos = EmailsMultiplesSupport::invalidos((string) $value);
        if ($invalidos === []) {
            return true;
        }

        $this->detalle = implode(', ', $invalidos);

        return false;
    }

    public function message(): string
    {
        if ($this->detalle !== '') {
            return 'Dirección(es) de correo no válida(s): '.$this->detalle
                .'. Separe varios con ; o ,';
        }

        return 'Ingrese uno o más correos válidos, separados por ; o ,';
    }
}
