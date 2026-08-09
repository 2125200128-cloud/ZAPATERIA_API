<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

class SincronizarRespaldoSqlite extends Command
{
    protected $signature = 'respaldo:sqlite';

    protected $description = 'Copia la estructura y los datos de MariaDB hacia SQLite';

    public function handle(): int
    {
        try {
            DB::connection('mariadb')->getPdo();
        } catch (Throwable $e) {
            $this->error('No se pudo conectar con MariaDB.');
            $this->line($e->getMessage());

            return self::FAILURE;
        }

        $sqlite = DB::connection('sqlite_backup');
        $mariadb = DB::connection('mariadb'); // Corregido: antes decía mysql

        $sqlite->statement('PRAGMA foreign_keys = OFF');

        $tablasIgnoradas = [
            'migrations',
            'cache',
            'cache_locks',
            'jobs',
            'job_batches',
            'failed_jobs',
            'sessions',
            'password_reset_tokens',
        ];

        $tablas = collect(
            $mariadb->select('SHOW TABLES')
        )->map(function ($fila) {
            return array_values((array) $fila)[0];
        })->reject(function ($tabla) use ($tablasIgnoradas) {
            return in_array($tabla, $tablasIgnoradas, true);
        })->values();

        foreach ($tablas as $tabla) {
            $this->info("Procesando: {$tabla}");

            $sqlite->statement("DROP TABLE IF EXISTS \"$tabla\"");

            $columnas = $mariadb->select("SHOW COLUMNS FROM `$tabla`");

            $definiciones = [];
            $clavePrimaria = [];

            foreach ($columnas as $columna) {
                $nombre = $columna->Field;
                $tipo = strtolower($columna->Type);
                $permiteNull = $columna->Null === 'YES';
                $esPrimary = $columna->Key === 'PRI';
                $extra = strtolower($columna->Extra ?? '');

                $tipoSqlite = $this->convertirTipo($tipo);

                $definicion = "\"{$nombre}\" {$tipoSqlite}";

                if ($esPrimary && str_contains($extra, 'auto_increment')) {
                    $definicion = "\"{$nombre}\" INTEGER PRIMARY KEY AUTOINCREMENT";
                } else {
                    if (!$permiteNull) {
                        $definicion .= ' NOT NULL';
                    }

                    if ($esPrimary) {
                        $clavePrimaria[] = "\"{$nombre}\"";
                    }
                }

                $definiciones[] = $definicion;
            }

            if (count($clavePrimaria) > 0) {
                $definiciones[] = 'PRIMARY KEY (' . implode(', ', $clavePrimaria) . ')';
            }

            $sqlCrear = sprintf(
                'CREATE TABLE "%s" (%s)',
                $tabla,
                implode(', ', $definiciones)
            );

            $sqlite->statement($sqlCrear);

            $registros = $mariadb->table($tabla)->get();

            if ($registros->isEmpty()) {
                continue;
            }

            foreach ($registros->chunk(200) as $bloque) {
                $datos = $bloque->map(function ($registro) {
                    return (array) $registro;
                })->all();

                $sqlite->table($tabla)->insert($datos);
            }

            $this->line("  {$registros->count()} registros copiados.");
        }

        $sqlite->statement('PRAGMA foreign_keys = ON');

        $this->newLine();
        $this->info('Respaldo SQLite creado correctamente.');

        return self::SUCCESS;
    }

    private function convertirTipo(string $tipo): string
    {
        return match (true) {
            str_contains($tipo, 'int') => 'INTEGER',
            str_contains($tipo, 'decimal'),
            str_contains($tipo, 'double'),
            str_contains($tipo, 'float') => 'REAL',
            str_contains($tipo, 'blob'),
            str_contains($tipo, 'binary') => 'BLOB',
            default => 'TEXT',
        };
    }
}