<?php

namespace App\Http\Requests;

use App\Models\Compras\Ordencompra;
use App\Models\Compras\Tipotransaccion_Compra;
use App\Repositories\Compras\Tipotransaccion_CompraRepositoryInterface;
use App\Support\Compras\ComprobanteProveedorCentrocostoSupport;
use App\Support\Compras\ComprobanteProveedorFechaContableSupport;
use App\Support\Compras\ComprobanteProveedorModoCarga;
use App\Support\Compras\ComprobanteProveedorTipoAutorizacion;
use App\Support\Compras\ComprobanteProveedorCondicionPagoNcNdSupport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use RuntimeException;

class ValidacionComprobante_Proveedor extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $letra = $this->input('letra');
        if (is_string($letra) || is_numeric($letra)) {
            $this->merge([
                'letra' => strtoupper(substr(trim((string) $letra), 0, 1)),
            ]);
        }

        $this->reconciliarTipotransaccionCompraDesdeAbreviatura();
    }

    /**
     * La pantalla muestra la abreviatura (FAS) y graba el id oculto.
     * Si el usuario cambió FAC por FAS y Actualizar salió antes de que el id se actualice,
     * manda la abreviatura escrita.
     */
    private function reconciliarTipotransaccionCompraDesdeAbreviatura(): void
    {
        if (! $this->exists('tipotransaccion_compra_abreviatura')) {
            return;
        }

        $abrev = strtoupper(trim((string) $this->input('tipotransaccion_compra_abreviatura', '')));
        if ($abrev === '') {
            return;
        }

        $id = (int) $this->input('tipotransaccion_compra_id', 0);
        if ($abrev === $this->abreviaturaTipotransaccionCompra($id)) {
            return;
        }

        $tipo = app(Tipotransaccion_CompraRepositoryInterface::class)
            ->findPorAbreviaturaFiltrado($abrev, $this->centrocostoIdParaTipoComprobante());
        if ($tipo) {
            $this->merge(['tipotransaccion_compra_id' => (int) $tipo->id]);
        }
    }

    private function abreviaturaTipotransaccionCompra(int $id): string
    {
        if ($id <= 0) {
            return '';
        }

        return strtoupper(trim((string) Tipotransaccion_Compra::query()->whereKey($id)->value('abreviatura')));
    }

    private function centrocostoIdParaTipoComprobante(): ?int
    {
        $ordencompraId = (int) $this->input('ordencompra_id', 0);
        if ($ordencompraId <= 0) {
            return null;
        }

        $oc = Ordencompra::query()->with('ordencompra_articulos')->find($ordencompraId);
        $centrocostoId = ComprobanteProveedorCentrocostoSupport::resolverDesdeOc($oc);

        return $centrocostoId > 0 ? $centrocostoId : null;
    }

    public function rules(): array
    {
        return [
            'empresa_id' => 'required|integer|min:1',
            'proveedor_id' => 'required|integer|min:1',
            'tipotransaccion_compra_id' => 'required|integer|min:1',
            'letra' => 'required|string|size:1',
            'sucursal' => 'required|integer|min:0',
            'numerocomprobante' => 'required|integer|min:1',
            'fechacomprobante' => 'required|date|before_or_equal:'.ComprobanteProveedorFechaContableSupport::fechaComprobanteMaximaYmd(),
            'fechaiva' => 'nullable|date',
            'moneda_id' => 'required|integer|min:1',
            'subtotal' => 'nullable|numeric',
            'total' => 'nullable|numeric',
            'cotizacion' => 'nullable|numeric',
            'numerocae' => 'nullable|string|max:30',
            'tipo_autorizacion' => 'nullable|string|in:'.implode(',', ComprobanteProveedorTipoAutorizacion::todos()),
            'modo_carga' => 'nullable|string|in:'.implode(',', ComprobanteProveedorModoCarga::todos()),
            'condicionpago_id' => 'nullable|integer|min:1',
            'provincia_destino_id' => 'nullable|integer|min:1',
            'recepcion_proveedor_ids' => 'nullable|array',
            'recepcion_proveedor_ids.*' => 'integer|min:1',
            'concepto_ivacompra_ids' => 'nullable|array',
            'concepto_ivacompra_ids.*' => 'nullable|integer',
            'montos' => 'nullable|array',
            'montos.*' => 'nullable|numeric',
            'cuentacontabledebe_ids' => 'nullable|array',
            'cuentacontabledebe_ids.*' => 'nullable|integer',
            'debe_gasto_cuenta_ids' => 'nullable|array',
            'debe_gasto_cuenta_ids.*' => 'nullable|integer',
            'debe_gasto_importes' => 'nullable|array',
            'debe_gasto_importes.*' => 'nullable',
            'debe_gasto_centrocosto_ids' => 'nullable|array',
            'debe_gasto_centrocosto_ids.*' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        $dias = ComprobanteProveedorFechaContableSupport::maxDiasFuturoComprobante();

        return [
            'fechacomprobante.before_or_equal' => 'La fecha del comprobante no puede ser más de '
                .$dias.' días posterior a hoy. Revisá el año o el mes: parece un error de carga.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->exists('tipotransaccion_compra_abreviatura')) {
                $abrev = strtoupper(trim((string) $this->input('tipotransaccion_compra_abreviatura', '')));
                $id = (int) $this->input('tipotransaccion_compra_id', 0);
                if ($abrev !== '' && $abrev !== $this->abreviaturaTipotransaccionCompra($id)) {
                    $existe = Tipotransaccion_Compra::query()->where('abreviatura', $abrev)->exists();
                    $validator->errors()->add(
                        'tipotransaccion_compra_id',
                        $existe
                            ? 'El tipo de comprobante «'.$abrev.'» no está habilitado para el centro de costo de la orden de compra.'
                            : 'No se encontró el tipo de comprobante «'.$abrev.'».',
                    );
                }
            }

            if ($validator->errors()->has('fechacomprobante')) {
                return;
            }
            try {
                ComprobanteProveedorFechaContableSupport::assertFechaComprobanteNoExcesivamenteFutura(
                    $this->input('fechacomprobante')
                );
            } catch (RuntimeException $e) {
                $validator->errors()->add('fechacomprobante', $e->getMessage());
            }

            if ($validator->errors()->has('tipotransaccion_compra_id')
                || $validator->errors()->has('condicionpago_id')
            ) {
                return;
            }

            $vencimientos = $this->input('cuota_fechavencimiento', []);
            $nCuotas = 0;
            if (is_array($vencimientos)) {
                foreach ($vencimientos as $vto) {
                    if (trim((string) $vto) !== '') {
                        $nCuotas++;
                    }
                }
            }

            try {
                ComprobanteProveedorCondicionPagoNcNdSupport::assertPermitida(
                    (int) $this->input('tipotransaccion_compra_id', 0),
                    (int) $this->input('condicionpago_id', 0) ?: null,
                    $nCuotas,
                );
            } catch (RuntimeException $e) {
                $validator->errors()->add('condicionpago_id', $e->getMessage());
            }
        });
    }
}
