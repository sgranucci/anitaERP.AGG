<?php

namespace App\Console\Commands;

use App\ApiAnita;
use App\Models\Caja\TesoreriaCrownDeposito;
use App\Models\Caja\TesoreriaCrownMesaJuego;
use App\Support\Configuracion\EntornoEmpresaSupport;
use App\Support\Ventas\GastronomiaSalaSupport;
use Illuminate\Console\Command;
use RuntimeException;

class SincronizarTiposJuegoMesaCrownDesdeAnita extends Command
{
    protected $signature = 'tesoreria-crown:sincronizar-tipos-juego-mesa-anita
                            {--ejecutar : Graba. Sin este flag solo muestra el plan}';

    protected $description = 'Sincroniza mesaj_desc_tipoj de Anita a tesoreria_crown_mesa_juego.';

    public function handle(): int
    {
        if (! EntornoEmpresaSupport::esCrown()) {
            $this->error('Solo corre en Crown.');

            return self::FAILURE;
        }

        try {
            $plan = $this->plan();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $filas = [];
        foreach ($plan['filas'] as $fila) {
            $filas[] = [
                $fila['mesa'],
                $fila['accion'],
                $fila['desc'],
                $fila['banca'],
            ];
        }
        $this->table(['Mesa', 'Acción', 'Tipo juego', 'Banca'], $filas);
        $this->info(sprintf(
            'Crear: %d. Actualizar: %d. Omitir: %d.',
            $plan['crear'],
            $plan['actualizar'],
            $plan['omitir']
        ));

        if (! $this->option('ejecutar')) {
            $this->warn('Sin --ejecutar no se grabó nada.');

            return self::SUCCESS;
        }

        $altas = 0;
        $cambios = 0;
        foreach ($plan['filas'] as $fila) {
            if ($fila['accion'] === 'crear') {
                TesoreriaCrownMesaJuego::query()->create($fila['datos']);
                $altas++;
            } elseif ($fila['accion'] === 'actualizar' && $fila['id']) {
                $row = TesoreriaCrownMesaJuego::query()->find($fila['id']);
                if ($row) {
                    $row->fill($fila['datos'])->save();
                    $cambios++;
                }
            }
        }
        $this->info('Altas: '.$altas.'. Cambios: '.$cambios.'.');

        return self::SUCCESS;
    }

    /**
     * @return array{crear: int, actualizar: int, omitir: int, filas: list<array<string, mixed>>}
     */
    private function plan(): array
    {
        $codigoSala = strtoupper(trim((string) config('caja_anita.sala_codigo', 'CIP')));
        $salaId = GastronomiaSalaSupport::idPorCodigo($codigoSala);
        if (! $salaId) {
            throw new RuntimeException('No está la sala '.$codigoSala.'.');
        }

        $depositos = TesoreriaCrownDeposito::query()
            ->where('sala_id', $salaId)
            ->where('codigo', 'like', 'M%')
            ->get()
            ->keyBy(function (TesoreriaCrownDeposito $d) {
                return (int) preg_replace('/\D+/', '', (string) $d->codigo);
            });

        $crear = $actualizar = $omitir = 0;
        $filas = [];
        foreach ($this->consultar(
            'm,dj,b',
            'SELECT mesaj_mesajuego m, mesaj_desc_tipoj dj, mesaj_tipoj_banca b FROM mesajuego'
        ) as $fila) {
            $mesa = (int) ($fila->m ?? 0);
            $desc = trim((string) ($fila->dj ?? ''));
            $banca = (int) ($fila->b ?? 0);
            if ($mesa <= 0) {
                continue;
            }
            $deposito = $depositos->get($mesa);
            if (! $deposito) {
                $omitir++;
                $filas[] = [
                    'mesa' => $mesa,
                    'accion' => 'omitir',
                    'desc' => $desc.' (sin depósito)',
                    'banca' => $banca,
                    'id' => null,
                    'datos' => [],
                ];

                continue;
            }
            $datos = [
                'deposito_id' => (int) $deposito->id,
                'banca_codigo' => $banca > 0 ? $banca : 0,
                'desc_tipojuego' => $desc !== '' ? mb_substr($desc, 0, 40) : null,
            ];
            $existente = TesoreriaCrownMesaJuego::query()
                ->where('deposito_id', $deposito->id)
                ->first();
            if ($existente === null) {
                $accion = 'crear';
                $crear++;
            } elseif (
                (int) $existente->banca_codigo !== (int) $datos['banca_codigo']
                || (string) ($existente->desc_tipojuego ?? '') !== (string) ($datos['desc_tipojuego'] ?? '')
            ) {
                $accion = 'actualizar';
                unset($datos['deposito_id']);
                $actualizar++;
            } else {
                $accion = 'omitir';
                $omitir++;
            }
            $filas[] = [
                'mesa' => $mesa,
                'accion' => $accion,
                'desc' => $desc,
                'banca' => $banca,
                'id' => $existente?->id,
                'datos' => $datos,
            ];
        }

        return compact('crear', 'actualizar', 'omitir', 'filas');
    }

    /**
     * @return list<object>
     */
    private function consultar(string $campos, string $sql): array
    {
        $api = new ApiAnita;
        $payload = [
            'acc' => 'customSql',
            'campos' => $campos,
            'customSql' => $sql,
            'sistema' => config('caja_anita.sistema'),
        ];
        $ifx = trim((string) config('caja_anita.ifx_server', ''));
        if ($ifx !== '') {
            $payload['ifx_server'] = $ifx;
        }
        $path = rtrim(trim((string) config('caja_anita.path_sistema', '')), '/');
        if ($path !== '') {
            $payload['path_sistema'] = $path;
        }
        $raw = $api->apiCall($payload);
        $error = ApiAnita::extraerMensajeError(is_string($raw) ? $raw : null);
        if ($error !== null && $error !== '') {
            throw new RuntimeException($error);
        }
        $decoded = json_decode(is_string($raw) ? $raw : '[]');

        return is_array($decoded) ? $decoded : [];
    }
}
