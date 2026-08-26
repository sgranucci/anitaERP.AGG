<?php

namespace App\Services\Compras;

use App\ApiAnita;
use App\Models\Compras\Requisicion;
use App\Models\Compras\Requisicion_Estado;
use App\Models\Configuracion\Arbolaprobacion_Movimiento;
use App\Models\Seguridad\Usuario;
use App\Support\Compras\AnitaSync\AnitaUsuarioBridgeSupport;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaAprobcompMapper;
use App\Support\Compras\AnitaSync\Requisicion\RequisicionAnitaAprobcompNumeracionSupport;
use App\Support\Compras\RequisicionAnitaColisionSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Escribe el autorizante de la requi en Anita aprobcomp sin pisar el árbol nativo.
 *
 * Reglas mientras Anita sigue vivo:
 * - no borra ni actualiza filas existentes;
 * - no inserta si ya hay REQ para ese número (Anita es dueño);
 * - no inserta si la requi está en árbol / pendiente (circuito todavía puede grabar Anita);
 * - no toca reqmae;
 * - reserva aprobc_nro_int_ap en el mismo numabm que a-compprov (código 73);
 * - un fallo de Anita no revierte el ERP.
 */
class RequisicionAnitaAprobcompSyncService
{
    public const RESULTADO_INSERTADO = 'insertado';

    public const RESULTADO_OMITIDO = 'omitido';

    public const RESULTADO_ERROR = 'error';

    public function habilitado(): bool
    {
        return (bool) config('requisicion.anita.sync_activo', true)
            && (bool) config('requisicion.anita.sync_aprobcomp_activo', true);
    }

    /**
     * @return self::RESULTADO_*
     */
    public function asegurarSnapshot(Requisicion $requisicion, bool $silencioso = true): string
    {
        if (! $this->habilitado()) {
            return self::RESULTADO_OMITIDO;
        }

        try {
            return $this->asegurarSnapshotOrFail($requisicion);
        } catch (\Throwable $e) {
            Log::warning('RequisicionAnitaAprobcomp: no se pudo escribir snapshot', [
                'requisicion_id' => $requisicion->id,
                'numerorequisicion' => $requisicion->numerorequisicion,
                'error' => $e->getMessage(),
            ]);

            if (! $silencioso) {
                throw $e;
            }

            return self::RESULTADO_ERROR;
        }
    }

    /**
     * @return array{procesadas: int, insertadas: int, omitidas: int, errores: int}
     */
    public function backfill(?int $requisicionId = null, int $desdeNro = 0, int $limite = 200, bool $dryRun = false): array
    {
        $stats = [
            'procesadas' => 0,
            'insertadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        $query = $this->queryCandidatas($requisicionId, $desdeNro)->limit(max(1, $limite));

        foreach ($query->cursor() as $requisicion) {
            $stats['procesadas']++;
            if ($dryRun) {
                if ($this->debeIntentarInsertar($requisicion)) {
                    $stats['insertadas']++;
                } else {
                    $stats['omitidas']++;
                }

                continue;
            }

            $resultado = $this->asegurarSnapshot($requisicion, true);
            if ($resultado === self::RESULTADO_INSERTADO) {
                $stats['insertadas']++;
            } elseif ($resultado === self::RESULTADO_ERROR) {
                $stats['errores']++;
            } else {
                $stats['omitidas']++;
            }
        }

        return $stats;
    }

    public function contarCandidatas(?int $requisicionId = null, int $desdeNro = 0): int
    {
        return $this->queryCandidatas($requisicionId, $desdeNro)->count();
    }

    /**
     * Completa nro_int_ap / fechas en snapshots ERP ya insertados incompletos.
     * No toca filas nativas de Anita (motivo <> ERP).
     *
     * @return array{procesadas: int, reparadas: int, omitidas: int, errores: int}
     */
    public function repararIncompletos(int $limite = 900, bool $dryRun = false, ?int $numerorequisicion = null): array
    {
        $stats = [
            'procesadas' => 0,
            'reparadas' => 0,
            'omitidas' => 0,
            'errores' => 0,
        ];

        $filas = $this->listarSnapshotsErpIncompletos($limite, $numerorequisicion);
        foreach ($filas as $fila) {
            $stats['procesadas']++;
            $nro = (int) ($fila->aprobc_nro ?? 0);
            if ($nro <= 0) {
                $stats['omitidas']++;

                continue;
            }

            if ($dryRun) {
                $stats['reparadas']++;

                continue;
            }

            try {
                $this->repararSnapshotNro($nro);
                $stats['reparadas']++;
            } catch (\Throwable $e) {
                $stats['errores']++;
                Log::warning('RequisicionAnitaAprobcomp: no se pudo reparar snapshot', [
                    'numerorequisicion' => $nro,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    private function asegurarSnapshotOrFail(Requisicion $requisicion): string
    {
        if (! $this->debeIntentarInsertar($requisicion)) {
            return self::RESULTADO_OMITIDO;
        }

        $datos = $this->armarDatos($requisicion);
        if ($datos === null) {
            return self::RESULTADO_OMITIDO;
        }

        $datos['nro_int_ap'] = RequisicionAnitaAprobcompNumeracionSupport::reservarSiguiente();

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'insert',
            'sistema' => RequisicionAnitaColisionSupport::sistemaCompras(),
            'tabla' => RequisicionAnitaAprobcompMapper::TABLA,
            'campos' => implode(', ', RequisicionAnitaAprobcompMapper::camposInsert()),
            'valores' => RequisicionAnitaAprobcompMapper::valoresInsert($datos),
        ], 'aprobcomp insert REQ '.$datos['numerorequisicion']);

        Log::info('RequisicionAnitaAprobcomp: snapshot Aprobado escrito', [
            'requisicion_id' => $requisicion->id,
            'numerorequisicion' => $datos['numerorequisicion'],
            'nro_int_ap' => $datos['nro_int_ap'],
        ]);

        return self::RESULTADO_INSERTADO;
    }

    public function debeIntentarInsertar(Requisicion $requisicion): bool
    {
        $nro = (int) $requisicion->numerorequisicion;
        if ($nro <= 0) {
            return false;
        }

        if (! RequisicionAnitaAprobcompMapper::correspondeSnapshot(
            (string) $requisicion->estado,
            $this->tieneHistoriaAprobada((int) $requisicion->id)
        )) {
            return false;
        }

        if (! RequisicionAnitaColisionSupport::existeNroEnReqmae($nro)) {
            return false;
        }

        return ! $this->existeReqEnAprobcomp($nro);
    }

    private function existeReqEnAprobcomp(int $numerorequisicion): bool
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => RequisicionAnitaColisionSupport::sistemaCompras(),
            'tabla' => RequisicionAnitaAprobcompMapper::TABLA,
            'campos' => 'COUNT(*) as cant',
            'whereArmado' => RequisicionAnitaAprobcompMapper::whereReq($numerorequisicion),
        ]);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo leer aprobcomp REQ '.$numerorequisicion.': '.$err);
        }

        $fila = ApiAnita::primeraFilaLista($raw);

        return (int) ($fila->cant ?? 0) > 0;
    }

