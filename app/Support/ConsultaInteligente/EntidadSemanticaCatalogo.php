<?php

declare(strict_types=1);

namespace App\Support\ConsultaInteligente;

use App\Support\ConsultaInteligente\Entidades\ArticuloEntidad;
use App\Support\ConsultaInteligente\Entidades\AsientoEntidad;
use App\Support\ConsultaInteligente\Entidades\ChequeEntidad;
use App\Support\ConsultaInteligente\Entidades\ClienteEntidad;
use App\Support\ConsultaInteligente\Entidades\CobranzaEntidad;
use App\Support\ConsultaInteligente\Entidades\ComprobanteProveedorEntidad;
use App\Support\ConsultaInteligente\Entidades\EmpleadoSueldosEntidad;
use App\Support\ConsultaInteligente\Entidades\FacturaEntidad;
use App\Support\ConsultaInteligente\Entidades\ImportacionEntidad;
use App\Support\ConsultaInteligente\Entidades\IngresoEgresoEntidad;
use App\Support\ConsultaInteligente\Entidades\CapexEntidad;
use App\Support\ConsultaInteligente\Entidades\MayorConceptoEntidad;
use App\Support\ConsultaInteligente\Entidades\MayorPlanoCuentaEntidad;
use App\Support\ConsultaInteligente\Entidades\MovimientoStockEntidad;
use App\Support\ConsultaInteligente\Entidades\OrdencompraEntidad;
use App\Support\ConsultaInteligente\Entidades\PagoproveedorEntidad;
use App\Support\ConsultaInteligente\Entidades\PedidoEntidad;
use App\Support\ConsultaInteligente\Entidades\PrecargaComprobanteProveedorEntidad;
use App\Support\ConsultaInteligente\Entidades\PropuestaPagoEntidad;
use App\Support\ConsultaInteligente\Entidades\ProveedorEntidad;
use App\Support\ConsultaInteligente\Entidades\RecepcionProveedorEntidad;
use App\Support\ConsultaInteligente\Entidades\RecuentoEntidad;
use App\Support\ConsultaInteligente\Entidades\RemitoEntidad;
use App\Support\ConsultaInteligente\Entidades\RequisicionEntidad;
use App\Support\ConsultaInteligente\Entidades\RequisicionSalaEntidad;
use App\Support\ConsultaInteligente\Entidades\SolicitudpagoEntidad;
use App\Support\ConsultaInteligente\Entidades\TicketEntidad;
use App\Support\ConsultaInteligente\Entidades\TransferenciaMercaderiaEntidad;
use InvalidArgumentException;

/**
 * Registro de entidades publicadas para Consulta Inteligente.
 * Solo entran fuentes con workbench/QBE ya alineados al ERP.
 */
final class EntidadSemanticaCatalogo
{
    /**
     * @return list<EntidadSemantica>
     */
    public static function todas(): array
    {
        return [
            new ChequeEntidad,
            new IngresoEgresoEntidad,
            new CobranzaEntidad,
            new RemitoEntidad,
            new FacturaEntidad,
            new PedidoEntidad,
            new ComprobanteProveedorEntidad,
            new PagoproveedorEntidad,
            new OrdencompraEntidad,
            new RequisicionEntidad,
            new ProveedorEntidad,
            new ClienteEntidad,
            new ArticuloEntidad,
            new MovimientoStockEntidad,
            new TransferenciaMercaderiaEntidad,
            new CapexEntidad,
            new ImportacionEntidad,
            new RecepcionProveedorEntidad,
            new PrecargaComprobanteProveedorEntidad,
            new PropuestaPagoEntidad,
            new RecuentoEntidad,
            new RequisicionSalaEntidad,
            new SolicitudpagoEntidad,
            new EmpleadoSueldosEntidad,
            new TicketEntidad,
            new AsientoEntidad,
            new MayorConceptoEntidad,
            new MayorPlanoCuentaEntidad,
        ];
    }

    /** Recurso de vistas guardadas / envíos del explorador CI para una entidad. */
    public static function recursoVista(string $claveEntidad): string
    {
        return 'consulta.inteligente.'.$claveEntidad;
    }

    /**
     * URL del explorador con query anidada (qbe) sin romper route().
     *
     * @param  array<string, mixed>  $query
     */
    public static function urlExplorar(string $claveEntidad, array $query = []): string
    {
        $base = route('explorar_consulta_inteligente', ['entidad' => $claveEntidad]);
        if ($query === []) {
            return $base;
        }

        return $base.'?'.http_build_query($query);
    }

    public static function porClave(string $clave): EntidadSemantica
    {
        foreach (self::todas() as $entidad) {
            if ($entidad->clave() === $clave) {
                return $entidad;
            }
        }

        throw new InvalidArgumentException("Entidad semántica desconocida: {$clave}");
    }

    public static function existe(string $clave): bool
    {
        foreach (self::todas() as $entidad) {
            if ($entidad->clave() === $clave) {
                return true;
            }
        }

        return false;
    }

    /**
     * Entidades que el usuario puede listar (permiso del módulo origen).
     *
     * @return list<EntidadSemantica>
     */
    public static function disponiblesParaUsuario(): array
    {
        $out = [];
        foreach (self::todas() as $entidad) {
            if (can($entidad->permisoListar(), false)) {
                $out[] = $entidad;
            }
        }

        return $out;
    }

    /**
     * @return list<string>
     */
    public static function modulos(): array
    {
        $mods = [];
        foreach (self::todas() as $entidad) {
            $mods[$entidad->modulo()] = true;
        }

        return array_keys($mods);
    }
}
