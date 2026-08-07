<?php
declare(strict_types=1);
require_once __DIR__ . '/../../../includes/mcp.php';
epl_mcp_json(['resource'=>epl_mcp_base_url().'/','authorization_servers'=>[epl_mcp_base_url()],
    'bearer_methods_supported'=>['header'],'scopes_supported'=>['epl.read','epl.write']]);
