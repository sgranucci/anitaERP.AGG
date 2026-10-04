<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use App\Models\Admin\Menu;

class ValidarCampoUrl implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
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
        if ($value == '#') {
            return true;
        }

        $id = request()->route('id');
        if ($id) {
            $actual = Menu::where('id', $id)->value($attribute);
            if ($actual !== null && (string) $actual === (string) $value) {
                return true;
            }
        }

        $menu = Menu::where($attribute, $value)->where('id', '!=', $id)->get();

        return $menu->isEmpty();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return 'Esta url ya esta asignada';
    }
}
