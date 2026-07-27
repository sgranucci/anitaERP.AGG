<?php

namespace App\Services\Solicitudpago;

use App\ApiAnita;
use App\Models\Compras\Proveedor;
use App\Models\Configuracion\Empresa;
use App\Models\Configuracion\Moneda;
use App\Models\Contable\Centrocosto;
use App\Models\Contable\Cuentacontable;
use App\Models\Seguridad\Usuario;
use App\Models\Solicitudpago\Concepto_Solicitudpago;
use App\Models\Solicitudpago\Formapagosol;
use App\Models\Solicitudpago\Sector_Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago;
use App\Models\Solicitudpago\Solicitudpago_Archivo;
use App\Models\Solicitudpago\Solicitudpago_Cuenta;
use App\Models\Solicitudpago\Solicitudpago_Cuota;
use App\Models\Solicitudpago\Solicitudpago_Estado;
use App\Repositories\Solicitudpago\Concepto_SolicitudpagoRepositoryInterface;
use App\Repositories\Solicitudpago\FormapagosolRepositoryInterface;
use App\Repositories\Solicitudpago\Sector_SolicitudpagoRepositoryInterface;
use App\Support\Solicitudpago\SolicitudpagoAnitaFechaSupport;
use App\Support\Solicitudpago\SolicitudpagoEstados;
use App\Support\Solicitudpago\SolicitudpagoTratamientos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sync pull completo solpago* (che_ban) → ERP.
 */
class SolicitudpagoAnitaSyncService
{
    public function __construct(
        private Sector_SolicitudpagoRepositoryInterface $sectorRepository,
        private FormapagosolRepositoryInterface $formapagosolRepository,
        private Concepto_SolicitudpagoRepositoryInterface $conceptoRepository,
    ) {
    }

