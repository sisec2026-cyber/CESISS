<?php
//Matriz de permisos por rol
$permisos = [
    'Superadmin' => [
        'ver_dispositivos' => true,
        'ver_index' => true,
        'agregar_dispositivos' => true,
        'editar_dispositivos' => true,
        'eliminar_dispositivos' => true,
        'ver_usuarios' => true,
        'agregar_usuarios' => true
    ],
    'Administrador' => [
        'ver_dispositivos' => true,
        'ver_index' => true,
        'agregar_dispositivos' => true,
        'editar_dispositivos' => true,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => true,
        'agregar_usuarios' => true
    ],
    'Capturista' => [
        'ver_dispositivos' => true,
        'ver_index' => true,
        'agregar_dispositivos' => true,
        'editar_dispositivos' => false,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => false,
        'agregar_usuarios' => false
    ],
    'Técnico' => [
        'ver_dispositivos' => true,
        'agregar_dispositivos' => true,
        'editar_dispositivos' => false,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => false,
        'agregar_usuarios' => false
    ],
    'Distrital' => [
        'ver_dispositivos' => true,
        'agregar_dispositivos' => true,
        'editar_dispositivos' => true,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => false,
        'agregar_usuarios' => false
    ],
    'Prevencion' => [
        'ver_dispositivos' => true,
        'agregar_dispositivos' => false,
        'editar_dispositivos' => false,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => false,
        'agregar_usuarios' => false
    ],
    'Monitorista' => [
        'ver_dispositivos' => true,
        'agregar_dispositivos' => false,
        'editar_dispositivos' => false,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => false,
        'agregar_usuarios' => false
    ],
    'Mantenimientos' => [
        'ver_dispositivos' => true,
        'ver_index' => true,
        'agregar_dispositivos' => false,
        'editar_dispositivos' => true,
        'eliminar_dispositivos' => false,
        'ver_usuarios' => false,
        'agregar_usuarios' => false
    ]
];

//Función para verificar permiso
function puede($accion) {
    global $permisos, $rol;
    return !empty($permisos[$rol][$accion]);
}