<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/gpt_actions.php';
epl_gpt_require_method('GET');

$string = ['type' => 'string'];
$integer = ['type' => 'integer'];
$boolean = ['type' => 'boolean'];
$okResponse = ['description' => 'Operación completada.', 'content' => ['application/json' => ['schema' => [
    'type' => 'object', 'properties' => ['ok' => ['type'=>'boolean'], 'data' => new stdClass()]
]]]];
$errorResponse = ['description' => 'Solicitud inválida o no autorizada.', 'content' => ['application/json' => ['schema' => [
    'type' => 'object', 'properties' => ['ok'=>['type'=>'boolean'], 'error'=>['type'=>'string']]
]]]];
$query = static fn(string $name, array $schema, string $description, bool $required=false): array => [
    'name'=>$name,'in'=>'query','required'=>$required,'description'=>$description,'schema'=>$schema
];

$doc = [
    'openapi' => '3.1.0',
    'info' => [
        'title' => 'Elite Padel League Actions',
        'version' => '1.0.0',
        'description' => 'API segura para consultar y gestionar Elite Padel League según el rol del usuario autenticado.',
    ],
    'externalDocs' => ['description'=>'Política de privacidad','url'=>rtrim(epl_env('APP_URL', 'https://epleague.cl'), '/') . '/privacidad_ia.php'],
    'servers' => [['url' => epl_gpt_base_url()]],
    'security' => [['eplOAuth' => ['epl.read', 'epl.write']]],
    'paths' => [
        '/me.php' => ['get' => [
            'operationId'=>'obtenerMiPerfilEPL','summary'=>'Obtener la cuenta EPL conectada','description'=>'Devuelve identidad, rol y ligas autorizadas del usuario.','responses'=>['200'=>$okResponse,'401'=>$errorResponse]
        ]],
        '/ligas.php' => ['get' => [
            'operationId'=>'listarMisLigas','summary'=>'Listar ligas autorizadas','description'=>'Un jugador ve sus ligas, un club las asignadas y un administrador todas.','responses'=>['200'=>$okResponse,'401'=>$errorResponse]
        ]],
        '/partidos.php' => ['get' => [
            'operationId'=>'buscarPartidosEPL','summary'=>'Buscar partidos','description'=>'Busca únicamente partidos visibles para el usuario autenticado.',
            'parameters'=>[
                $query('liga_id',$integer,'ID de liga.'),
                $query('estado',['type'=>'string','enum'=>['pendiente','reprogramado','jugado','walkover','no_presentado']],'Estado del partido.'),
                $query('desde',['type'=>'string','format'=>'date'],'Fecha inicial YYYY-MM-DD.'),
                $query('hasta',['type'=>'string','format'=>'date'],'Fecha final YYYY-MM-DD.'),
                $query('buscar',$string,'Nombre de pareja o jugador.'),
                $query('limite',['type'=>'integer','minimum'=>1,'maximum'=>100],'Máximo de resultados.'),
            ],'responses'=>['200'=>$okResponse,'400'=>$errorResponse,'401'=>$errorResponse]
        ]],
        '/partido.php' => ['get' => [
            'operationId'=>'verDetallePartidoEPL','summary'=>'Ver un partido','description'=>'Obtiene el detalle completo de un partido autorizado.',
            'parameters'=>[$query('partido_id',$integer,'ID del partido.',true)],'responses'=>['200'=>$okResponse,'400'=>$errorResponse,'401'=>$errorResponse]
        ]],
        '/reprogramaciones.php' => ['get' => [
            'operationId'=>'verReprogramacionesEPL','summary'=>'Ver reprogramaciones','description'=>'Lista reprogramaciones visibles y solicitudes pendientes.',
            'parameters'=>[$query('liga_id',$integer,'ID de liga.'),$query('limite',['type'=>'integer','minimum'=>1,'maximum'=>100],'Máximo de resultados.')],
            'responses'=>['200'=>$okResponse,'400'=>$errorResponse,'401'=>$errorResponse]
        ]],
        '/recintos.php' => ['get' => [
            'operationId'=>'listarRecintosEPL','summary'=>'Listar recintos','description'=>'Solo administradores. Lista recintos activos para asignar partidos.',
            'responses'=>['200'=>$okResponse,'400'=>$errorResponse,'401'=>$errorResponse]
        ]],
        '/solicitar-reprogramacion.php' => ['post' => [
            'operationId'=>'solicitarReprogramacionEPL','summary'=>'Solicitar reprogramación','description'=>'Crea una solicitud en un partido propio. Antes de llamar, resume todo y pide confirmación explícita al usuario.','x-openai-isConsequential'=>true,
            'requestBody'=>['required'=>true,'content'=>['application/json'=>['schema'=>[
                'type'=>'object','additionalProperties'=>false,'required'=>['partido_id','motivo','confirmar'],'properties'=>[
                    'partido_id'=>$integer,
                    'motivo'=>['type'=>'string','minLength'=>3,'maxLength'=>500],
                    'fecha_propuesta'=>['type'=>'string','description'=>'YYYY-MM-DD HH:MM.'],
                    'rival_no_responde'=>$boolean,
                    'mutuo_acuerdo'=>$boolean,
                    'confirmar'=>['type'=>'boolean','description'=>'Debe ser true únicamente después de la confirmación explícita del usuario.'],
                ]
            ]]]],'responses'=>['200'=>$okResponse,'400'=>$errorResponse,'401'=>$errorResponse]
        ]],
        '/administrar-partido.php' => ['post' => [
            'operationId'=>'administrarPartidoEPL','summary'=>'Modificar un partido','description'=>'Exclusivo para administradores. Modifica fecha, recinto, estado o alerta. Antes de llamar, muestra un resumen exacto y pide confirmación explícita.','x-openai-isConsequential'=>true,
            'requestBody'=>['required'=>true,'content'=>['application/json'=>['schema'=>[
                'type'=>'object','additionalProperties'=>false,'required'=>['partido_id','confirmar'],'properties'=>[
                    'partido_id'=>$integer,
                    'fecha_programada'=>['type'=>['string','null'],'description'=>'YYYY-MM-DD HH:MM o null para dejar sin fecha.'],
                    'recinto_id'=>['type'=>['integer','null']],
                    'estado'=>['type'=>'string','enum'=>['pendiente','reprogramado','jugado','walkover','no_presentado']],
                    'alerta_admin'=>['type'=>['string','null'],'maxLength'=>500],
                    'confirmar'=>['type'=>'boolean','description'=>'Debe ser true únicamente después de la confirmación explícita del administrador.'],
                ]
            ]]]],'responses'=>['200'=>$okResponse,'400'=>$errorResponse,'401'=>$errorResponse]
        ]],
    ],
    'components' => ['securitySchemes' => ['eplOAuth' => [
        'type'=>'oauth2','flows'=>['authorizationCode'=>[
            'authorizationUrl'=>epl_gpt_url('oauth/authorize.php'),
            'tokenUrl'=>epl_gpt_url('oauth/token.php'),
            'scopes'=>['epl.read'=>'Consultar información permitida de EPL.','epl.write'=>'Solicitar o realizar cambios permitidos en EPL.']
        ]]
    ]]],
];

epl_gpt_json($doc);
