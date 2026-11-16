<?php

declare(strict_types=1);

namespace App\Support\Compras;

use App\Models\Compras\Comprobante_Proveedor;
use App\Models\Compras\Importacion;
use App\Models\Compras\Importacion_Gasto;
use App\Models\Compras\Importacion_Gasto_Tipo;
use App\Services\Compras\ImportacionLandedCostService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Vínculo factura de proveedor ↔ gasto de carpeta de importación.
 *
 * Independiente de OC: flete/seguro/despachante se asocian desde la carpeta
 * o desde el ABM de la factura (alta «Sin OC»).
 */
final class ComprobanteProveedorImportacionSupport
{
    /**
     * @return list<array{
     *   gasto_id: int,
     *   importacion_id: int,
     *   codigo: string,
     *   estado: string,
     *   estado_etiqueta: string,
     *   tipo_id: int,
     *   tipo_codigo: string,
     *   tipo_nombre: string,
     *   naturaleza: string,
     *   importe: float,
     *   fecha: ?string,
     *   url: ?string
     * }>
     */
    public static function gastosVinculados(int $comprobanteProveedorId): array
    {
        if ($comprobanteProveedorId <= 0) {
            return [];
        }

        $puedeAbrir = can('listar-importacion', false)
            || can('editar-importacion', false)
            || can('crear-importacion', false);

        /** @var Collection<int, Importacion_Gasto> $gastos */
        $gastos = Importacion_Gasto::query()
            ->with([
                'importacion:id,codigo,estado',
                'tipo:id,codigo,nombre',
            ])
            ->where('comprobante_proveedor_id', $comprobanteProveedorId)
            ->orderByDesc('id')
            ->get();

        $out = [];
        foreach ($gastos as $gasto) {
            $imp = $gasto->importacion;
            $impId = (int) ($imp->id ?? $gasto->importacion_id ?? 0);
            $estado = (string) ($imp->estado ?? '');
            $url = null;
            if ($puedeAbrir && $impId > 0) {
                $url = route('editar_importacion', [
                    'id' => $impId,
                    'origen' => 'modal_consulta',
                    'vista' => 'consulta',
                ]);
            }

            $out[] = [
                'gasto_id' => (int) $gasto->id,
                'importacion_id' => $impId,
                'codigo' => (string) ($imp->codigo ?? ''),
                'estado' => $estado,
                'estado_etiqueta' => ImportacionEstados::etiqueta($estado),
                'tipo_id' => (int) ($gasto->importacion_gasto_tipo_id ?? 0),
                'tipo_codigo' => (string) ($gasto->tipo->codigo ?? ''),
                'tipo_nombre' => (string) ($gasto->tipo->nombre ?? ''),
                'naturaleza' => (string) ($gasto->naturaleza ?? ''),
                'importe' => (float) ($gasto->importe ?? 0),
                'fecha' => self::fechaCorta($gasto->fecha),
                'url' => $url,
            ];
        }

        return $out;
    }

    /**
     * Prefill del formulario CP (primer vínculo, si hay).
     *
     * @return array{
     *   importacion_id: int|null,
     *   importacion_codigo: string,
     *   importacion_estado: string,
     *   importacion_gasto_tipo_id: int|null,
     *   importacion_gasto_tipo_codigo: string,
     *   importacion_gasto_tipo_nombre: string
     * }
     */
    public static function prefijoFormulario(?Comprobante_Proveedor $cp): array
    {
        $vacio = [
            'importacion_id' => null,
            'importacion_codigo' => '',
            'importacion_estado' => '',
            'importacion_gasto_tipo_id' => null,
            'importacion_gasto_tipo_codigo' => '',
            'importacion_gasto_tipo_nombre' => '',
        ];
        if (! $cp || ! $cp->id) {
            return $vacio;
        }

        $vinculos = self::gastosVinculados((int) $cp->id);
        if ($vinculos === []) {
            return $vacio;
        }
        $v = $vinculos[0];

        return [
            'importacion_id' => (int) $v['importacion_id'] ?: null,
            'importacion_codigo' => (string) $v['codigo'],
            'importacion_estado' => (string) $v['estado_etiqueta'],
            'importacion_gasto_tipo_id' => (int) $v['tipo_id'] ?: null,
            'importacion_gasto_tipo_codigo' => (string) $v['tipo_codigo'],
            'importacion_gasto_tipo_nombre' => (string) $v['tipo_nombre'],
        ];
    }

    /**
     * Tras guardar la factura: crea/actualiza el gasto en la carpeta o desvincula.
     */
    public static function sincronizarDesdeRequest(Comprobante_Proveedor $cp, Request $request): void
    {
        // Panel presente en el form (todas las altas). Sin el hidden = panel no renderizado.
        if (! $request->has('importacion_id')) {
            return;
        }

        $importacionId = $request->filled('importacion_id') ? (int) $request->input('importacion_id') : 0;
        $tipoId = $request->filled('importacion_gasto_tipo_id') ? (int) $request->input('importacion_gasto_tipo_id') : 0;

        self::sincronizar($cp, $importacionId > 0 ? $importacionId : null, $tipoId > 0 ? $tipoId : null);
    }

