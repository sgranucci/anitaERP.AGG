<?php

namespace App\Rules\Ventas;

use App\Support\Ventas\ClienteAnitaColisionSupport;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

/**
 * Impide reutilizar un código Anita (cliente.codigo / clim_cliente) en otro cliente.
 */
class RuleClienteCodigoUnico implements Rule
{
    private ?int $excluirClienteId;

    private string $mensaje = 'El código Anita ya está en uso en otro cliente.';

    public function __construct(?int $excluirClienteId = null)
    {
        $this->excluirClienteId = $excluirClienteId;
    }

    public function passes($attribute, $value): bool
    {
        $codigo = trim((string) $value);
        if ($codigo === '') {
            return true;
        }

        if (! ctype_digit($codigo)) {
            $query = DB::table('cliente')->where('codigo', $codigo);
            if ($this->excluirClienteId !== null && $this->excluirClienteId > 0) {
                $query->where('id', '!=', $this->excluirClienteId);
            }

            return ! $query->exists();
        }

        $numero = (int) ltrim($codigo, '0');
        if ($numero <= 0) {
            $numero = (int) $codigo;
        }
        $variantes = ClienteAnitaColisionSupport::variantesCodigoNumerico($numero);
        if ($variantes === []) {
            return true;
        }

        $query = DB::table('cliente')->whereIn('codigo', $variantes);
        if ($this->excluirClienteId !== null && $this->excluirClienteId > 0) {
            $query->where('id', '!=', $this->excluirClienteId);
        }
        if ($query->exists()) {
            $this->mensaje = 'El código Anita '.$codigo.' ya está asignado a otro cliente del ERP.';

            return false;
        }

        // En alta, también bloquear si ya existe en climae (evitar pisar Anita).
        if (($this->excluirClienteId === null || $this->excluirClienteId <= 0)
            && ClienteAnitaColisionSupport::existeCodigoEnClimae($numero)
        ) {
            $this->mensaje = 'El código Anita '.$codigo.' ya existe en Anita (climae). Elija otro o deje la numeración automática.';

            return false;
        }

        return true;
    }

    public function message(): string
    {
        return $this->mensaje;
    }
}
