<?php

namespace App\Rules\Compras;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use App\Traits\ValidacionCuit;
use App\Models\Compras\Proveedor;

class RuleProveedor implements Rule
{
  	private $campo, $tipoalta;
	  use ValidacionCuit;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($campo, $tipoalta = null)
    {
	  	$this->campo = $campo;
      
      $this->tipoalta = $tipoalta;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
      $cc = true;
      switch($this->campo)
      {
      case 'nroinscripcion':
        $cc = $this->ValidacionCuit($value);

        // Valida la existencia en otro proveedor
        if ($cc)
        {

        }
        break;
      case 'retieneiva':
        $cc = Arr::has(Proveedor::$enumRetieneiva, $value);
        break;
      case 'nroIIBB':
        if ($this->tipoalta)
        {
          if ($this->tipoalta == substr(config("proveedor.tipoalta"),0,1))
            $cc = true;
          else
          {
            $cc = request()->has($attribute);
          }
        }
        else
          $cc = true;
        break;
      }
      return($cc);
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Error en campo :attribute.';
    }
}
