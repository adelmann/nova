<?php

declare(strict_types=1);

namespace Nova\Models;

final class ProjectItemRepository extends BaseRepository
{
    protected string $table = 'project_item';

    /** @return array<int,array<string,mixed>> */
    public function forProject(int $projectId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM project_item WHERE project_id = :id ORDER BY item_date, id',
            ['id' => $projectId]
        );
    }

    /** Noch nicht abgerechnete Leistungen eines Projekts. @return array<int,array<string,mixed>> */
    public function unbilled(int $projectId): array
    {
        return $this->db()->fetchAll(
            'SELECT * FROM project_item WHERE project_id = :id AND billed_doc_id IS NULL ORDER BY item_date, id',
            ['id' => $projectId]
        );
    }

    /** @param array<string,mixed> $data */
    public function add(array $data): int
    {
        return $this->insert([
            'project_id'       => (int) $data['project_id'],
            'item_date'        => ((string) ($data['item_date'] ?? '')) ?: date('Y-m-d'),
            'description'      => (string) ($data['description'] ?? ''),
            'quantity'         => (float) ($data['quantity'] ?? 1),
            'unit'             => ((string) ($data['unit'] ?? 'Std')) ?: 'Std',
            'unit_price_cents' => (int) ($data['unit_price_cents'] ?? 0),
        ]);
    }

    /**
     * Markiert eine Liste von Leistungen als abgerechnet (Verweis auf das Dokument).
     *
     * @param array<int,int> $ids
     */
    public function markBilled(array $ids, string $docType, int $docId): void
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if ($ids === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $this->db()->query(
            "UPDATE project_item SET billed_doc_type = ?, billed_doc_id = ? WHERE id IN ({$placeholders})",
            array_merge([$docType, $docId], $ids)
        );
    }

    /** Summe der offenen (nicht abgerechneten) Leistungen in Cent. */
    public function unbilledTotalCents(int $projectId): int
    {
        return (int) $this->db()->fetchColumn(
            'SELECT COALESCE(SUM(ROUND(quantity * unit_price_cents)), 0)
             FROM project_item WHERE project_id = :id AND billed_doc_id IS NULL',
            ['id' => $projectId]
        );
    }
}
