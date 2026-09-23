<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ValidacionLocalVenta extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $id = (int) $this->route('id');

        return [
            'codigo' => 'required|string|max:20|unique:local_venta,codigo,'.($id ?: 'NULL').',id',
            'nombre' => 'required|string|max:120',
            'activo' => 'nullable|boolean',
            'empresa_id' => 'nullable|integer|exists:empresa,id',
            'puntoventa_ids' => 'required|array|min:1',
            'puntoventa_ids.*' => 'integer|exists:puntoventa,id',
            'puntoventa_id' => 'nullable|integer|exists:puntoventa,id',
            'deposito_id' => 'required|integer|exists:depmae,id',
            'listaprecio_id' => 'nullable|integer|exists:listaprecio,id',
            'tipotransaccion_fac_id' => 'nullable|integer|exists:tipotransaccion,id',
            'tipotransaccion_nc_id' => 'nullable|integer|exists:tipotransaccion,id',
            'tipotransaccion_caja_id' => 'nullable|integer',
            'tipotransaccion_caja_devolucion_id' => 'nullable|integer',
            'cuentacaja_efectivo_id' => 'nullable|integer|exists:cuentacaja,id',
            'cuentacontable_venta_id' => 'nullable|integer|exists:cuentacontable,id',
            'anita_servidor' => 'nullable|string|max:40',
            'anita_ifx_server' => 'nullable|string|max:40',
            'anita_deposito' => 'nullable|integer',
            'observacion' => 'nullable|string',
            'pdf_web' => 'nullable|string|max:500',
            'pdf_imp_internos' => 'nullable|string|max:200',
            'pdf_seguridad_higiene' => 'nullable|string|max:200',
            'pdf_habilitacion' => 'nullable|string|max:200',
            'pdf_lugar' => 'nullable|string|max:80',
            'pdf_inicio_actividad' => 'nullable|string|max:40',
            'cuentacaja_ids' => 'nullable|array',
            'cuentacaja_ids.*' => 'integer|exists:cuentacaja,id',
        ];
    }

    protected function prepareForValidation(): void
    {
        $pvIds = collect($this->input('puntoventa_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $defaultId = (int) $this->input('puntoventa_id', 0);
        if ($defaultId <= 0 || ! in_array($defaultId, $pvIds, true)) {
            $defaultId = $pvIds[0] ?? 0;
        }

        $cuentaIds = collect($this->input('cuentacaja_ids', []))
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $this->merge([
            'activo' => $this->boolean('activo'),
            'puntoventa_ids' => $pvIds,
            'puntoventa_id' => $defaultId > 0 ? $defaultId : null,
            'cuentacaja_ids' => $cuentaIds,
            'listaprecio_id' => $this->filled('listaprecio_id') ? (int) $this->input('listaprecio_id') : null,
            'tipotransaccion_fac_id' => $this->filled('tipotransaccion_fac_id') ? (int) $this->input('tipotransaccion_fac_id') : null,
            'tipotransaccion_nc_id' => $this->filled('tipotransaccion_nc_id') ? (int) $this->input('tipotransaccion_nc_id') : null,
            'cuentacaja_efectivo_id' => $this->filled('cuentacaja_efectivo_id') ? (int) $this->input('cuentacaja_efectivo_id') : null,
            'cuentacontable_venta_id' => $this->filled('cuentacontable_venta_id') ? (int) $this->input('cuentacontable_venta_id') : null,
            'deposito_id' => $this->filled('deposito_id') ? (int) $this->input('deposito_id') : null,
            'anita_deposito' => $this->filled('anita_deposito') ? (int) $this->input('anita_deposito') : null,
        ]);
    }
}
