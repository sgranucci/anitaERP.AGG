<?php

namespace App\Http\Requests;

use App\Models\Uif\Cliente_Uif;
use App\Repositories\Uif\Cliente_Riesgo_UifRepository;
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

                    // Comparar con TRIM para alinear con prepareForValidation() y valores guardados con espacios.
                    $query = Cliente_Uif::query()->whereRaw('TRIM(numerodocumento) = ?', [$nro]);

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
            'periodos' => 'sometimes|array',
            'periodos.*' => [
                function (string $attribute, mixed $value, \Closure $fail) {
                    if ($value === null || (is_string($value) && trim($value) === '')) {
                        $fail('Debe indicar año y mes del período de riesgo.');

                        return;
                    }
                    $r = Cliente_Riesgo_UifRepository::intentarNormalizarPeriodoRiesgoUif($value);
                    if ($r === false) {
                        $fail('El período de riesgo no es válido. Use AAAA-MM o un formato equivalente reconocible.');

                        return;
                    }
                    if ($r === '') {
                        $fail('Debe indicar año y mes del período de riesgo.');
                    }
                },
            ],
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

        // Actualización: el formulario envía POST + _method=PUT y campo oculto cliente_uif_id.
        if ($this->isClienteUifUpdateRequest()) {
            $id = $this->normalizeClienteUifRouteId($this->input('cliente_uif_id'));
            if ($id !== null) {
                return $id;
            }
        }

        // Sin anclaje al inicio del path (compatible con prefijo de subcarpeta / proxy).
        $path = ltrim((string) $this->path(), '/');
        if (preg_match('#cliente_uif/(\d+)#', $path, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    /**
     * Detecta guardado en edición (PUT/PATCH o POST con spoofing _method=PUT hacia uif/cliente_uif/{id}).
     */
    private function isClienteUifUpdateRequest(): bool
    {
        if ($this->isMethod('put') || $this->isMethod('patch')) {
            return true;
        }

        return strtoupper((string) $this->input('_method')) === 'PUT';
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

    protected function passedValidation()
    {
        if (! $this->has('periodos') || ! is_array($this->input('periodos'))) {
            return;
        }
        $normalizados = [];
        foreach ($this->input('periodos') as $p) {
            $n = Cliente_Riesgo_UifRepository::intentarNormalizarPeriodoRiesgoUif($p);
            $normalizados[] = ($n === false) ? '' : $n;
        }
        $this->merge(['periodos' => $normalizados]);
    }

    public function attributes()
    {
        return [
            'numerodocumento' => 'número de documento',
            'fotodocumento' => 'foto del documento',
            'periodos.*' => 'período (riesgo)',
            'so_uif_id' => 'sujeto obligado',
            'pep_uif_id' => 'expuesto políticamente',
            'fechafirmapep' => 'fecha de última firma PEP',
        ];
    }
}
