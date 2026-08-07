<?php
declare(strict_types=1);
require_once __DIR__ . '/../../includes/mcp.php';
epl_mcp_json(['issuer'=>epl_mcp_base_url(),'authorization_endpoint'=>epl_mcp_url('oauth/authorize.php'),
    'token_endpoint'=>epl_mcp_url('oauth/token.php'),'registration_endpoint'=>epl_mcp_url('oauth/register.php'),
    'response_types_supported'=>['code'],'grant_types_supported'=>['authorization_code','refresh_token'],
    'code_challenge_methods_supported'=>['S256'],'token_endpoint_auth_methods_supported'=>['none'],
    'scopes_supported'=>['epl.read','epl.write']]);
