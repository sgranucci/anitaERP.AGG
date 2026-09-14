<?php

namespace App\Support\Caja;

use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use InvalidArgumentException;

final class ChequeNdConfigSupport
{
    public static function habilitado(): bool
    {
        return (bool) config('cheque.nd_habilitado', true);
    }

    public static function puntoventaIdParaEmpresa(int $empresaId): int
    {
        $map = config('cheque.nd_puntoventa_por_empresa', []);
        $id = (int) ($map[$empresaId] ?? $map[(string) $empresaId] ?? 0);
        if ($id <= 0) {
            $fallback = config('facturacion.PUNTOVENTA_FACTURACION');
            if (is_array($fallback)) {
                $id = (int) ($fallback[0] ?? 0);
            } else {
                $id = (int) $fallback;
            }
        }

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Configure CHEQUE_ND_PUNTOVENTA_POR_EMPRESA en .env para la empresa '.$empresaId.'.'
            );
        }

        $pv = Puntoventa::query()->find($id);
        if (! $pv || (int) $pv->empresa_id !== $empresaId) {
            throw new InvalidArgumentException(
                'El punto de venta id '.$id.' no existe o no pertenece a la empresa '.$empresaId.'.'
            );
        }

        return $id;
    }

    public static function tipotransaccionNotaDebitoId(?string $letraCliente = null): int
    {
        $mapaLetra = config('cheque.nd_tipotransaccion_por_letra', []);
        $letra = strtoupper(trim((string) $letraCliente));
        if ($letra !== '' && isset($mapaLetra[$letra])) {
            $id = (int) $mapaLetra[$letra];
        } else {
            $id = (int) config('cheque.nd_tipotransaccion_id', 0);
        }

        if ($id <= 0) {
            throw new InvalidArgumentException(
                'Configure CHEQUE_ND_TIPOTRANSACCION_ID o CHEQUE_ND_TIPOTRANSACCION_POR_LETRA en .env.'
            );
        }

        $tipo = Tipotransaccion::query()->find($id);
        if (! $tipo || ! $tipo->esNotaDebito()) {
            throw new InvalidArgumentException(
                'El tipo de transacción id '.$id.' no es una nota de débito válida (NDR/ND*).'
            );
        }

        return $id;
    }

    public static function conceptoIdParaChequeRechazado(): int
    {
        return self::resolverConceptoId(
            (int) config('cheque.nd_concepto_id', 0),
            trim((string) config('cheque.nd_concepto_codigo', 'NDR-CHEQUE')),
            'CHEQUE_ND_CONCEPTO_ID o CHEQUE_ND_CONCEPTO_CODIGO'
        );
    }

    public static function conceptoIdParaGastosBancarios(): ?int
    {
        $id = (int) config('cheque.nd_gastos_concepto_id', 0);
        $codigo = trim((string) config('cheque.nd_gastos_concepto_codigo', 'NDR-GASTOS'));
        if ($id <= 0 && $codigo === '') {
            return null;
        }

        try {
            return self::resolverConceptoId($id, $codigo, 'CHEQUE_ND_GASTOS_CONCEPTO_ID o CHEQUE_ND_GASTOS_CONCEPTO_CODIGO');
        } catch (InvalidArgumentException) {
            return null;
        }
    }

    public static function impuestoIdDefault(): int
    {
        $id = (int) config('cheque.nd_impuesto_id', 0);

        return $id > 0 ? $id : 3;
    }

    public static function letraDesdeCliente(?object $cliente): string
    {
        $letra = strtoupper(trim((string) ($cliente?->condicionivas?->letra ?? '')));
        if ($letra !== '') {
            return substr($letra, 0, 1);
        }

        return 'A';
    }

    /**
     * @return array{id:int, codigo:string, nombre:string, modofacturacion:string, webservice:?string}
     */
    public static function puntoventaResumen(int $empresaId): array
    {
        $id = self::puntoventaIdParaEmpresa($empresaId);
        $pv = Puntoventa::query()->findOrFail($id);

        return [
            'id' => (int) $pv->id,
            'codigo' => (string) ($pv->codigo ?? ''),
            'nombre' => (string) ($pv->nombre ?? ''),
            'modofacturacion' => (string) ($pv->modofacturacion ?? 'M'),
            'webservice' => $pv->webservice !== null ? (string) $pv->webservice : null,
        ];
    }

    private static function resolverConceptoId(int $id, string $codigo, string $envHint): int
    {
        if ($id > 0) {
            $existe = Concepto_Venta::query()->whereKey($id)->where('activo', true)->exists();
            if ($existe) {
                return $id;
            }
        }

        if ($codigo !== '') {
            $conceptoId = Concepto_Venta::query()
                ->where('codigo', $codigo)
                ->where('activo', true)
                ->value('id');
            if ($conceptoId) {
                return (int) $conceptoId;
            }
        }

        throw new InvalidArgumentException(
            'Configure '.$envHint.' (concepto de venta activo) para ND por cheque rechazado.'
        );
    }
}
