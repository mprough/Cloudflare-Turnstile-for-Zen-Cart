<?php
if (!defined('IS_ADMIN_FLAG')) {
    exit;
}

if (!empty($_SESSION['admin_id']) && zen_is_superuser() && !zen_page_key_exists('prowebsEdgeChallenge')) {
    zen_register_admin_page(
        'prowebsEdgeChallenge',
        'BOX_TOOLS_PROWEBS_EDGE_CHALLENGE',
        'FILENAME_PROWEBS_EDGE_CHALLENGE',
        '',
        'tools',
        'Y'
    );
}
