<?php
if (!defined('IS_ADMIN_FLAG')) {
    exit;
}

$autoLoadConfig[185][] = [
    'autoType' => 'init_script',
    'loadFile' => 'init_prowebs_edge.php',
];
