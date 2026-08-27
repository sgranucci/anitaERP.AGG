<?php

namespace App\Support\Compras;

final class ComprobanteProveedorEstados
{
    public const PRECARGA = 'PRECARGA';

    public const BORRADOR = 'BORRADOR';

    public const PENDIENTE_REVISION = 'PENDIENTE_REVISION';

    public const PENDIENTE_APROBACION = 'PENDIENTE_APROBACION';

    public const PENDIENTE_DIFERENCIA = 'PENDIENTE_DIFERENCIA';

    public const APROBADO = 'APROBADO';

    public const CONTABILIZADO = 'CONTABILIZADO';

    public const ANULADO = 'ANULADO';

    public const ERROR_SYNC = 'ERROR_SYNC';

    /** Filtro de listado: todas las facturas. */
    public const FILTRO_TODOS = 'todos';

    /** Filtro virtual: anita_sync_estado = ERROR (el estado del comprobante puede ser BORRADOR). */
    public const FILTRO_ERROR_ANITA = 'ERROR_ANITA';

    /** @return list<string> */
    public static function todos(): array
    {
        return [
            self::PRECARGA,
            self::BORRADOR,
            self::PENDIENTE_REVISION,
            self::PENDIENTE_APROBACION,
            self::PENDIENTE_DIFERENCIA,
            self::APROBADO,
            self::CONTABILIZADO,
            self::ANULADO,
            self::ERROR_SYNC,
        ];
    }

    /** @return list<string> */
    public static function editables(): array
    {
        return [
            self::BORRADOR,
            self::PENDIENTE_REVISION,
            self::PENDIENTE_APROBACION,
            self::PENDIENTE_DIFERENCIA,
            self::APROBADO,
            self::CONTABILIZADO,
        ];
    }

    public static function etiqueta(?string $estado): string
    {
        return match (strtoupper(trim((string) $estado))) {
            self::PRECARGA => 'Precarga',
            self::BORRADOR => 'Borrador',
            self::PENDIENTE_REVISION => 'Pend. revisión',
            self::PENDIENTE_APROBACION => 'Pend. aprobación',
            self::PENDIENTE_DIFERENCIA => 'Pend. diferencia',
            self::APROBADO => 'Aprobado',
            self::CONTABILIZADO => 'Contabilizado',
            self::ANULADO => 'Anulado',
            self::ERROR_SYNC => 'Error sync',
            self::FILTRO_ERROR_ANITA => 'Error Anita',
            default => trim((string) $estado) !== '' ? (string) $estado : '—',
        };
    }

    /**
     * Badge Bootstrap 4 para listados y cabecera del formulario.
     *
     * @return array{class: string, label: string}
     */
    public static function badge(?string $estado, bool $errorAnita = false): array
    {
        if ($errorAnita) {
            return [
                'class' => 'badge badge-danger',
                'label' => 'Error Anita',
            ];
        }

        $estado = strtoupper(trim((string) $estado));
        $class = match ($estado) {
            self::BORRADOR => 'badge badge-secondary',
            self::PENDIENTE_REVISION, self::PENDIENTE_APROBACION, self::PENDIENTE_DIFERENCIA => 'badge badge-warning',
            self::APROBADO, self::PRECARGA => 'badge badge-info',
            self::CONTABILIZADO => 'badge badge-success',
            self::ANULADO => 'badge badge-dark',
            self::ERROR_SYNC, self::FILTRO_ERROR_ANITA => 'badge badge-danger',
            default => 'badge badge-light',
        };

        return [
            'class' => $class,
            'label' => self::etiqueta($estado),
        ];
    }

    /**
     * Chips del filtro externo del index (más "Todos").
     *
     * @return array<string, string> codigo => etiqueta
     */
    public static function opcionesFiltroListado(): array
    {
        return [
            self::BORRADOR => self::etiqueta(self::BORRADOR),
            self::PENDIENTE_REVISION => self::etiqueta(self::PENDIENTE_REVISION),
            self::PENDIENTE_APROBACION => self::etiqueta(self::PENDIENTE_APROBACION),
            self::PENDIENTE_DIFERENCIA => self::etiqueta(self::PENDIENTE_DIFERENCIA),
            self::APROBADO => self::etiqueta(self::APROBADO),
            self::CONTABILIZADO => self::etiqueta(self::CONTABILIZADO),
            self::ANULADO => self::etiqueta(self::ANULADO),
            self::FILTRO_ERROR_ANITA => self::etiqueta(self::FILTRO_ERROR_ANITA),
        ];
    }

    public static function esFiltroListadoValido(string $codigo): bool
    {
        return $codigo === self::FILTRO_TODOS || isset(self::opcionesFiltroListado()[$codigo]);
    }

    public static function filtroBotonClases(string $codigo, bool $activo): string
    {
        [$lleno, $outline] = match ($codigo) {
            self::BORRADOR => ['btn-secondary', 'btn-outline-secondary'],
            self::PENDIENTE_REVISION, self::PENDIENTE_APROBACION, self::PENDIENTE_DIFERENCIA => ['btn-warning', 'btn-outline-warning'],
            self::APROBADO, self::PRECARGA => ['btn-info', 'btn-outline-info'],
            self::CONTABILIZADO => ['btn-success', 'btn-outline-success'],
            self::ANULADO => ['btn-dark', 'btn-outline-dark'],
            self::FILTRO_ERROR_ANITA, self::ERROR_SYNC => ['btn-danger', 'btn-outline-danger'],
            default => ['btn-primary', 'btn-outline-primary'],
        };

        return $activo ? $lleno : $outline;
    }

    /**
     * El comprobante ya escribió asiento/CC/compra en Anita (no es un borrador local).
     */
    public static function tieneHuellaAnita(object|array|null $comprobante): bool
    {
        if ($comprobante === null) {
            return false;
        }

        $nro = is_array($comprobante)
            ? ($comprobante['anita_nro_interno'] ?? 0)
            : ($comprobante->anita_nro_interno ?? 0);
        $asiento = is_array($comprobante)
            ? ($comprobante['asiento_id'] ?? 0)
            : ($comprobante->asiento_id ?? 0);

        return (int) $nro > 0 || (int) $asiento > 0;
    }

    public static function textoBorrarTooltip(bool $huellaAnita): string
    {
        return $huellaAnita
            ? 'Borrar factura (ERP + Anita)'
            : 'Borrar borrador (solo ERP)';
    }

    public static function textoBorrarConfirm(int $id, bool $huellaAnita, ?int $precargaId = null): string
    {
        if ($precargaId !== null && $precargaId > 0) {
            return $huellaAnita
                ? '¿Borrar el comprobante #'.$id.' y también la precarga #'.$precargaId.' en ERP y Anita? Esta acción no se puede deshacer.'
                : '¿Borrar el borrador #'.$id.' y la precarga #'.$precargaId.' del ERP? No se toca Anita (la factura nativa, si existe, queda). Esta acción no se puede deshacer.';
        }

        return $huellaAnita
            ? '¿Borrar el comprobante #'.$id.' en anitaERP y Anita (asiento, CC, compra/promov/ctamov)? Esta acción no se puede deshacer.'
            : '¿Borrar el borrador #'.$id.' del ERP? No se toca Anita. Esta acción no se puede deshacer.';
    }
}
