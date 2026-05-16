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
        'Lo Barnechea', 'Vitacura', 'Las Condes', 'Providencia',
        'La Reina', 'Ñuñoa', 'Peñalolén', 'Colina (Chicureo)', 'Otra',
    ];
}

function epl_lista_marcas_pala(): array {
    return [
        'Adidas', 'Babolat', 'Bullpadel', 'Drop Shot', 'Head',
        'Kuikma', 'Nox', 'Siux', 'StarVie', 'Varlion', 'Wilson', 'Otra',
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