    /**
     * @return array{numerorequisicion: int, empresa: int, proveedor: string, usuario_anita: int, usuario_nombre: string, fecha_ymd: int, hora_hm: string}|null
     */
    private function armarDatos(Requisicion $requisicion): ?array
    {
        $requisicion->loadMissing(['empresas', 'proveedores']);

        $erpUsuarioId = $this->autorizanteErpId((int) $requisicion->id);
        if ($erpUsuarioId <= 0) {
            Log::info('RequisicionAnitaAprobcomp: sin autorizante ERP, se omite', [
                'requisicion_id' => $requisicion->id,
            ]);

            return null;
        }

        $usuarioAnita = AnitaUsuarioBridgeSupport::usuUsuarioDesdeErpId($erpUsuarioId);
        if ($usuarioAnita <= 0) {
            Log::info('RequisicionAnitaAprobcomp: autorizante sin usu_usuario Anita, se omite', [
                'requisicion_id' => $requisicion->id,
                'erp_usuario_id' => $erpUsuarioId,
            ]);

            return null;
        }

        $cuando = $this->fechaAprobacion($requisicion);

        return [
            'numerorequisicion' => (int) $requisicion->numerorequisicion,
            'empresa' => (int) ($requisicion->empresas?->codigo ?? $requisicion->empresa_id ?? 1),
            'proveedor' => (string) ($requisicion->proveedores?->codigo ?? '0'),
            'usuario_anita' => $usuarioAnita,
            'usuario_nombre' => $this->nombreAutorizante($erpUsuarioId, $usuarioAnita),
            'fecha_ymd' => (int) $cuando->format('Ymd'),
            'hora_hm' => $cuando->format('H:i'),
        ];
    }

    private function fechaAprobacion(Requisicion $requisicion): Carbon
    {
        $fecha = Requisicion_Estado::query()
            ->where('requisicion_id', $requisicion->id)
            ->where('estado', 'APROBADA')
            ->orderByDesc('id')
            ->value('fecha');

        try {
            return $fecha ? Carbon::parse((string) $fecha) : Carbon::now();
        } catch (\Throwable $e) {
            return Carbon::now();
        }
    }

