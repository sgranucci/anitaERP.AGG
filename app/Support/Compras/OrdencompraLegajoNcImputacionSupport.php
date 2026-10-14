<?php

namespace App\Support\Compras;

use App\Models\Compras\Concepto_Ivacompra;
use App\Models\Compras\Ordencompra;
use App\Models\Compras\Ordencompra_Legajo_Nc_Imputacion;
use App\Models\Compras\Precarga_Comprobante_Proveedor;
use App\Models\Contable\Cuentacontable;
use Illuminate\Support\Facades\Schema;

/**
 * Elección obligatoria del neto de cada nota de crédito o débito al enviar el legajo a Cuentas a pagar:
 * cuentas de los artículos de la OC, u otra cuenta (por ejemplo diferencia de cambio).
 */
final class OrdencompraLegajoNcImputacionSupport
{
    public const MODO_OC = Ordencompra_Legajo_Nc_Imputacion::MODO_OC;

    public const MODO_CUENTA = Ordencompra_Legajo_Nc_Imputacion::MODO_CUENTA;

    public static function tablaDisponible(): bool
    {
        return Schema::hasTable('ordencompra_legajo_nc_imputacion');
    }

    public static function exigeEleccion(string $tipo): bool
    {
        return in_array(strtoupper(trim($tipo)), ['NC', 'ND'], true);
    }

    /**
     * NC y ND del legajo que todavía se van a cargar en CxP.
     *
     * @return list<array{
     *     precarga_id: int|null,
     *     anita_scan_documento_id: int|null,
     *     etiqueta: string,
     *     empresa_id: int,
     *     modo: string|null,
     *     cuentacontable_id: int,
     *     cuenta_codigo: string,
     *     cuenta_nombre: string
     * }>
     */
    public static function listarPendientes(Ordencompra $oc): array
    {
        $docs = [];
        foreach (OrdencompraEnvioCuentasAPagarGateSupport::documentosPendientesCarga($oc) as $doc) {
            if (! self::exigeEleccion((string) ($doc['tipo'] ?? ''))) {
                continue;
            }
            $precargaId = (int) ($doc['precarga_id'] ?? 0);
            $anitaId = OrdencompraLegajoAnitaScanFacturaSupport::documentoIdDesdeAnitaRef(
                (string) ($doc['anita_id'] ?? '')
            );
            if ($precargaId <= 0 && $anitaId <= 0) {
                continue;
            }
            $docs[] = [
                'precarga_id' => $precargaId > 0 ? $precargaId : null,
                'anita_scan_documento_id' => $anitaId > 0 ? $anitaId : null,
                'etiqueta' => (string) ($doc['etiqueta'] ?? 'Nota de crédito o débito'),
            ];
        }

        return self::completarConEleccionGuardada($oc, $docs);
    }

    /**
     * Persiste la elección del formulario y devuelve los errores que todavía bloquean el envío.
     *
     * @param  list<array<string, mixed>>|null  $items
     * @return list<string>
     */
    public static function persistirYValidar(Ordencompra $oc, ?array $items): array
    {
        if (! self::tablaDisponible()) {
            return [];
        }

        $pendientes = self::listarPendientes($oc);
        if ($pendientes === []) {
            return [];
        }

        $empresaId = (int) ($oc->empresa_id ?? 0);
        $analisis = self::analizarPayload($pendientes, $items ?? [], self::cuentasValidas(
            self::idsCuentaDelPayload($items ?? []),
            $empresaId
        ));

        foreach ($analisis['guardar'] as $fila) {
            self::guardarFila((int) $oc->id, $fila);
        }

        if ($analisis['errores'] !== []) {
            return $analisis['errores'];
        }

        return self::analizarPayload(self::listarPendientes($oc), [], [])['errores'];
    }

    /**
     * Decisión ya guardada para la precarga (o para el scan Anita del que salió).
     *
     * @return array{modo: string, cuentacontable_id: int, cuenta_codigo: string, cuenta_nombre: string}|null
     */
    public static function decisionParaPrecarga(?Precarga_Comprobante_Proveedor $precarga, int $ordencompraId): ?array
    {
        if (! $precarga || $ordencompraId <= 0 || ! self::tablaDisponible()) {
            return null;
        }
        if (! self::exigeEleccion(OrdencompraLegajoDocumentoTipoSupport::desdePrecarga($precarga))) {
            return null;
        }

        $precargaId = (int) $precarga->id;
        $anitaId = (int) ($precarga->anita_scan_documento_id ?? 0);
        if ($precargaId <= 0 && $anitaId <= 0) {
            return null;
        }

        $row = Ordencompra_Legajo_Nc_Imputacion::query()
            ->where('ordencompra_id', $ordencompraId)
            ->where(function ($q) use ($precargaId, $anitaId) {
                if ($precargaId > 0) {
                    $q->where('precarga_comprobante_proveedor_id', $precargaId);
                }
                if ($anitaId > 0) {
                    $method = $precargaId > 0 ? 'orWhere' : 'where';
                    $q->{$method}('anita_scan_documento_id', $anitaId);
                }
            })
            ->orderByDesc('id')
            ->first();
        if (! $row) {
            return null;
        }

        if ($precargaId > 0 && (int) ($row->precarga_comprobante_proveedor_id ?? 0) <= 0) {
            $row->precarga_comprobante_proveedor_id = $precargaId;
            $row->save();
        }

        $cuentaId = (string) $row->modo === self::MODO_CUENTA ? (int) ($row->cuentacontable_id ?? 0) : 0;
        $codigo = '';
        $nombre = '';
        if ($cuentaId > 0) {
            $cuenta = Cuentacontable::query()->find($cuentaId, ['id', 'codigo', 'nombre']);
            $codigo = (string) ($cuenta->codigo ?? '');
            $nombre = (string) ($cuenta->nombre ?? '');
        }

        return [
            'modo' => (string) $row->modo === self::MODO_CUENTA ? self::MODO_CUENTA : self::MODO_OC,
            'cuentacontable_id' => $cuentaId,
            'cuenta_codigo' => $codigo,
            'cuenta_nombre' => $nombre,
        ];
    }

