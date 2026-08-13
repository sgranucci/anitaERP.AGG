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
        if (! $this->filled('categoria_ticket_id')) {
            $this->merge(['categoria_ticket_id' => null]);
        }
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
            'categoria_ticket_id' => 'nullable|integer',
            'subcategoria_ticket_id' => 'nullable|integer|exists:subcategoria_ticket,id',
            'areadestino_id' => 'required',
            'usuario_id' => 'nullable|integer',
            'titulo' => 'required|string|max:255',
            'comentario' => 'required|string',
        ];
    }

    public function attributes()
    {
        return [
            'sector_id' => 'sector',
            'sala_id' => 'sala',
            'areadestino_id' => 'área de destino',
            'categoria_ticket_id' => 'categoría',
            'subcategoria_ticket_id' => 'subcategoría',
            'titulo' => 'título',
            'comentario' => 'comentario',
        ];
    }
}
