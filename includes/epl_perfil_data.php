<?php
declare(strict_types=1);

function epl_lista_profesiones(): array {
    return [
        'Arquitectura / Diseño', 'Arte / Creatividad', 'Comercial / Ventas',
        'Construcción / Inmobiliaria', 'Derecho / Jurídico', 'Educación / Docencia',
        'Emprendedor / Independiente', 'Finanzas / Banca', 'Gerencia / Dirección',
        'Ingeniería / Construcción', 'Logística / Operaciones', 'Minería / Energía',
        'Publicidad / Marketing', 'Recursos Humanos', 'Salud / Medicina',
        'Estudiante', 'Tecnología / IT', 'Otro',
    ];
}

function epl_lista_comunas_chile(): array {
    return [
        'Alhué', 'Alto Biobío', 'Alto del Carmen', 'Alto Hospicio', 'Andacollo', 'Angol',
        'Antártica', 'Antofagasta', 'Arauco', 'Arica', 'Aysén', 'Buin', 'Bulnes',
        'Cabo de Hornos', 'Cabildo', 'Calama', 'Caldera', 'Calera', 'Calera de Tango',
        'Camarones', 'Camiña', 'Cañete', 'Carahue', 'Cartagena', 'Castro', 'Catemu',
        'Cauquenes', 'Chanco', 'Chañaral', 'Chaitén', 'Chépica', 'Chile Chico',
        'Chiguayante', 'Chillán', 'Chillán Viejo', 'Chimbarongo', 'Cholchol',
        'Cisnes', 'Cobquecura', 'Cochrane', 'Codegua', 'Coelemu', 'Coihueco', 'Coihaique',
        'Coinco', 'Colbún', 'Colchane', 'Colina', 'Coltauco', 'Combarbalá', 'Concepción',
        'Conchalí', 'Concón', 'Constitución', 'Contulmo', 'Coquimbo', 'Coronel', 'Corral',
        'Cunco', 'Curacaví', 'Curacautín', 'Curanilahue', 'Curarrehue', 'Curepto', 'Curicó',
        'Curaco de Vélez', 'Dalcahue', 'Diego de Almagro', 'Doñihue',
        'El Bosque', 'El Carmen', 'El Monte', 'El Quisco', 'El Tabo',
        'Empedrado', 'Ercilla', 'Estación Central', 'Florida', 'Freire', 'Fresia',
        'Freirina', 'Frutillar', 'Futrono', 'Futaleufú', 'Galvarino', 'General Lagos',
        'Gorbea', 'Graneros', 'Guaitecas', 'Hijuelas', 'Hualaihué', 'Hualañé',
        'Hualqui', 'Hualpén', 'Huara', 'Huechuraba', 'Illapel', 'Independencia',
        'Iquique', 'Isla de Maipo', 'Isla de Pascua', 'Juan Fernández',
        'La Cisterna', 'La Cruz', 'La Estrella', 'La Florida', 'La Granja',
        'La Higuera', 'La Ligua', 'La Pintana', 'La Reina', 'La Serena', 'La Unión',
        'Lago Ranco', 'Lago Verde', 'Laguna Blanca', 'Laja', 'Lampa', 'Lanco',
        'Las Cabras', 'Las Condes', 'Lautaro', 'Lebu', 'Licantén', 'Limache',
        'Linares', 'Llaillay', 'Lo Barnechea', 'Lo Espejo', 'Lo Prado',
        'Lolol', 'Loncoche', 'Lonquimay', 'Los Álamos', 'Los Ángeles', 'Los Lagos',
        'Los Muermos', 'Los Sauces', 'Los Vilos', 'Lota', 'Llanquihue', 'Lumaco',
        'Machalí', 'Macul', 'Maipú', 'Malloa', 'María Elena', 'María Pinto',
        'Mariquina', 'Maule', 'Maullín', 'Máfil', 'Mejillones', 'Melipeuco',
        'Melipilla', 'Monte Patria', 'Mostazal', 'Mulchén', 'Nacimiento', 'Nancagua',
        'Natales', 'Navidad', 'Negrete', 'Ninhue', 'Nogales', 'Nueva Imperial',
        'Ñiquén', 'Ñuñoa', 'O\'Higgins', 'Olivar', 'Olmué', 'Osorno', 'Ovalle',
        'Padre Hurtado', 'Padre las Casas', 'Paillaco', 'Paihuano', 'Palena',
        'Palmilla', 'Panquehue', 'Panguipulli', 'Papudo', 'Paredones', 'Parral',
        'Pedro Aguirre Cerda', 'Pelarco', 'Pelluhue', 'Pemuco', 'Pencahue', 'Penco',
        'Peñaflor', 'Peñalolén', 'Peralillo', 'Perquenco', 'Petorca', 'Peumo',
        'Pichidegua', 'Pichilemu', 'Pinto', 'Pirque', 'Pitrufquén', 'Placilla',
        'Porvenir', 'Pozo Almonte', 'Primavera', 'Providencia', 'Puchuncaví',
        'Pucón', 'Pudahuel', 'Puente Alto', 'Pumanque', 'Punitaqui', 'Punta Arenas',
        'Puqueldón', 'Puerto Montt', 'Puerto Octay', 'Puerto Varas',
        'Purén', 'Purranque', 'Putaendo', 'Puyehue',
        'Queilén', 'Quellón', 'Quemchi', 'Quilaco', 'Quilicura', 'Quilleco',
        'Quillón', 'Quillota', 'Quinchao', 'Quinta Normal', 'Quinta de Tilcoco',
        'Quintero', 'Quirihue', 'Rancagua', 'Ránquil', 'Rauco', 'Recoleta',
        'Renca', 'Renaico', 'Rengo', 'Requínoa', 'Retiro', 'Río Bueno', 'Río Claro',
        'Río Hurtado', 'Río Ibáñez', 'Río Negro', 'Río Verde', 'Romeral',
        'Saavedra', 'Sagrada Familia', 'Salamanca', 'San Bernardo', 'San Carlos',
        'San Clemente', 'San Esteban', 'San Fabián', 'San Felipe', 'San Fernando',
        'San Gregorio', 'San Ignacio', 'San Javier', 'San José de Maipo',
        'San Juan de la Costa', 'San Miguel', 'San Nicolás', 'San Pablo',
        'San Pedro', 'San Pedro de Atacama', 'San Pedro de la Paz', 'San Rafael',
        'San Ramón', 'San Rosendo', 'San Vicente',
        'Santa Bárbara', 'Santa Cruz', 'Santa Juana', 'Santa María', 'Santo Domingo',
        'Santiago', 'Sierra Gorda', 'Talca', 'Talcahuano', 'Talagante', 'Taltal',
        'Temuco', 'Teno', 'Teodoro Schmidt', 'Tierra Amarilla', 'Tiltil', 'Timaukel',
        'Tirúa', 'Tocopilla', 'Toltén', 'Tomé', 'Torres del Paine', 'Tortel',
        'Traiguén', 'Trehuaco', 'Tucapel', 'Valdivia', 'Vallenar', 'Valparaíso',
        'Victoria', 'Vichuquén', 'Vicuña', 'Vilcún', 'Villa Alegre', 'Villarrica',
        'Viña del Mar', 'Vitacura', 'Yerbas Buenas', 'Yumbel', 'Yungay', 'Zapallar',
    ];
}

