<?php

namespace App\Support\Contable\MayorPlanoCuenta;

use App\Support\Contable\AsientoOrigenProcesoSupport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Completa IDs de origen (CP, venta, remesa, cobranza, etc.) desde el asiento ERP.
 */
class MayorPlanoCuentaComprobanteEnricher
{
    /**
     * @param  list<array<string, mixed>>  $filas
     * @return list<array<string, mixed>>
     */
    public function enriquecer(array $filas): array
    {
        $asientoIds = array_values(array_unique(array_filter(array_map(
            fn (array $f) => (int) ($f['asiento_id'] ?? 0),
            $filas,
        ), fn (int $n) => $n > 0)));

        $fksVacias = [
            'comprobante_proveedor_id' => 0,
            'venta_id' => 0,
            'remesa_id' => 0,
            'remesa_numero' => 0,
            'jornada_gastronomia_id' => 0,
            'rendicion_estacionamiento_caja_id' => 0,
            'transferencia_mercaderia_id' => 0,
            'caja_movimiento_id' => 0,
            'solicitudpago_id' => 0,
            'solicitudpago_codigo' => '',
            'cobranza_id' => 0,
            'pagoproveedor_id' => 0,
            'recepcionproveedor_id' => 0,
            'movimientostock_id' => 0,
            'ordencompra_id_asiento' => 0,
        ];

        if ($asientoIds === []) {
            foreach ($filas as $idx => $fila) {
                if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                    continue;
                }
                foreach ($fksVacias as $k => $v) {
                    $filas[$idx][$k] = $v;
                }
            }

            return $filas;
        }

        $cols = ['id'];
        foreach (AsientoOrigenProcesoSupport::columnasFk() as $fk) {
            if (Schema::hasColumn('asiento', $fk)) {
                $cols[] = $fk;
            }
        }

        $mapa = DB::table('asiento')
            ->whereIn('id', $asientoIds)
            ->get($cols)
            ->keyBy('id');

        $remesaIds = [];
        $solicitudpagoIds = [];
        foreach ($mapa as $row) {
            $rid = (int) ($row->remesa_id ?? 0);
            if ($rid > 0) {
                $remesaIds[$rid] = $rid;
            }
            $spid = (int) ($row->solicitudpago_id ?? 0);
            if ($spid > 0) {
                $solicitudpagoIds[$spid] = $spid;
            }
        }

        $numerosRemesa = [];
        if ($remesaIds !== [] && Schema::hasTable('remesa')) {
            $numerosRemesa = DB::table('remesa')
                ->whereIn('id', array_values($remesaIds))
                ->pluck('numero', 'id')
                ->all();
        }

        $codigosSp = [];
        if ($solicitudpagoIds !== [] && Schema::hasTable('solicitudpago')) {
            $codigosSp = DB::table('solicitudpago')
                ->whereIn('id', array_values($solicitudpagoIds))
                ->pluck('codigo', 'id')
                ->all();
        }

        foreach ($filas as $idx => $fila) {
            if (($fila['tipo_fila'] ?? 'detalle') !== 'detalle') {
                continue;
            }

            $asientoId = (int) ($fila['asiento_id'] ?? 0);
            $row = $asientoId > 0 ? $mapa->get($asientoId) : null;

            $filas[$idx]['comprobante_proveedor_id'] = (int) ($row->comprobante_proveedor_id ?? 0);
            $filas[$idx]['venta_id'] = (int) ($row->venta_id ?? 0);
            $remesaId = (int) ($row->remesa_id ?? 0);
            $filas[$idx]['remesa_id'] = $remesaId;
            $filas[$idx]['remesa_numero'] = (int) ($numerosRemesa[$remesaId] ?? 0);
            $filas[$idx]['jornada_gastronomia_id'] = (int) ($row->jornada_gastronomia_id ?? 0);
            $filas[$idx]['rendicion_estacionamiento_caja_id'] = (int) ($row->rendicion_estacionamiento_caja_id ?? 0);
            $filas[$idx]['transferencia_mercaderia_id'] = (int) ($row->transferencia_mercaderia_id ?? 0);
            $filas[$idx]['caja_movimiento_id'] = (int) ($row->caja_movimiento_id ?? 0);
            $spId = (int) ($row->solicitudpago_id ?? 0);
            $filas[$idx]['solicitudpago_id'] = $spId;
            $filas[$idx]['solicitudpago_codigo'] = (string) ($codigosSp[$spId] ?? '');
            $filas[$idx]['cobranza_id'] = (int) ($row->cobranza_id ?? 0);
            $filas[$idx]['pagoproveedor_id'] = (int) ($row->pagoproveedor_id ?? 0);
            $filas[$idx]['recepcionproveedor_id'] = (int) ($row->recepcionproveedor_id ?? 0);
            $filas[$idx]['movimientostock_id'] = (int) ($row->movimientostock_id ?? 0);
            $ocAsiento = (int) ($row->ordencompra_id ?? 0);
            $filas[$idx]['ordencompra_id_asiento'] = $ocAsiento;
            // Preferir siempre la FK del asiento ERP: aplp_orden/ctav mal resueltos
            // pueden haber cargado un nro_oc de renglón (1, 2, 3…) con id de OC antigua.
            if ($ocAsiento > 0) {
                $filas[$idx]['ordencompra_id'] = $ocAsiento;
            }

            if (trim((string) ($filas[$idx]['comprobante'] ?? '')) === '') {
                $filas[$idx]['comprobante'] = $this->etiquetaComprobante($filas[$idx]);
            }
            if (trim((string) ($filas[$idx]['tipo_comp'] ?? '')) === '' && $filas[$idx]['comprobante'] !== '') {
                $filas[$idx]['tipo_comp'] = $this->tipoCompDesdeFila($filas[$idx]);
            }
        }

