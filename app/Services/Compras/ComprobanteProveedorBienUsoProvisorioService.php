<?php

namespace App\Services\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Contable\BienUso;
use App\Models\Contable\Centrocosto;
use App\Services\Configuracion\ModuloAvisoService;
use App\Support\Compras\ComprobanteProveedorCentrocostoSupport;
use App\Support\Compras\ComprobanteProveedorEstados;
use App\Support\Compras\TipotransaccionCompraBienUsoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Alta provisoria de bienes de uso desde factura (Anita a-biemae -s).
 */
final class ComprobanteProveedorBienUsoProvisorioService
{
    public function __construct(
        private readonly ModuloAvisoService $moduloAvisoService,
    ) {
    }

    /**
     * @return array{
     *   puede: bool,
     *   motivo?: string,
     *   prefill: array<string, mixed>,
     *   renglones_default: list<array<string, mixed>>,
     *   bienes_existentes: int
     * }
     */
    public function datosModal(Comprobante_Proveedor $comprobante): array
    {
        $comprobante->loadMissing([
            'proveedores:id,codigo,nombre,centrocostocompra_id',
            'tipotransaccion_compras:id,abreviatura,nombre',
            'ordencompras.ordencompra_articulos',
            'ordencompras.centrocostos:id,codigo,nombre',
            'empresas:id,nombre',
            'monedas:id,nombre,abreviatura',
        ]);

        $existentes = Schema::hasColumn('bien_uso', 'comprobante_proveedor_id')
            ? (int) BienUso::query()->where('comprobante_proveedor_id', $comprobante->id)->count()
            : 0;

        if (! TipotransaccionCompraBienUsoSupport::generaDesdeComprobante($comprobante)) {
            return [
                'puede' => false,
                'motivo' => 'Este tipo de comprobante no genera bien de uso.',
                'prefill' => [],
                'renglones_default' => [],
                'bienes_existentes' => $existentes,
            ];
        }

        if (($comprobante->estado ?? '') !== ComprobanteProveedorEstados::CONTABILIZADO) {
            return [
                'puede' => false,
                'motivo' => 'El comprobante debe estar contabilizado.',
                'prefill' => [],
                'renglones_default' => [],
                'bienes_existentes' => $existentes,
            ];
        }

        if ($existentes > 0) {
            return [
                'puede' => false,
                'motivo' => 'Esta factura ya tiene '.$existentes.' bien(es) de uso vinculado(s). No se puede cargar de nuevo.',
                'prefill' => [],
                'renglones_default' => [],
                'bienes_existentes' => $existentes,
            ];
        }

        $ccId = ComprobanteProveedorCentrocostoSupport::resolverDesdeComprobante($comprobante);
        $cc = $ccId > 0
            ? Centrocosto::query()->whereKey($ccId)->first(['id', 'codigo', 'nombre'])
            : null;

        $valorSugerido = round((float) ($comprobante->subtotal ?? $comprobante->total ?? 0), 2);
        if ($valorSugerido <= 0) {
            $valorSugerido = round((float) ($comprobante->total ?? 0), 2);
        }

        $abrev = strtoupper(trim((string) ($comprobante->tipotransaccion_compras?->abreviatura ?? '')));
        $facturaLabel = trim(
            $abrev.' '
            .($comprobante->letra ?? '').'-'
            .str_pad((string) ($comprobante->sucursal ?? 0), 4, '0', STR_PAD_LEFT).'-'
            .(string) ($comprobante->numerocomprobante ?? '')
        );

        $descDefault = trim((string) ($comprobante->ordencompras?->detalle
            ?? $comprobante->ordencompras?->comentario
            ?? ''));
        if ($descDefault === '') {
            $descDefault = 'Bien de uso '.$facturaLabel;
        }
        if (mb_strlen($descDefault) > 80) {
            $descDefault = mb_substr($descDefault, 0, 80);
        }

        $fecha = $comprobante->fechacomprobante
            ? $comprobante->fechacomprobante->format('Y-m-d')
            : now()->format('Y-m-d');

        return [
            'puede' => true,
            'prefill' => [
                'comprobante_proveedor_id' => (int) $comprobante->id,
                'empresa_id' => (int) ($comprobante->empresa_id ?? 0),
                'empresa_nombre' => (string) ($comprobante->empresas?->nombre ?? ''),
                'proveedor_id' => (int) ($comprobante->proveedor_id ?? 0),
                'proveedor_codigo' => (string) ($comprobante->proveedores?->codigo ?? ''),
                'proveedor_nombre' => (string) ($comprobante->proveedores?->nombre
                    ?? $comprobante->proveedor_nombre_eventual
                    ?? ''),
                'factura_label' => $facturaLabel,
                'tipo_factura' => $abrev !== '' ? mb_substr($abrev, 0, 3) : 'FAC',
                'letra_factura' => (string) ($comprobante->letra ?? ''),
                'sucursal_factura' => (int) ($comprobante->sucursal ?? 0),
                'nro_factura' => (int) ($comprobante->numerocomprobante ?? 0),
                'fecha_factura' => $fecha,
                'monto_factura' => round((float) ($comprobante->total ?? 0), 2),
                'moneda_id' => (int) ($comprobante->moneda_id ?? 1) ?: 1,
                'moneda_nombre' => (string) ($comprobante->monedas?->nombre
                    ?? $comprobante->monedas?->abreviatura
                    ?? 'ARS'),
                'valor_sugerido' => $valorSugerido,
                'centrocosto_id' => $ccId,
                'centrocosto_codigo' => (string) ($cc->codigo ?? ''),
                'centrocosto_nombre' => (string) ($cc->nombre ?? ''),
                'descripcion_default' => $descDefault,
            ],
            'renglones_default' => [[
                'descripcion' => $descDefault,
                'valor_origen' => $valorSugerido,
                'tipo_bien' => 'I',
                'centrocosto_id' => $ccId,
                'centrocosto_codigo' => (string) ($cc->codigo ?? ''),
                'centrocosto_nombre' => (string) ($cc->nombre ?? ''),
            ]],
            'bienes_existentes' => $existentes,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $renglones
     * @return list<BienUso>
     */
    public function crearDesdeComprobante(Comprobante_Proveedor $comprobante, array $renglones): array
    {
        $modal = $this->datosModal($comprobante);
        if (! $modal['puede']) {
            throw new RuntimeException($modal['motivo'] ?? 'No se pueden crear bienes de uso.');
        }

        if ((int) ($modal['bienes_existentes'] ?? 0) > 0) {
            throw new RuntimeException(
                'Esta factura ya tiene bienes de uso vinculados. No se puede cargar de nuevo.'
            );
        }

        $prefill = $modal['prefill'];
        $limpios = [];
        foreach ($renglones as $idx => $row) {
            $desc = trim((string) ($row['descripcion'] ?? ''));
            $valor = round((float) ($row['valor_origen'] ?? 0), 2);
            $tipoBien = strtoupper(trim((string) ($row['tipo_bien'] ?? 'I')));
            if (! in_array($tipoBien, ['I', 'M', 'P'], true)) {
                $tipoBien = 'I';
            }
            $ccId = (int) ($row['centrocosto_id'] ?? 0);
            if ($ccId <= 0) {
                $ccId = (int) ($prefill['centrocosto_id'] ?? 0);
            }
            if ($desc === '') {
                throw new RuntimeException('Renglón '.($idx + 1).': falta la descripción.');
            }
            if ($valor <= 0) {
                throw new RuntimeException('Renglón '.($idx + 1).': el valor debe ser mayor a cero.');
            }
            if ($ccId <= 0) {
                throw new RuntimeException('Renglón '.($idx + 1).': indique el centro de costo.');
            }
            if (! Centrocosto::query()->whereKey($ccId)->exists()) {
                throw new RuntimeException('Renglón '.($idx + 1).': centro de costo inválido.');
            }
            if (mb_strlen($desc) > 80) {
                $desc = mb_substr($desc, 0, 80);
            }
            $limpios[] = [
                'descripcion' => $desc,
                'valor_origen' => $valor,
                'tipo_bien' => $tipoBien,
                'centrocosto_id' => $ccId,
            ];
        }

        if ($limpios === []) {
            throw new RuntimeException('Indique al menos un bien de uso.');
        }

        $creados = DB::transaction(function () use ($comprobante, $prefill, $limpios) {
            $out = [];
            $fecha = (string) ($prefill['fecha_factura'] ?? now()->format('Y-m-d'));
            foreach ($limpios as $row) {
                $payload = [
                    'empresa_id' => (int) $prefill['empresa_id'],
                    'descripcion' => $row['descripcion'],
                    'valor_origen' => $row['valor_origen'],
                    'tipo_bien' => $row['tipo_bien'],
                    'centrocosto_id' => $row['centrocosto_id'],
                    'estado' => 'P',
                    'proveedor_id' => ((int) $prefill['proveedor_id']) ?: null,
                    'comprobante_proveedor_id' => (int) $comprobante->id,
                    'tipo_factura' => (string) $prefill['tipo_factura'],
                    'letra_factura' => (string) $prefill['letra_factura'],
                    'sucursal_factura' => (int) $prefill['sucursal_factura'],
                    'nro_factura' => (int) $prefill['nro_factura'],
                    'monto_factura' => (float) $prefill['monto_factura'],
                    'fecha_factura' => $fecha,
                    'fecha_alta' => $fecha,
                    'fecha_compra' => $fecha,
                    'moneda_id' => (int) $prefill['moneda_id'],
                    'observaciones' => 'Alta provisoria desde factura '.$prefill['factura_label'],
                ];
                $out[] = BienUso::query()->create($payload);
            }

            return $out;
        });

        $this->moduloAvisoService->enviar('contable', 'bien_uso_provisorio', (int) $comprobante->id);

        return $creados;
    }
}
