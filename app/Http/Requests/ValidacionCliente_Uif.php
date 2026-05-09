<?php

namespace App\Http\Requests;

use App\Models\Uif\Cliente_Uif;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionCliente_Uif extends FormRequest
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

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'nombre' => 'required|max:255',
            'numerodocumento' => [
                'required',
                'max:50',
                function (string $attribute, mixed $value, \Closure $fail) {
                    $nro = trim((string) $value);
                    $ignoreId = $this->resolveClienteUifIdForValidation();

                    $query = Cliente_Uif::query()->where('numerodocumento', $nro);

                    if ($this->filled('tipodocumento_id')) {
                        $query->where('tipodocumento_id', (int) $this->input('tipodocumento_id'));
                    }

                    if ($ignoreId !== null) {
                        $query->where('id', '!=', $ignoreId);
                    }

                    if ($query->exists()) {
                        $fail(__('validation.unique', ['attribute' => $this->attributes()[$attribute] ?? $attribute]));
                    }
                },
            ],
            'fotodocumento' => 'nullable|image|mimes:jpeg,png,jpg,gif,webp|max:10240',
        ];
    }

    /**
     * ID del registro que se está editando (para excluirlo del chequeo de unicidad).
     */
    private function resolveClienteUifIdForValidation(): ?int
    {
        foreach ([
            $this->route('id'),
            $this->route()?->parameter('id'),
        ] as $candidate) {
            $id = $this->normalizeClienteUifRouteId($candidate);
            if ($id !== null) {
                return $id;
            }
        }

        if ($this->isMethod('put') || $this->isMethod('patch')) {
            $id = $this->normalizeClienteUifRouteId($this->input('cliente_uif_id'));
            if ($id !== null) {
                return $id;
            }
        }

        $path = ltrim((string) $this->path(), '/');
        if (preg_match('#^uif/cliente_uif/(\d+)#', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    private function normalizeClienteUifRouteId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value instanceof \Illuminate\Database\Eloquent\Model) {
            $value = $value->getKey();
        }

        $id = filter_var($value, FILTER_VALIDATE_INT);

        return ($id !== false && $id > 0) ? $id : null;
    }

    protected function prepareForValidation()
    {
        if ($this->has('numerodocumento')) {
            $this->merge([
                'numerodocumento' => trim((string) $this->input('numerodocumento')),
            ]);
        }
    }

    public function attributes()
    {
        return [
            'numerodocumento' => 'número de documento',
            'fotodocumento' => 'foto del documento',
        ];
    }
}