        return $filas;
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function etiquetaComprobante(array $fila): string
    {
        if ((int) ($fila['comprobante_proveedor_id'] ?? 0) > 0) {
            return 'CP #'.(int) $fila['comprobante_proveedor_id'];
        }
        if ((int) ($fila['venta_id'] ?? 0) > 0) {
            return 'Venta #'.(int) $fila['venta_id'];
        }
        if ((int) ($fila['remesa_id'] ?? 0) > 0) {
            $nro = (int) ($fila['remesa_numero'] ?? 0);

            return $nro > 0 ? 'Remesa '.$nro : 'Remesa #'.(int) $fila['remesa_id'];
        }
        if ((int) ($fila['jornada_gastronomia_id'] ?? 0) > 0) {
            return 'Gastro jornada #'.(int) $fila['jornada_gastronomia_id'];
        }
        if ((int) ($fila['rendicion_estacionamiento_caja_id'] ?? 0) > 0) {
            return 'Estac. rend. #'.(int) $fila['rendicion_estacionamiento_caja_id'];
        }
        if ((int) ($fila['transferencia_mercaderia_id'] ?? 0) > 0) {
            return 'TM #'.(int) $fila['transferencia_mercaderia_id'];
        }
        if ((int) ($fila['cobranza_id'] ?? 0) > 0) {
            return 'Cobranza #'.(int) $fila['cobranza_id'];
        }
        if ((int) ($fila['pagoproveedor_id'] ?? 0) > 0) {
            return 'Pago #'.(int) $fila['pagoproveedor_id'];
        }
        if ((int) ($fila['recepcionproveedor_id'] ?? 0) > 0) {
            return 'Recepción #'.(int) $fila['recepcionproveedor_id'];
        }
        if ((int) ($fila['movimientostock_id'] ?? 0) > 0) {
            return 'Mov.stock #'.(int) $fila['movimientostock_id'];
        }
        if ((int) ($fila['caja_movimiento_id'] ?? 0) > 0) {
            $etiqueta = 'Mov.caja #'.(int) $fila['caja_movimiento_id'];
            $spCodigo = trim((string) ($fila['solicitudpago_codigo'] ?? ''));
            $spId = (int) ($fila['solicitudpago_id'] ?? 0);
            if ($spCodigo !== '') {
                $etiqueta .= ' / SP '.$spCodigo;
            } elseif ($spId > 0) {
                $etiqueta .= ' / SP #'.$spId;
            }

            return $etiqueta;
        }
        if ((int) ($fila['solicitudpago_id'] ?? 0) > 0) {
            $spCodigo = trim((string) ($fila['solicitudpago_codigo'] ?? ''));

            return $spCodigo !== ''
                ? 'SP '.$spCodigo
                : 'SP #'.(int) $fila['solicitudpago_id'];
        }
        $ocId = (int) ($fila['ordencompra_id'] ?? $fila['ordencompra_id_asiento'] ?? 0);
        if ($ocId > 0) {
            return 'OC #'.$ocId;
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $fila
     */
    private function tipoCompDesdeFila(array $fila): string
    {
        if ((int) ($fila['remesa_id'] ?? 0) > 0) {
            return 'REM';
        }
        if ((int) ($fila['jornada_gastronomia_id'] ?? 0) > 0) {
            return 'GAS';
        }
        if ((int) ($fila['rendicion_estacionamiento_caja_id'] ?? 0) > 0) {
            return 'EST';
        }
        if ((int) ($fila['transferencia_mercaderia_id'] ?? 0) > 0) {
            return 'TM';
        }
        if ((int) ($fila['cobranza_id'] ?? 0) > 0) {
            return 'COB';
        }
        if ((int) ($fila['pagoproveedor_id'] ?? 0) > 0) {
            return 'OPP';
        }
        if ((int) ($fila['caja_movimiento_id'] ?? 0) > 0) {
            return 'TES';
        }
        if ((int) ($fila['solicitudpago_id'] ?? 0) > 0) {
            return 'SP';
        }
        if ((int) ($fila['recepcionproveedor_id'] ?? 0) > 0) {
            return 'REC';
        }
        if ((int) ($fila['movimientostock_id'] ?? 0) > 0) {
            return 'STK';
        }

        return '';
    }
}
