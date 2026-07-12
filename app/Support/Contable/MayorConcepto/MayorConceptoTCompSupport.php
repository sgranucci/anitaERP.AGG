<?php

namespace App\Support\Contable\MayorConcepto;

use App\ApiAnita;

/**
 * Catálogo t_comp (compras) para decidir qué aplicaciones auxpag son facturas
 * imputables al mayor por concepto (l-mayorconc.c: axp_nro_interno + tcomp_tipo_comp).
 *
 * Regla:
 * - axp_nro_interno > 0 → comprobante de compra aplicado al pago.
 * - Debe existir en t_comp (subdiario compras, activo).
 * - Excluir notas de crédito (tcomp_tipo_comp = 03): actúan como medio de pago.
 */
class MayorConceptoTCompSupport
{
    /** @var array<string, object>|null clave 3 letras => fila t_comp */
    private ?array $porClave = null;

    public function __construct(
        private readonly ApiAnita $api = new ApiAnita(),
        private readonly MayorConceptoMediopagoSupport $mediopagoSupport = new MayorConceptoMediopagoSupport(),
    ) {
    }

    /**
     * @param  list<string>  $errores
     */
    public function cargar(array &$errores = []): void
    {
        if ($this->porClave !== null) {
            return;
        }

        $raw = (string) $this->api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 't_comp',
            'campos' => 'tcomp_clave,tcomp_desc,tcomp_oper,tcomp_subdiar,tcomp_tipo_comp,tcomp_estado',
            'whereArmado' => '',
        ]);

        $mensaje = ApiAnita::extraerMensajeError($raw);
        if ($mensaje !== null) {
            $errores[] = 't_comp: '.$mensaje;
            $this->porClave = [];

            return;
        }

        $this->porClave = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            $clave = strtoupper(trim((string) ($fila->tcomp_clave ?? '')));
            if ($clave !== '') {
                $this->porClave[$clave] = $fila;
            }
        }
    }

    public function esNotaCreditoCompras(string $tipoAp): bool
    {
        $fila = $this->fila($tipoAp);

        return $fila !== null
            && trim((string) ($fila->tcomp_tipo_comp ?? '')) === '03';
    }

    /**
     * Aplicación auxpag imputable como factura de compra (gasto vía COM/subdiario).
     */
    public function esFacturaAplicada(object $fila): bool
    {
        $tipoAp = strtoupper(trim((string) ($fila->axp_tipo_ap ?? '')));
        $nroInterno = (int) ($fila->axp_nro_interno ?? 0);

        if ($tipoAp === '' || $nroInterno <= 0) {
            return false;
        }

        if ($this->mediopagoSupport->esAuxpagIgnorado($tipoAp)
            || $this->mediopagoSupport->esMedioPagoAuxpag($tipoAp)) {
            return false;
        }

        $banco = strtoupper(trim((string) ($fila->axp_banco ?? '')));
        if (str_starts_with($banco, 'INTERNA')) {
            return false;
        }

        $tcomp = $this->fila($tipoAp);
        if ($tcomp === null) {
            return false;
        }

        if (trim((string) ($tcomp->tcomp_estado ?? '')) !== 'A') {
            return false;
        }

        if (trim((string) ($tcomp->tcomp_subdiar ?? '')) !== 'C') {
            return false;
        }

        if ($this->esNotaCreditoCompras($tipoAp)) {
            return false;
        }

        return true;
    }

    private function fila(string $tipoAp): ?object
    {
        $this->cargar();

        $clave = strtoupper(trim($tipoAp));

        return $this->porClave[$clave] ?? null;
    }
}
