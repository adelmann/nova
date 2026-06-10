<?php

declare(strict_types=1);

namespace Nova\Models;

use Nova\Core\DB;

/**
 * Gemeinsame CRUD-Bausteine für tabellengebundene Repositories.
 */
abstract class BaseRepository
{
    protected string $table = '';

    protected function db(): DB
    {
        return DB::getInstance();
    }

    /** @return array<string,mixed>|null */
    public function find(int $id): ?array
    {
        return $this->db()->fetch(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $orderBy = 'id DESC'): array
    {
        return $this->db()->fetchAll("SELECT * FROM {$this->table} ORDER BY {$orderBy}");
    }

    public function delete(int $id): void
    {
        $this->db()->query("DELETE FROM {$this->table} WHERE id = :id", ['id' => $id]);
    }

    /**
     * Generisches Insert. Gibt die neue ID zurück.
     *
     * @param array<string,mixed> $data
     */
    protected function insert(array $data): int
    {
        $cols         = array_keys($data);
        $placeholders = array_map(static fn ($c) => ':' . $c, $cols);
        $sql          = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );
        $this->db()->query($sql, $data);
        return $this->db()->lastInsertId();
    }

    /**
     * Generisches Update anhand der id.
     *
     * @param array<string,mixed> $data
     */
    protected function updateById(int $id, array $data): void
    {
        if ($data === []) {
            return;
        }
        $set = implode(', ', array_map(static fn ($c) => "{$c} = :{$c}", array_keys($data)));
        $data['id'] = $id;
        $this->db()->query("UPDATE {$this->table} SET {$set} WHERE id = :id", $data);
    }
}
