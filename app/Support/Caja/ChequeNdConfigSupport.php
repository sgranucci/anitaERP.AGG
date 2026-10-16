<?php

namespace App\Support\Caja;

use App\Models\Configuracion\Actividad_Arca;
use App\Models\Ventas\Concepto_Venta;
use App\Models\Ventas\Puntoventa;
use App\Models\Ventas\Tipotransaccion;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Configuracion\ParametroSistemaSupport;
use InvalidArgumentException;

final class ChequeNdConfigSupport
{
    /**
     * Sucursal AFIP de respaldo si Configuración general todavía no tiene punto de venta.
     */
    public const CODIGO_SUCURSAL_NDR_FERLI = '03333';

    public static function habilitado(): bool
    {
        return (bool) config('cheque.nd_habilitado', true);
    }

    public static function sucursalFija(): bool
    {
        return EntornoEmpresaSupport::esFerli();
    }

    public static function puntoventaIdParaEmpresa(int $empresaId): int
    {
        if (self::sucursalFija()) {
            return (int) self::puntoventaSucursalFija($empresaId)->id;
        }

        $configurado = self::puntoventaConfiguradoParaEmpresa($empresaId);
        if ($configurado > 0) {
            return $configurado;
        }

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
            'Elegí la sucursal de la NDR en Configuración general (Caja / Cheques) o configurá CHEQUE_ND_PUNTOVENTA_POR_EMPRESA para la empresa '.$empresaId.'.'
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

    /**
     * Punto de venta de la ND: el elegido en el rechazo, o el default de la empresa.
     * En Ferli se ignora lo elegido y sale siempre la sucursal 03333.
     */
    public static function puntoventaParaNotaDebito(int $empresaId, ?int $puntoventaId = null): Puntoventa
    {
        if (self::sucursalFija()) {
            return self::puntoventaSucursalFija($empresaId);
        }

        $id = ($puntoventaId !== null && $puntoventaId > 0)
            ? $puntoventaId
            : self::puntoventaIdParaEmpresa($empresaId);

        $pv = Puntoventa::query()->find($id);
        if (! $pv || (int) $pv->empresa_id !== $empresaId) {
            throw new InvalidArgumentException(
                'El punto de venta id '.$id.' no existe o no pertenece a la empresa '.$empresaId.'.'
            );
        }
        if (strtoupper(trim((string) ($pv->estado ?? 'A'))) === 'I') {
            throw new InvalidArgumentException('El punto de venta '.$pv->codigo.' está inactivo.');
        }

        return $pv;
    }

    /**
     * Actividad ARCA del punto de venta de la ND. El id 1 no existe en todas las instalaciones.
     */
    public static function actividadArcaIdParaPuntoventa(int $puntoventaId): int
    {
        $desdePv = (int) (Puntoventa::query()->whereKey($puntoventaId)->value('actividad_arca_id') ?? 0);
        if ($desdePv > 0 && Actividad_Arca::query()->whereKey($desdePv)->exists()) {
            return $desdePv;
        }

        $primera = (int) (Actividad_Arca::query()->orderBy('id')->value('id') ?? 0);
        if ($primera > 0) {
            return $primera;
        }

        throw new InvalidArgumentException(
            'El punto de venta id '.$puntoventaId.' no tiene una actividad ARCA válida para la nota de débito.'
        );
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
            'sucursal_fija' => self::sucursalFija(),
        ];
    }

    /**
     * Id de Configuración general si ese punto de venta es de la empresa del cheque.
     */
    private static function puntoventaConfiguradoParaEmpresa(int $empresaId): int
    {
        $id = ParametroSistemaSupport::ndrChequePuntoventaId();
        if ($id <= 0 || $empresaId <= 0) {
            return 0;
        }

        $pv = Puntoventa::query()->find($id);
        if (! $pv || (int) $pv->empresa_id !== $empresaId) {
            return 0;
        }
        $estado = strtoupper(trim((string) ($pv->estado ?? 'A')));
        if ($estado === 'I' || $estado === 'S') {
            return 0;
        }

        return $id;
    }

    /**
     * Punto de venta de Configuración general (Caja / Cheques).
     * Si no hay uno cargado, el activo con código 03333. Vale para todas las empresas.
     */
    private static function puntoventaSucursalFija(int $empresaId): Puntoventa
    {
        $configuradoId = ParametroSistemaSupport::ndrChequePuntoventaId();
        if ($configuradoId > 0) {
            $configurado = Puntoventa::query()->find($configuradoId);
            if (! $configurado) {
                throw new InvalidArgumentException(
                    'El punto de venta de la NDR (Configuración general, Caja / Cheques) no existe.'
                );
            }
            $estado = strtoupper(trim((string) ($configurado->estado ?? 'A')));
            if ($estado === 'I' || $estado === 'S') {
                throw new InvalidArgumentException(
                    'El punto de venta '.$configurado->codigo.' de la NDR está inactivo. Cambialo en Configuración general, Caja / Cheques.'
                );
            }

            return $configurado;
        }

        $codigo = Puntoventa::normalizarCodigoArca(self::CODIGO_SUCURSAL_NDR_FERLI);
        if ($codigo === null) {
            throw new InvalidArgumentException('La sucursal fija de la NDR no es un punto de venta válido.');
        }

        $activos = Puntoventa::query()
            ->where('codigo', $codigo)
            ->get()
            ->filter(static function (Puntoventa $pv): bool {
                $estado = strtoupper(trim((string) ($pv->estado ?? 'A')));

                return $estado !== 'I' && $estado !== 'S';
            })
            ->values();

        $deLaEmpresa = $activos->first(
            static fn (Puntoventa $pv): bool => (int) $pv->empresa_id === $empresaId
        );
        if ($deLaEmpresa instanceof Puntoventa) {
            return $deLaEmpresa;
        }

        if ($activos->count() === 1) {
            $unico = $activos->first();
            if ($unico instanceof Puntoventa) {
                return $unico;
            }
        }

        throw new InvalidArgumentException(
            'La nota de débito por rechazo sale siempre con la sucursal de Configuración general (Caja / Cheques).'
            .' No hay un punto de venta activo '.$codigo.' para la empresa '.$empresaId.'.'
        );
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