    public static function sincronizar(
        Comprobante_Proveedor $cp,
        ?int $importacionId,
        ?int $gastoTipoId
    ): void {
        $cpId = (int) $cp->id;
        if ($cpId <= 0) {
            return;
        }

        if ($importacionId === null || $importacionId <= 0) {
            self::desvincular($cpId);

            return;
        }

        if ($gastoTipoId === null || $gastoTipoId <= 0) {
            throw new \InvalidArgumentException(
                'Para asociar la factura a una carpeta de importación indique también el tipo de gasto.'
            );
        }

        $importacion = Importacion::query()->find($importacionId);
        if (! $importacion) {
            throw new \InvalidArgumentException('Carpeta de importación no encontrada.');
        }

        $estadoImp = (string) $importacion->estado;
        if (! ImportacionEstados::aceptaGastos($estadoImp)) {
            throw new \InvalidArgumentException(
                'La carpeta '.$importacion->codigo.' no admite más gastos (estado '
                .ImportacionEstados::etiqueta($estadoImp).').'
            );
        }

        if (! Importacion_Gasto_Tipo::query()->whereKey($gastoTipoId)->where('activo', true)->exists()) {
            throw new \InvalidArgumentException('Tipo de gasto de importación inválido o inactivo.');
        }

        // Sacar el CP de otras carpetas.
        Importacion_Gasto::query()
            ->where('comprobante_proveedor_id', $cpId)
            ->where('importacion_id', '!=', $importacionId)
            ->update(['comprobante_proveedor_id' => null]);

        $importe = round((float) ($cp->total ?? 0), 4);
        $cot = (float) ($cp->cotizacion ?? 0);
        $monedaId = (int) ($cp->moneda_id ?? 1);
        $importeLocal = $monedaId <= 1
            ? $importe
            : ($cot > 0 ? round($importe * $cot, 4) : $importe);

        $fecha = null;
        if ($cp->fechacomprobante instanceof \DateTimeInterface) {
            $fecha = $cp->fechacomprobante->format('Y-m-d');
        } elseif (! empty($cp->fechacomprobante)) {
            $fecha = substr((string) $cp->fechacomprobante, 0, 10);
        }

        $abrev = (string) (optional($cp->tipotransaccion_compras)->abreviatura ?? 'FAC');
        $ref = trim($abrev.' '.(string) ($cp->letra ?? '').' '
            .(string) ($cp->sucursal ?? '').'-'.(string) ($cp->numerocomprobante ?? ''));

        $payload = [
            'importacion_gasto_tipo_id' => $gastoTipoId,
            'naturaleza' => 'real',
            'proveedor_id' => $cp->proveedor_id,
            'comprobante_proveedor_id' => $cpId,
            'moneda_id' => $monedaId > 0 ? $monedaId : null,
            'cotizacion' => $cot > 0 ? $cot : null,
            'importe' => $importe,
            'importe_moneda_local' => $importeLocal,
            'fecha' => $fecha,
            'referencia' => mb_substr($ref, 0, 120),
        ];

        $existente = Importacion_Gasto::query()
            ->where('comprobante_proveedor_id', $cpId)
            ->where('importacion_id', $importacionId)
            ->first();

        if ($existente) {
            $existente->fill($payload);
            $existente->save();
        } else {
            Importacion_Gasto::query()->create($payload + [
                'importacion_id' => $importacionId,
            ]);
        }

        app(ImportacionLandedCostService::class)->refrescarTotalesCabecera($importacion->fresh());
    }

    private static function desvincular(int $comprobanteProveedorId): void
    {
        $gastos = Importacion_Gasto::query()
            ->where('comprobante_proveedor_id', $comprobanteProveedorId)
            ->get();
        if ($gastos->isEmpty()) {
            return;
        }

        $importacionIds = $gastos->pluck('importacion_id')->unique()->filter()->all();
        foreach ($gastos as $gasto) {
            $gasto->comprobante_proveedor_id = null;
            $gasto->save();
        }

        $landed = app(ImportacionLandedCostService::class);
        foreach ($importacionIds as $impId) {
            $imp = Importacion::query()->find((int) $impId);
            if ($imp) {
                $landed->refrescarTotalesCabecera($imp);
            }
        }
    }

    private static function fechaCorta(mixed $fecha): ?string
    {
        if ($fecha instanceof \DateTimeInterface) {
            return $fecha->format('d/m/Y');
        }
        if ($fecha === null || $fecha === '') {
            return null;
        }
        $ts = strtotime((string) $fecha);

        return $ts ? date('d/m/Y', $ts) : null;
    }
}
