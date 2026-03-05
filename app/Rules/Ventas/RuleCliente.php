<?php

namespace App\Rules\Ventas;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use App\Traits\ValidacionCuit;
use App\Models\Ventas\Cliente;

class RuleCliente implements Rule
{
  	private $campo;
	use ValidacionCuit;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($campo)
    {
	  	$this->campo = $campo;	  
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
		case 'numerodocumento':
			$cc = $this->ValidacionCuit($value);

			if (!$cc && (strlen($value) != 11 && strlen($value) != 13))
				$cc = true;
			break;
		case 'retieneiva':
			$cc = Arr::has(Cliente::$enumRetieneiva, $value);
			break;
		case 'condicioniibb':
			$cc = Arr::has(Cliente::$enumCondicioniibb, $value);
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
