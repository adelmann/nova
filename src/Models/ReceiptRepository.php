<?php

declare(strict_types=1);

namespace Nova\Models;

final class ReceiptRepository extends BaseRepository
{
    protected string $table = 'receipt';

    /**
     * @param array{stored_path:string,original_name:string,mime:string,size_bytes:int,sha256:string} $meta
     */
    public function createFromUpload(array $meta, string $type, ?string $linkableType = null, ?int $linkableId = null): int
    {
        return $this->insert([
            'stored_path'   => $meta['stored_path'],
            'original_name' => $meta['original_name'],
            'mime'          => $meta['mime'],
            'size_bytes'    => $meta['size_bytes'],
            'sha256'        => $meta['sha256'],
            'type'          => $type,
            'linkable_type' => $linkableType,
            'linkable_id'   => $linkableId,
            'locked'        => $linkableType !== null ? 1 : 0,
        ]);
    }

    /**
     * Volltextsuche über Originalname und Typ.
     *
     * @return array<int,array<string,mixed>>
     */
    public function search(string $term = ''): array
    {
        if ($term === '') {
            return $this->db()->fetchAll('SELECT * FROM receipt ORDER BY created_at DESC');
        }
        $like = '%' . $term . '%';
        return $this->db()->fetchAll(
            'SELECT * FROM receipt WHERE original_name LIKE :t OR type LIKE :t ORDER BY created_at DESC',
            ['t' => $like]
        );
    }

    /** @return array<int,array<string,mixed>> */
    public function forLinkable(string $type, int $id): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM receipt WHERE linkable_type = :t AND linkable_id = :id ORDER BY created_at',
            ['t' => $type, 'id' => $id]
        );
    }

    public function link(int $receiptId, string $type, int $id): void
    {
        $this->updateById($receiptId, ['linkable_type' => $type, 'linkable_id' => $id, 'locked' => 1]);
    }

    /** @return array<int,array<string,mixed>> */
    public function unlinked(): array
    {
        return $this->db()->fetchAll('SELECT * FROM receipt WHERE linkable_id IS NULL ORDER BY created_at DESC');
    }

    public function countForYear(int $year): int
    {
        return (int) $this->db()->fetchColumn(
            "SELECT COUNT(*) FROM receipt WHERE strftime('%Y', created_at) = :y",
            ['y' => (string) $year]
        );
    }
}
