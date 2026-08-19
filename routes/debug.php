<?php
declare(strict_types=1);

require_once ROOT.'/routes/layout-studio.php';

if ($path === '/debug') {
    require_permission('security.manage');
    erased_debug_page();
}
