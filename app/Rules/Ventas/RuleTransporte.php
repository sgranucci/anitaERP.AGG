<?php

namespace App\Rules\Ventas;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Arr;
use App\Traits\ValidacionCuit;

class RuleTransporte implements Rule
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
		switch($this->campo)
		{
		case 'nroinscripcion':
			$cc = $this->ValidacionCuit($value);
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
