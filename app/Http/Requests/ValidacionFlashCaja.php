<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionFlashCaja extends FormRequest
{
    private const CAMPOS_DECIMAL = [
        'show',
        'ayb',
        'estac',
        'vending',
        'bingo_total_venta',
        'bingo_resultado',
        'slot_coin_in',
        'slot_d',
        'slot_r',
        'soft_count',
        'hard_count',
        'win_ol_slot',
        'rul_coin_in',
        'rul_d',
        'rul_r',
        'soft_rul',
        'hard_rul',
        'win_ol_rul',
        'cotizacion',
    ];

    private const CAMPOS_ENTERO = [
        'att',
        'pos_online',
        'cant_vehic',
        'bingo_cant_carton',
        'cant_slots',
        'cant_rul',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (self::CAMPOS_DECIMAL as $campo) {
            if (! $this->exists($campo)) {
                continue;
            }
            $merge[$campo] = self::parseNumeroAr($this->input($campo), $campo === 'cotizacion' ? 4 : 2);
        }

        foreach (self::CAMPOS_ENTERO as $campo) {
            if (! $this->exists($campo)) {
                continue;
            }
            $merge[$campo] = (int) round(self::parseNumeroAr($this->input($campo), 0));
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function rules(): array
    {
        $id = $this->route('id');

        return [
            'empresa_id' => ['required', 'integer', 'min:1'],
            'fecha' => [
                'required',
                'date',
                Rule::unique('flash_caja', 'fecha')
                    ->where(fn ($q) => $q->where('empresa_id', (int) $this->input('empresa_id')))
                    ->ignore($id),
            ],
            'att' => ['nullable', 'integer', 'min:0'],
            'comentario' => ['nullable', 'string', 'max:30'],
            'cotizacion' => ['nullable', 'numeric', 'min:0'],
            'pos_online' => ['nullable', 'integer', 'min:0'],
            'show' => ['nullable', 'numeric'],
            'recalcular' => ['nullable', 'boolean'],
            'flash_valores_desde_formulario' => ['nullable', 'boolean'],
            'ayb' => ['nullable', 'numeric'],
            'estac' => ['nullable', 'numeric'],
            'vending' => ['nullable', 'numeric'],
            'bingo_total_venta' => ['nullable', 'numeric'],
            'bingo_resultado' => ['nullable', 'numeric'],
            'slot_coin_in' => ['nullable', 'numeric'],
            'slot_d' => ['nullable', 'numeric'],
            'slot_r' => ['nullable', 'numeric'],
            'soft_count' => ['nullable', 'numeric'],
            'hard_count' => ['nullable', 'numeric'],
            'win_ol_slot' => ['nullable', 'numeric'],
            'rul_coin_in' => ['nullable', 'numeric'],
            'rul_d' => ['nullable', 'numeric'],
            'rul_r' => ['nullable', 'numeric'],
            'soft_rul' => ['nullable', 'numeric'],
            'hard_rul' => ['nullable', 'numeric'],
            'win_ol_rul' => ['nullable', 'numeric'],
            'cant_vehic' => ['nullable', 'integer', 'min:0'],
            'bingo_cant_carton' => ['nullable', 'integer', 'min:0'],
            'cant_slots' => ['nullable', 'integer', 'min:0'],
            'cant_rul' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function attributes(): array
    {
        return [
            'empresa_id' => 'empresa',
            'fecha' => 'fecha',
            'att' => 'asistencia',
            'comentario' => 'comentario',
        ];
    }

    /**
     * Acepta 1234.56, 1.234,56 o 1.234.567.
     */
    public static function parseNumeroAr(mixed $valor, int $decimales = 2): float
    {
        if ($valor === null || $valor === '') {
            return 0.0;
        }

        if (is_int($valor) || is_float($valor)) {
            return round((float) $valor, $decimales);
        }

        $t = trim(str_replace(' ', '', (string) $valor));
        if ($t === '') {
            return 0.0;
        }

        if (str_contains($t, ',')) {
            $t = str_replace('.', '', $t);
            $t = str_replace(',', '.', $t);
        } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $t) === 1) {
            $t = str_replace('.', '', $t);
        }

        if (! is_numeric($t)) {
            return 0.0;
        }

        return round((float) $t, $decimales);
    }
}