    public function sincronizar(): array
    {
        ini_set('max_execution_time', '900');
        ini_set('memory_limit', '1024M');

        $this->sectorRepository->sincronizarConAnita();
        $this->formapagosolRepository->sincronizarConAnita();
        if (! Concepto_Solicitudpago::query()->exists()) {
            $this->conceptoRepository->sincronizarConAnita();
        }

        $api = new ApiAnita();
        $sistema = (string) config('solicitudpago.anita_sistema', 'che_ban');

        $cabeceras = $this->listar($api, $sistema, 'solpagomae',
            'solpm_id, solpm_empresa, solpm_fecha, solpm_tratamiento, solpm_proveedor, solpm_concepto, '
            .'solpm_formapago, solpm_cod_mon, solpm_beneficiario, solpm_endoso, solpm_fecha_ent, solpm_fecha_vto, '
            .'solpm_monto, solpm_observacion, solpm_estado, solpm_sector, solpm_detalle, solpm_id_sp_orig, solpm_usuario_umod'
        );

        $mapas = $this->armarMapas($api);

        $creados = 0;
        $actualizados = 0;
        $madrePendientes = [];

        DB::transaction(function () use ($cabeceras, $mapas, &$creados, &$actualizados, &$madrePendientes) {
            foreach ($cabeceras as $row) {
                $codigo = (int) ($row->solpm_id ?? 0);
                if ($codigo <= 0) {
                    continue;
                }

                $attrs = $this->attrsCabecera($row, $mapas);
                $madreCodigo = (int) ($row->solpm_id_sp_orig ?? 0);
                if ($madreCodigo > 0) {
                    $madrePendientes[$codigo] = $madreCodigo;
                }

                $existente = Solicitudpago::query()->where('codigo', $codigo)->first();
                if ($existente) {
                    $existente->update($attrs);
                    $actualizados++;
                } else {
                    Solicitudpago::query()->create($attrs);
                    $creados++;
                }
            }
        });

        $porCodigo = Solicitudpago::query()->pluck('id', 'codigo')->all();
        foreach ($madrePendientes as $codigoHija => $codigoMadre) {
            $hijaId = $porCodigo[$codigoHija] ?? null;
            $madreId = $porCodigo[$codigoMadre] ?? null;
            if ($hijaId && $madreId) {
                Solicitudpago::query()->where('id', $hijaId)->update(['solicitudpago_madre_id' => $madreId]);
            }
        }

        $this->sincronizarCuentas($api, $sistema, $porCodigo, $mapas);
        $this->sincronizarCuotas($api, $sistema, $porCodigo);
        $this->sincronizarEstados($api, $sistema, $porCodigo, $mapas);
        $this->sincronizarArchivos($api, $sistema, $porCodigo, $mapas);

        return [
            'cabeceras' => count($cabeceras),
            'creados' => $creados,
            'actualizados' => $actualizados,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function armarMapas(ApiAnita $api): array
    {
        $usuariosAnita = $this->mapaAnitaUsuarioAErp($api);

        return [
            'empresas' => Empresa::query()->pluck('id', 'codigo')->all(),
            'sectores' => Sector_Solicitudpago::query()->pluck('id', 'codigo')->all(),
            'conceptos' => Concepto_Solicitudpago::query()->pluck('id', 'codigo')->all(),
            'formapagos' => Formapagosol::query()->pluck('id', 'codigo')->all(),
            'monedas' => Moneda::query()->pluck('id', 'codigo')->all(),
            'proveedores' => Proveedor::query()->pluck('id', 'codigo')->mapWithKeys(function ($id, $codigo) {
                return [ltrim((string) $codigo, '0') ?: '0' => $id, (string) $codigo => $id];
            })->all(),
            'ccostos' => Centrocosto::query()->pluck('id', 'codigo')->all(),
            'usuarios' => $usuariosAnita,
            'centrocostos_usuario' => Usuario::query()
                ->whereNotNull('centrocosto_id')
                ->where('centrocosto_id', '>', 0)
                ->pluck('centrocosto_id', 'id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $mapas
     * @return array<string, mixed>
     */
    private function attrsCabecera(object $row, array $mapas): array
    {
        $empresaCodigo = (int) ($row->solpm_empresa ?? 0);
        $conceptoCodigo = (int) ($row->solpm_concepto ?? 0);
        $fpCodigo = (int) ($row->solpm_formapago ?? 0);
        $sectorCodigo = (int) ($row->solpm_sector ?? 0);
        $monCodigo = (int) ($row->solpm_cod_mon ?? 0);
        $provCodigo = ltrim(trim((string) ($row->solpm_proveedor ?? '')), '0');
        if ($provCodigo === '') {
            $provCodigo = '0';
        }
        $anitaUsuario = (int) ($row->solpm_usuario_umod ?? 0);
        $usuarioUmodId = $anitaUsuario > 0 ? ($mapas['usuarios'][$anitaUsuario] ?? null) : null;
        $centrocostoId = null;
        if ($usuarioUmodId) {
            $centrocostoId = $mapas['centrocostos_usuario'][(int) $usuarioUmodId] ?? null;
        }

        $fecha = SolicitudpagoAnitaFechaSupport::fechaDesdeAnita($row->solpm_fecha ?? 0)
            ?? now()->toDateString();

        return [
            'codigo' => (int) $row->solpm_id,
            'empresa_id' => $mapas['empresas'][$empresaCodigo] ?? ($mapas['empresas'][1] ?? Empresa::query()->value('id')),
            'fecha' => $fecha,
            'tratamiento' => SolicitudpagoTratamientos::desdeAnita($row->solpm_tratamiento ?? ''),
            'proveedor_id' => $mapas['proveedores'][$provCodigo]
                ?? $mapas['proveedores'][trim((string) ($row->solpm_proveedor ?? ''))]
                ?? null,
            'concepto_solicitudpago_id' => $mapas['conceptos'][$conceptoCodigo] ?? null,
            'formapagosol_id' => $mapas['formapagos'][$fpCodigo] ?? null,
            'moneda_id' => $mapas['monedas'][$monCodigo] ?? null,
            'beneficiario' => $this->recortar(trim((string) ($row->solpm_beneficiario ?? '')), 80) ?: null,
            'endoso' => $this->recortar(trim((string) ($row->solpm_endoso ?? '')), 80) ?: null,
            'fecha_entrega' => SolicitudpagoAnitaFechaSupport::fechaDesdeAnita($row->solpm_fecha_ent ?? 0),
            'fecha_vencimiento' => SolicitudpagoAnitaFechaSupport::fechaDesdeAnita($row->solpm_fecha_vto ?? 0),
            'monto' => (float) ($row->solpm_monto ?? 0),
            'observacion' => $this->recortar(trim((string) ($row->solpm_observacion ?? '')), 160) ?: null,
            'estado' => SolicitudpagoEstados::desdeAnita($row->solpm_estado ?? 'E'),
            'sector_solicitudpago_id' => $mapas['sectores'][$sectorCodigo] ?? null,
            'centrocosto_id' => $centrocostoId,
            'detalle' => $this->recortar(trim((string) ($row->solpm_detalle ?? '')), 180) ?: null,
            'usuario_umod_id' => $usuarioUmodId,
        ];
    }

    /**
     * @param  array<int, int>  $porCodigo
     * @param  array<string, mixed>  $mapas
     */
    private function sincronizarCuentas(ApiAnita $api, string $sistema, array $porCodigo, array $mapas): void
    {
        $filas = $this->listar($api, $sistema, 'solpagocta',
            'solpc_id, solpc_empresa, solpc_cuenta, solpc_ccosto, solpc_d_h, solpc_monto'
        );

        $idsTocados = [];
        $buffer = [];
        foreach ($filas as $row) {
            $codigoSp = (int) ($row->solpc_id ?? 0);
            $spId = $porCodigo[$codigoSp] ?? null;
            if (! $spId) {
                continue;
            }
            $idsTocados[$spId] = true;

            $empresaCodigo = (int) ($row->solpc_empresa ?? 0);
            $empresaId = $mapas['empresas'][$empresaCodigo] ?? null;
            if (! $empresaId) {
                continue;
            }
            $codigoCuenta = (string) (int) ($row->solpc_cuenta ?? 0);
            $cuenta = Cuentacontable::query()
                ->where('empresa_id', $empresaId)
                ->where(function ($q) use ($codigoCuenta) {
                    $q->where('codigo', $codigoCuenta)
                        ->orWhereRaw('CAST(codigo AS UNSIGNED) = ?', [(int) $codigoCuenta]);
                })
                ->first();
            if (! $cuenta) {
                continue;
            }

            $ccCodigo = (int) ($row->solpc_ccosto ?? 0);
            $ccId = $ccCodigo > 0
                ? ($mapas['ccostos'][(string) $ccCodigo] ?? $mapas['ccostos'][$ccCodigo] ?? null)
                : null;
            $dh = strtoupper(trim((string) ($row->solpc_d_h ?? 'D')));
            if ($dh !== 'H') {
                $dh = 'D';
            }

            $buffer[] = [
                'solicitudpago_id' => $spId,
                'empresa_id' => $empresaId,
                'cuentacontable_id' => $cuenta->id,
                'centrocosto_id' => $ccId,
                'debe_haber' => $dh,
                'monto' => (float) ($row->solpc_monto ?? 0),
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($idsTocados !== []) {
            Solicitudpago_Cuenta::query()->whereIn('solicitudpago_id', array_keys($idsTocados))->delete();
        }
        foreach (array_chunk($buffer, 500) as $chunk) {
            Solicitudpago_Cuenta::query()->insert($chunk);
        }
    }

    /**
     * @param  array<int, int>  $porCodigo
     */
    private function sincronizarCuotas(ApiAnita $api, string $sistema, array $porCodigo): void
    {
        $filas = $this->listar($api, $sistema, 'solpagocuota',
            'solpcu_id, solpcu_cuota, solpcu_fecha_vto, solpcu_monto, solpcu_id_sp'
        );

        $idsTocados = [];
        $buffer = [];
        $nroPorSp = [];
        foreach ($filas as $row) {
            $codigoSp = (int) ($row->solpcu_id ?? 0);
            $spId = $porCodigo[$codigoSp] ?? null;
            if (! $spId) {
                continue;
            }
            $fechaVto = SolicitudpagoAnitaFechaSupport::fechaDesdeAnita($row->solpcu_fecha_vto ?? 0);
            if ($fechaVto === null) {
                continue;
            }
            $idsTocados[$spId] = true;
            $hijaCodigo = (int) ($row->solpcu_id_sp ?? 0);
            $nroAnita = max(1, (int) ($row->solpcu_cuota ?? 1));
            // Evitar choque uk_sp_cuota si Anita repite nro de cuota.
            if (isset($nroPorSp[$spId][$nroAnita])) {
                $nroAnita = (isset($nroPorSp[$spId]) ? max(array_keys($nroPorSp[$spId])) : 0) + 1;
            }
            $nroPorSp[$spId][$nroAnita] = true;

            $buffer[] = [
                'solicitudpago_id' => $spId,
                'nro_cuota' => $nroAnita,
                'fecha_vencimiento' => $fechaVto,
                'monto' => (float) ($row->solpcu_monto ?? 0),
                'solicitudpago_hija_id' => $hijaCodigo > 0 ? ($porCodigo[$hijaCodigo] ?? null) : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($idsTocados !== []) {
            Solicitudpago_Cuota::query()->whereIn('solicitudpago_id', array_keys($idsTocados))->delete();
        }
        foreach (array_chunk($buffer, 500) as $chunk) {
            Solicitudpago_Cuota::query()->insert($chunk);
        }
    }

    /**
     * @param  array<int, int>  $porCodigo
     * @param  array<string, mixed>  $mapas
     */
    private function sincronizarEstados(ApiAnita $api, string $sistema, array $porCodigo, array $mapas): void
    {
        $filas = $this->listar($api, $sistema, 'solpagoest',
            'solpe_id, solpe_fecha, solpe_hora, solpe_usuario, solpe_estado_ant, solpe_estado_act, solpe_leyenda'
        );

        $idsTocados = [];
        $buffer = [];
        foreach ($filas as $row) {
            $codigoSp = (int) ($row->solpe_id ?? 0);
            $spId = $porCodigo[$codigoSp] ?? null;
            if (! $spId) {
                continue;
            }
            $fecha = SolicitudpagoAnitaFechaSupport::fechaDesdeAnita($row->solpe_fecha ?? 0);
            if ($fecha === null) {
                continue;
            }
            $idsTocados[$spId] = true;
            $anitaUsuario = (int) ($row->solpe_usuario ?? 0);
            $estadoAct = SolicitudpagoEstados::desdeAnita($row->solpe_estado_act ?? 'E');
            $estadoAntRaw = trim((string) ($row->solpe_estado_ant ?? ''));
            $estadoAnt = $estadoAntRaw === '' || $estadoAntRaw === ' '
                ? null
                : SolicitudpagoEstados::desdeAnita($estadoAntRaw);

            $buffer[] = [
                'solicitudpago_id' => $spId,
                'fecha' => $fecha,
                'hora' => $this->recortar(trim((string) ($row->solpe_hora ?? '')), 5) ?: null,
                'usuario_id' => $anitaUsuario > 0 ? ($mapas['usuarios'][$anitaUsuario] ?? null) : null,
                'estado_anterior' => $estadoAnt,
                'estado_actual' => $estadoAct,
                'leyenda' => $this->recortar(trim((string) ($row->solpe_leyenda ?? '')), 80) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($idsTocados !== []) {
            Solicitudpago_Estado::query()->whereIn('solicitudpago_id', array_keys($idsTocados))->delete();
        }
        foreach (array_chunk($buffer, 500) as $chunk) {
            Solicitudpago_Estado::query()->insert($chunk);
        }
    }

    /**
     * @param  array<int, int>  $porCodigo
     * @param  array<string, mixed>  $mapas
     */
    private function sincronizarArchivos(ApiAnita $api, string $sistema, array $porCodigo, array $mapas): void
    {
        $filas = $this->listar($api, $sistema, 'solpagoarch',
            'solpa_nro_sol, solpa_nro_linea, solpa_archivo, solpa_usuario, solpa_fecha_act, solpa_hora_act'
        );

        $idsTocados = [];
        $buffer = [];
        $lineaPorSp = [];
        $usuariosPorLogname = Usuario::query()->get(['id', 'usuario'])
            ->keyBy(fn (Usuario $u) => mb_strtolower(trim((string) $u->usuario)));

        foreach ($filas as $row) {
            $codigoSp = (int) ($row->solpa_nro_sol ?? 0);
            $spId = $porCodigo[$codigoSp] ?? null;
            if (! $spId) {
                continue;
            }
            $archivo = trim((string) ($row->solpa_archivo ?? ''));
            if ($archivo === '') {
                continue;
            }
            $idsTocados[$spId] = true;
            $logname = mb_strtolower(trim((string) ($row->solpa_usuario ?? '')));
            $usuarioId = $logname !== '' ? optional($usuariosPorLogname->get($logname))->id : null;

            // Anita puede repetir solpa_nro_linea; ERP exige unique (solicitudpago_id, nro_linea).
            $lineaPorSp[$spId] = ($lineaPorSp[$spId] ?? 0) + 1;

            $buffer[] = [
                'solicitudpago_id' => $spId,
                'nro_linea' => $lineaPorSp[$spId],
                'archivo' => $this->recortar($archivo, 255),
                'nombre_original' => $this->recortar(basename($archivo), 255),
                'usuario_id' => $usuarioId,
                'fecha' => SolicitudpagoAnitaFechaSupport::fechaDesdeAnita($row->solpa_fecha_act ?? 0),
                'hora' => $this->recortar(trim((string) ($row->solpa_hora_act ?? '')), 5) ?: null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        if ($idsTocados !== []) {
            Solicitudpago_Archivo::query()->whereIn('solicitudpago_id', array_keys($idsTocados))->delete();
        }
        foreach (array_chunk($buffer, 500) as $chunk) {
            Solicitudpago_Archivo::query()->insert($chunk);
        }
    }

    /**
     * @return array<int, int>
     */
    private function mapaAnitaUsuarioAErp(ApiAnita $api): array
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => 'compras',
            'tabla' => 'usuario',
            'campos' => 'usu_usuario, usu_logname',
        ]));

        $porLogname = Usuario::query()->get(['id', 'usuario'])
            ->keyBy(fn (Usuario $u) => mb_strtolower(trim((string) $u->usuario)));

        $mapa = [];
        foreach ($parsed['filas'] as $fila) {
            $anitaId = (int) ($fila->usu_usuario ?? 0);
            $logname = mb_strtolower(trim((string) ($fila->usu_logname ?? '')));
            if ($anitaId <= 0 || $logname === '') {
                continue;
            }
            $erp = $porLogname->get($logname);
            if ($erp) {
                $mapa[$anitaId] = (int) $erp->id;
            }
        }

        return $mapa;
    }

    /**
     * @return list<object>
     */
    private function listar(ApiAnita $api, string $sistema, string $tabla, string $campos): array
    {
        $parsed = ApiAnita::parsearRespuestaLista($api->apiCall([
            'acc' => 'list',
            'sistema' => $sistema,
            'tabla' => $tabla,
            'campos' => $campos,
        ]));

        if ($parsed['error_lectura'] !== null) {
            Log::warning('solicitudpago.anita_sync.lectura', [
                'tabla' => $tabla,
                'error' => $parsed['error_lectura'],
            ]);
        }

        return $parsed['filas'];
    }

    private function recortar(string $valor, int $len): string
    {
        if (function_exists('mb_substr')) {
            return mb_substr($valor, 0, $len);
        }

        return substr($valor, 0, $len);
    }
}
