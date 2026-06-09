<?php

namespace App\Http\Requests;

use App\Repositories\Configuracion\EmpresaRepositoryInterface;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ValidacionEstacionamientoListaPrecio extends FormRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        $id = $this->route('id');
        $empresaId = (int) $this->input('empresa_id');

        return [
            'empresa_id' => 'required|exists:empresa,id',
            'categoria_automovil_id' => [
                'required',
                'exists:categoria_automovil_estacionamiento,id',
                Rule::unique('lista_precio_estacionamiento', 'categoria_automovil_id')
                    ->ignore($id)
                    ->where(fn ($q) => $q->where('empresa_id', $empresaId)),
            ],
            'moneda_id' => 'required|exists:moneda,id',
            'precio_renglones' => 'nullable|array',
            'precio_renglones.*.item_id' => 'nullable|exists:item_estacionamiento,id',
            'precio_renglones.*.linea_id' => 'nullable|integer',
            'precio_renglones.*.precio' => 'nullable|numeric|min:0',
            'precio_renglones.*.fecha_vigencia' => 'nullable|date',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $empresaId = (int) $this->input('empresa_id');
            if ($empresaId <= 0) {
                return;
            }

            $empresaRepository = app(EmpresaRepositoryInterface::class);
            if (! $empresaRepository->empresaIdPermitida($empresaId)) {
                $validator->errors()->add('empresa_id', 'No tiene acceso a la empresa seleccionada.');
            }

            $renglones = (array) $this->input('precio_renglones', []);
            $renglonesValidos = 0;
            $combinaciones = [];

            foreach ($renglones as $idx => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $itemId = (int) ($row['item_id'] ?? 0);
                $precioRaw = $row['precio'] ?? null;
                $fecha = trim((string) ($row['fecha_vigencia'] ?? ''));

                if ($itemId <= 0) {
                    continue;
                }

                if ($precioRaw === null || $precioRaw === '') {
                    continue;
                }

                if ($fecha === '') {
                    $validator->errors()->add("precio_renglones.{$idx}.fecha_vigencia", 'Cada precio debe tener fecha de vigencia.');

                    continue;
                }

                $clave = $itemId.'|'.$fecha;
                if (isset($combinaciones[$clave])) {
                    $validator->errors()->add("precio_renglones.{$idx}.fecha_vigencia", 'No puede repetir la misma fecha de vigencia para un ítem.');
                }
                $combinaciones[$clave] = true;
                $renglonesValidos++;
            }

            if ($renglonesValidos === 0) {
                $validator->errors()->add('precio_renglones', 'Debe cargar al menos un precio con su vigencia.');
            }

            $itemIds = array_values(array_filter(array_map(
                fn ($row) => is_array($row) ? (int) ($row['item_id'] ?? 0) : 0,
                $renglones
            ), fn ($id) => $id > 0));

            if ($itemIds !== []) {
                $invalidos = \DB::table('item_estacionamiento')
                    ->whereIn('id', $itemIds)
                    ->where('empresa_id', '!=', $empresaId)
                    ->count();

                if ($invalidos > 0) {
                    $validator->errors()->add('precio_renglones', 'Todos los ítems deben pertenecer a la empresa seleccionada.');
                }
            }
        });
    }

    public function messages()
    {
        return [
            'categoria_automovil_id.unique' => 'Ya existe una lista de precios para esta empresa y categoría.',
        ];
    }
}
