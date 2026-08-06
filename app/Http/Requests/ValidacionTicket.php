<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionTicket extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    protected function prepareForValidation()
    {
        if (! $this->filled('subcategoria_ticket_id')) {
            $this->merge(['subcategoria_ticket_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'sector_id' => 'required',
            'sala_id' => 'required',
            'categoria_ticket_id' => 'required',
            'subcategoria_ticket_id' => 'nullable|integer|exists:subcategoria_ticket,id',
            'areadestino_id' => 'required',
            'titulo' => 'required|string|max:255',
            'comentario' => 'required|string',
        ];
    }
}
