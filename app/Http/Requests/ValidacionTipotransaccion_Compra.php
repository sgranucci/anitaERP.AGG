<?php

namespace App\Http\Requests;

use App\Models\Compras\Tipotransaccion_Compra;
use App\Models\Ventas\ArcaTipoComprobante;
use App\Services\Arca\ArcaTiposComprobanteCatalogoService;
use Illuminate\Foundation\Http\FormRequest;

class ValidacionTipotransaccion_Compra extends FormRequest
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

    protected function prepareForValidation(): void
    {
        if (! $this->exists('codigoafip')) {
            return;
        }

        $this->merge([
            'codigoafip' => ArcaTiposComprobanteCatalogoService::normalizarCodigoAfip((string) $this->input('codigoafip')),
        ]);
    }

    public function rules()
    {
        return [
            'nombre' => 'required|max:255|unique:tipotransaccion_compra,nombre,' . $this->route('id'),
            'abreviatura' => 'required|max:5|unique:tipotransaccion_compra,abreviatura,' . $this->route('id'),
            'codigoafip' => ['required', 'string', 'max:10', function (string $attribute, mixed $value, \Closure $fail): void {
                if (! ArcaTipoComprobante::query()->exists()) {
                    return;
                }

                $normalizado = ArcaTiposComprobanteCatalogoService::normalizarCodigoAfip((string) $value);
                $enCatalogo = ArcaTipoComprobante::query()
                    ->where('codigo_afip', $normalizado)
                    ->exists();
                if ($enCatalogo) {
                    return;
                }

                $id = $this->route('id');
                if ($id) {
                    $actual = (string) (Tipotransaccion_Compra::query()->whereKey($id)->value('codigoafip') ?? '');
                    if ($actual !== '' && ArcaTiposComprobanteCatalogoService::normalizarCodigoAfip($actual) === $normalizado) {
                        return;
                    }
                }

                $fail('El tipo de comprobante no figura en el catálogo ARCA de los web services activos.');
            }],
        ];
    }
}
