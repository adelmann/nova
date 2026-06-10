<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\DB;
use Nova\Core\Request;

final class AuditController extends Controller
{
    public function index(Request $request): void
    {
        $entityType = $request->str('typ');
        $page       = max(1, $request->int('seite', 1));
        $perPage    = 100;
        $offset     = ($page - 1) * $perPage;

        $where  = [];
        $params = [];
        if ($entityType !== '') {
            $where[]          = 'entity_type = :et';
            $params['et']     = $entityType;
        }
        $whereSql = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);

        $total = (int) DB::getInstance()->fetchColumn("SELECT COUNT(*) FROM audit_log{$whereSql}", $params);
        $entries = DB::getInstance()->fetchAll(
            "SELECT * FROM audit_log{$whereSql} ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}",
            $params
        );

        $types = array_column(
            DB::getInstance()->fetchAll('SELECT DISTINCT entity_type FROM audit_log ORDER BY entity_type'),
            'entity_type'
        );

        $this->view('audit/index', [
            'title'    => 'Änderungsprotokoll',
            'entries'  => $entries,
            'types'    => $types,
            'type'     => $entityType,
            'page'     => $page,
            'perPage'  => $perPage,
            'total'    => $total,
        ]);
    }
}
