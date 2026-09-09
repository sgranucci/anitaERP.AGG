<?php

namespace App\Http\Requests;

use App\Support\Ticket\TicketEmpresaSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

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

        $empresaId = (int) $this->input('empresa_id', 0);
        if ($empresaId > 0) {
            return;
        }

        $salaId = (int) $this->input('sala_id', 0);
        $empresaExistente = null;
        $ticketId = (int) ($this->route('id') ?? 0);
        if ($ticketId > 0) {
            $ticket = DB::table('ticket')->where('id', $ticketId)->first(['empresa_id', 'sala_id']);
            if ($ticket) {
                $empresaExistente = (int) ($ticket->empresa_id ?? 0) ?: null;
                if ($salaId <= 0) {
                    $salaId = (int) ($ticket->sala_id ?? 0);
                }
            }
        }

        $resuelto = TicketEmpresaSupport::resolver(0, $salaId, $empresaExistente);
        if ($resuelto) {
            $this->merge(['empresa_id' => $resuelto]);
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
            'empresa_id' => 'required|integer|exists:empresa,id',
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
            'empresa_id' => 'empresa',
            'areadestino_id' => 'área de destino',
            'categoria_ticket_id' => 'categoría',
            'subcategoria_ticket_id' => 'subcategoría',
            'titulo' => 'título',
            'comentario' => 'comentario',
        ];
    }
}