    private function nombreAutorizante(int $erpUsuarioId, int $usuarioAnita): string
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => RequisicionAnitaColisionSupport::sistemaCompras(),
            'tabla' => 'usuario',
            'campos' => 'usu_nombre',
            'whereArmado' => ' WHERE usu_usuario = '.(int) $usuarioAnita,
            'limit' => 'FIRST 1',
        ]);
        $fila = ApiAnita::primeraFilaLista($raw);
        $anita = trim((string) ($fila->usu_nombre ?? ''));
        if ($anita !== '') {
            return $anita;
        }

        return trim((string) (Usuario::query()->whereKey($erpUsuarioId)->value('nombre') ?? ''));
    }

    /** @return list<object> */
    private function listarSnapshotsErpIncompletos(int $limite, ?int $numerorequisicion = null): array
    {
        $api = new ApiAnita;
        $raw = $api->apiCall([
            'acc' => 'list',
            'sistema' => RequisicionAnitaColisionSupport::sistemaCompras(),
            'tabla' => RequisicionAnitaAprobcompMapper::TABLA,
            'campos' => 'aprobc_nro, aprobc_nro_int_ap, aprobc_fecha_envio, aprobc_motivo',
            'whereArmado' => RequisicionAnitaAprobcompMapper::whereSnapshotsErpIncompletos($numerorequisicion),
        ]);

        $err = ApiAnita::extraerMensajeError($raw);
        if ($err !== null) {
            throw new \RuntimeException('No se pudo listar snapshots ERP incompletos: '.$err);
        }

        $salida = [];
        foreach (ApiAnita::decodificarListaFilas($raw) as $fila) {
            if (! RequisicionAnitaAprobcompMapper::snapshotIncompleto(
                isset($fila->aprobc_nro_int_ap) ? (string) $fila->aprobc_nro_int_ap : null,
                isset($fila->aprobc_fecha_envio) ? (string) $fila->aprobc_fecha_envio : null,
            )) {
                continue;
            }
            $salida[] = $fila;
            if (count($salida) >= $limite) {
                break;
            }
        }

        return $salida;
    }

    private function repararSnapshotNro(int $numerorequisicion): void
    {
        $requisicion = Requisicion::query()->where('numerorequisicion', $numerorequisicion)->first();
        if ($requisicion === null) {
            throw new \RuntimeException('Requisición ERP no encontrada para nro '.$numerorequisicion);
        }

        $datos = $this->armarDatos($requisicion);
        if ($datos === null) {
            throw new \RuntimeException('Sin autorizante para reparar nro '.$numerorequisicion);
        }

        $datos['nro_int_ap'] = RequisicionAnitaAprobcompNumeracionSupport::reservarSiguiente();

        $api = new ApiAnita;
        $api->apiCallEscritura([
            'acc' => 'update',
            'sistema' => RequisicionAnitaColisionSupport::sistemaCompras(),
            'tabla' => RequisicionAnitaAprobcompMapper::TABLA,
            'valores' => RequisicionAnitaAprobcompMapper::valoresUpdateIncompleto($datos),
            'whereArmado' => RequisicionAnitaAprobcompMapper::whereSnapshotErp($numerorequisicion),
        ], 'aprobcomp update REQ '.$numerorequisicion, exigirFilasAfectadas: true);
    }

    private function tieneHistoriaAprobada(int $requisicionId): bool
    {
        return Requisicion_Estado::query()
            ->where('requisicion_id', $requisicionId)
            ->where('estado', 'APROBADA')
            ->exists();
    }

    private function autorizanteErpId(int $requisicionId): int
    {
        $ultimaAprobada = Requisicion_Estado::query()
            ->where('requisicion_id', $requisicionId)
            ->where('estado', 'APROBADA')
            ->orderByDesc('id')
            ->first(['usuario_id']);

        $ultimoArbol = Arbolaprobacion_Movimiento::query()
            ->where('requisicion_id', $requisicionId)
            ->where('estado', 'Aprobado')
            ->orderByDesc('id')
            ->first(['destinatariousuario_id', 'enviousuario_id']);

        return RequisicionAnitaAprobcompMapper::autorizanteErpId(
            $ultimaAprobada ? ['usuario_id' => (int) $ultimaAprobada->usuario_id] : null,
            $ultimoArbol ? [
                'destinatario_id' => (int) ($ultimoArbol->destinatariousuario_id ?? 0),
                'enviador_id' => (int) ($ultimoArbol->enviousuario_id ?? 0),
            ] : null,
        );
    }

    /** @return Builder<Requisicion> */
    private function queryCandidatas(?int $requisicionId, int $desdeNro): Builder
    {
        $query = Requisicion::query()
            ->where('numerorequisicion', '>', 0)
            ->whereNotIn('estado', RequisicionAnitaAprobcompMapper::ESTADOS_ANITA_VIVO)
            ->whereExists(function ($sub) {
                $sub->selectRaw('1')
                    ->from('requisicion_estado as re')
                    ->whereColumn('re.requisicion_id', 'requisicion.id')
                    ->where('re.estado', 'APROBADA');
            })
            ->orderBy('id');

        if ($requisicionId !== null && $requisicionId > 0) {
            $query->whereKey($requisicionId);
        }

        if ($desdeNro > 0) {
            $query->where('numerorequisicion', '>=', $desdeNro);
        }

        return $query;
    }
}
