<?php

declare(strict_types=1);

namespace Nova\Controllers;

use Nova\Core\Controller;
use Nova\Core\Request;
use Nova\Core\Version;
use Nova\Services\UpdateService;

final class AboutController extends Controller
{
    public function index(Request $request): void
    {
        $this->view('about/index', [
            'title'   => 'Über Nova',
            'version' => Version::CURRENT,
            'repo'    => (string) ($GLOBALS['nova_config']['github_repo'] ?? ''),
            'update'  => UpdateService::cached(),
        ]);
    }
}