function epl_lista_marcas_pala(): array {
    return [
        'Adidas', 'Babolat', 'Black Crown', 'Bullpadel', 'Dunlop', 'Head',
        'Kuikma', 'NOX', 'Puma', 'Prince', 'Royal Padel', 'Shark Padel',
        'Siux', 'Starvie', 'Varlion', 'Vibor-A', 'Volt Padel', 'Wilson', 'Otra',
    ];
}

/** Valores de formulario de perfil desde fila jugador o sesión. */
function epl_perfil_val_desde_jugador(?array $j): array {
    $campos = ['email', 'nombre', 'apellido', 'rut', 'telefono', 'fecha_nacimiento', 'sexo', 'comuna', 'profesion', 'nivel', 'lado', 'pala', 'talla', 'frecuencia_juego'];
    $val  = array_fill_keys($campos, '');
    if (!$j) {
        return $val;
    }
    foreach ($campos as $c) {
        if (isset($j[$c]) && $j[$c] !== null && $j[$c] !== '') {
            $val[$c] = is_string($j[$c]) ? $j[$c] : (string) $j[$c];
        }
    }
    return $val;
}

/** @return array{cc: string, num: string} */
function epl_parse_telefono(?string $telefono): array {
    $telefono = trim($telefono ?? '');
    if ($telefono !== '' && preg_match('/^(\+\d{1,3})\s*(.*)$/', $telefono, $m)) {
        return ['cc' => $m[1], 'num' => trim($m[2])];
    }
    return ['cc' => '+56', 'num' => $telefono];
}
