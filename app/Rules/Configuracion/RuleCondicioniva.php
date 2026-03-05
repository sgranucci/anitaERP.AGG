<?php

namespace App\Rules\Configuracion;

use Illuminate\Contracts\Validation\Rule;

class RuleCondicioniva implements Rule
{
  	private $valores;

    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct($valores)
    {
	  	$this->valores = $valores;	  
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
	  	$fl_encontro = false;
	  	foreach ($this->valores as $clave => $valor)
		{
		  	if ($clave == $value)
			{
			  	$fl_encontro = true;
				break;
			}
		}
		return($fl_encontro);
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