    /**
     * @param  iterable<int, object>  $conceptos
     */
    public static function aplicarCuentaForzadaEnConceptos(iterable $conceptos, int $cuentaId): void
    {
        if ($cuentaId <= 0) {
            return;
        }

        $ids = [];
        foreach ($conceptos as $linea) {
            $id = (int) ($linea->concepto_ivacompra_id ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        if ($ids === []) {
            return;
        }

        $metas = Concepto_Ivacompra::query()
            ->whereIn('id', array_values($ids))
            ->get(['id', 'tipoconcepto', 'codigo'])
            ->keyBy('id');

        foreach ($conceptos as $linea) {
            $meta = $metas->get((int) ($linea->concepto_ivacompra_id ?? 0));
            if (! $meta) {
                continue;
            }
            if (! ComprobanteProveedorConceptoIvaTipos::esNetoDeGasto($meta->tipoconcepto, $meta->codigo)) {
                continue;
            }
            $linea->cuentacontabledebe_id = $cuentaId;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $pendientes
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, true>  $cuentasValidas
     * @return array{errores: list<string>, guardar: list<array<string, mixed>>}
     */
    public static function analizarPayload(array $pendientes, array $items, array $cuentasValidas): array
    {
        $errores = [];
        $guardar = [];
        $usados = [];

        foreach ($pendientes as $pendiente) {
            $item = self::itemParaPendiente($pendiente, $items, $usados);
            $etiqueta = (string) ($pendiente['etiqueta'] ?? 'la nota de crédito o débito');
            $modoGuardado = (string) ($pendiente['modo'] ?? '');
            $cuentaGuardada = (int) ($pendiente['cuentacontable_id'] ?? 0);
            $modo = $item !== null ? strtolower(trim((string) ($item['modo'] ?? ''))) : $modoGuardado;

            if (! in_array($modo, [self::MODO_OC, self::MODO_CUENTA], true)) {
                $errores[] = 'Definí la imputación del neto de '.$etiqueta
                    .': la cuenta de la OC u otra cuenta contable.';

                continue;
            }

            $cuentaId = $modo === self::MODO_CUENTA
                ? ($item !== null
                    ? (int) ($item['cuentacontable_id'] ?? 0)
                    : $cuentaGuardada)
                : 0;

            if ($modo === self::MODO_CUENTA && $cuentaId <= 0) {
                $errores[] = 'El comprobante '.$etiqueta
                    .' pide otra cuenta contable, pero no indicaste cuál.';

                continue;
            }

            if ($modo === self::MODO_CUENTA && $item !== null && ! isset($cuentasValidas[$cuentaId])) {
                $errores[] = 'La cuenta indicada para '.$etiqueta
                    .' no existe en la empresa del legajo.';

                continue;
            }

            if ($item !== null) {
                $guardar[] = [
                    'precarga_id' => (int) ($pendiente['precarga_id'] ?? 0) ?: null,
                    'anita_scan_documento_id' => (int) ($pendiente['anita_scan_documento_id'] ?? 0) ?: null,
                    'modo' => $modo,
                    'cuentacontable_id' => $cuentaId > 0 ? $cuentaId : null,
                ];
            }
        }

        return ['errores' => $errores, 'guardar' => $guardar];
    }

    /**
     * @param  list<array<string, mixed>>  $docs
     * @return list<array<string, mixed>>
     */
    private static function completarConEleccionGuardada(Ordencompra $oc, array $docs): array
    {
        $empresaId = (int) ($oc->empresa_id ?? 0);
        $porPrecarga = [];
        $porAnita = [];
        if (self::tablaDisponible() && (int) $oc->id > 0 && $docs !== []) {
            foreach (Ordencompra_Legajo_Nc_Imputacion::query()->where('ordencompra_id', (int) $oc->id)->get() as $row) {
                $payload = [
                    'modo' => (string) $row->modo === self::MODO_CUENTA ? self::MODO_CUENTA : self::MODO_OC,
                    'cuentacontable_id' => (string) $row->modo === self::MODO_CUENTA
                        ? (int) ($row->cuentacontable_id ?? 0)
                        : 0,
                ];
                $preId = (int) ($row->precarga_comprobante_proveedor_id ?? 0);
                $anitaId = (int) ($row->anita_scan_documento_id ?? 0);
                if ($preId > 0) {
                    $porPrecarga[$preId] = $payload;
                }
                if ($anitaId > 0) {
                    $porAnita[$anitaId] = $payload;
                }
            }
        }

        $cuentaIds = [];
        foreach (array_merge($porPrecarga, $porAnita) as $payload) {
            if ((int) ($payload['cuentacontable_id'] ?? 0) > 0) {
                $cuentaIds[] = (int) $payload['cuentacontable_id'];
            }
        }
        $cuentas = $cuentaIds === []
            ? collect()
            : Cuentacontable::query()->whereIn('id', array_values(array_unique($cuentaIds)))->get(['id', 'codigo', 'nombre'])->keyBy('id');

        $out = [];
        foreach ($docs as $doc) {
            $preId = (int) ($doc['precarga_id'] ?? 0);
            $anitaId = (int) ($doc['anita_scan_documento_id'] ?? 0);
            $elegida = $porPrecarga[$preId] ?? $porAnita[$anitaId] ?? null;
            $cuentaId = (int) ($elegida['cuentacontable_id'] ?? 0);
            $cuenta = $cuentaId > 0 ? $cuentas->get($cuentaId) : null;
            $out[] = [
                'precarga_id' => $preId > 0 ? $preId : null,
                'anita_scan_documento_id' => $anitaId > 0 ? $anitaId : null,
                'etiqueta' => (string) ($doc['etiqueta'] ?? 'Nota de crédito o débito'),
                'empresa_id' => $empresaId,
                'modo' => $elegida['modo'] ?? null,
                'cuentacontable_id' => $cuentaId,
                'cuenta_codigo' => (string) ($cuenta->codigo ?? ''),
                'cuenta_nombre' => (string) ($cuenta->nombre ?? ''),
            ];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<int>
     */
    private static function idsCuentaDelPayload(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $id = (int) ($item['cuentacontable_id'] ?? 0);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }

        return array_values($ids);
    }

    /**
     * @param  list<int>  $ids
     * @return array<int, true>
     */
    private static function cuentasValidas(array $ids, int $empresaId): array
    {
        if ($ids === [] || $empresaId <= 0) {
            return [];
        }

        $validas = [];
        foreach (Cuentacontable::query()->whereIn('id', $ids)->get(['id', 'empresa_id']) as $cuenta) {
            if ((int) $cuenta->empresa_id === $empresaId) {
                $validas[(int) $cuenta->id] = true;
            }
        }

        return $validas;
    }

    /**
     * @param  array<string, mixed>  $pendiente
     * @param  list<array<string, mixed>>  $items
     * @param  array<int, true>  $usados
     * @return array<string, mixed>|null
     */
    private static function itemParaPendiente(array $pendiente, array $items, array &$usados): ?array
    {
        $preId = (int) ($pendiente['precarga_id'] ?? 0);
        $anitaId = (int) ($pendiente['anita_scan_documento_id'] ?? 0);
        foreach ($items as $i => $item) {
            if (! is_array($item) || isset($usados[$i])) {
                continue;
            }
            $itemPre = (int) ($item['precarga_id'] ?? 0);
            $itemAnita = (int) ($item['anita_scan_documento_id'] ?? 0);
            $coincide = ($preId > 0 && $itemPre === $preId)
                || ($preId <= 0 && $anitaId > 0 && $itemAnita === $anitaId);
            if (! $coincide) {
                continue;
            }
            $usados[$i] = true;

            return $item;
        }

        return null;
    }

    /**
     * @param  array{precarga_id: int|null, anita_scan_documento_id: int|null, modo: string, cuentacontable_id: int|null}  $fila
     */
    private static function guardarFila(int $ordencompraId, array $fila): void
    {
        $datos = [
            'modo' => $fila['modo'],
            'cuentacontable_id' => $fila['cuentacontable_id'],
            'precarga_comprobante_proveedor_id' => $fila['precarga_id'],
            'anita_scan_documento_id' => $fila['anita_scan_documento_id'],
        ];
        if ((int) ($fila['precarga_id'] ?? 0) > 0) {
            Ordencompra_Legajo_Nc_Imputacion::query()->updateOrCreate(
                [
                    'ordencompra_id' => $ordencompraId,
                    'precarga_comprobante_proveedor_id' => (int) $fila['precarga_id'],
                ],
                $datos
            );

            return;
        }

        Ordencompra_Legajo_Nc_Imputacion::query()->updateOrCreate(
            [
                'ordencompra_id' => $ordencompraId,
                'anita_scan_documento_id' => (int) ($fila['anita_scan_documento_id'] ?? 0),
            ],
            $datos
        );
    }
}
