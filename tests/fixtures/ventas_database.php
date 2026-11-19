<?php
function fixtureEndpoint(string $ruta): PDO
{
    $pdo = new PDO('sqlite:' . $ruta, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('CREATE TABLE usuarios (id INTEGER PRIMARY KEY, nombre TEXT, rol TEXT, activo INTEGER, sucursal_id INTEGER, comision_productos DECIMAL)');
    $pdo->exec("INSERT INTO usuarios VALUES (1, 'Caja', 'admin', 1, 1, 0), (2, 'Recepción', 'recepcion', 1, 1, 15), (3, 'Barbero', 'barbero', 1, 1, 10)");
    $pdo->exec('CREATE TABLE usuarios_sucursales (usuario_id INTEGER, sucursal_id INTEGER)');
    $pdo->exec('CREATE TABLE inventario (id INTEGER PRIMARY KEY, producto TEXT, cantidad INTEGER, precio DECIMAL, stock_minimo INTEGER, sucursal_id INTEGER, unidad TEXT, categoria TEXT, descripcion TEXT)');
    $pdo->exec("INSERT INTO inventario VALUES (1, 'Cera', 10, 12.5, 2, 1, 'unidades', 'General', ''), (2, 'Champú', 1, 8, 2, 1, 'unidades', 'General', ''), (3, 'Otra sucursal', 5, 10, 2, 2, 'unidades', 'General', '')");
    $pdo->exec('CREATE TABLE ventas_productos (id INTEGER PRIMARY KEY, cita_id INTEGER, producto_id INTEGER, cantidad INTEGER, precio_unitario DECIMAL,
        sucursal_id INTEGER, usuario_id INTEGER, registrado_por INTEGER, vendedor_nombre TEXT, comision_porcentaje DECIMAL, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE citas (id INTEGER PRIMARY KEY, cliente_id INTEGER, servicio_id INTEGER, barbero_id INTEGER, sucursal_id INTEGER, estado TEXT, precio_final DECIMAL, propina DECIMAL DEFAULT 0)');
    $pdo->exec("INSERT INTO citas VALUES (42, 1, 1, 3, 1, 'confirmada', 15, 0)");
    $pdo->exec('CREATE TABLE clientes (id INTEGER PRIMARY KEY, nombre TEXT, puntos INTEGER)');
    $pdo->exec("INSERT INTO clientes VALUES (1, 'Cliente', 0)");
    $pdo->exec('CREATE TABLE servicios (id INTEGER PRIMARY KEY, nombre TEXT)');
    $pdo->exec("INSERT INTO servicios VALUES (1, 'Corte')");
    $pdo->exec('CREATE TABLE configuracion (clave TEXT, valor TEXT)');
    $pdo->exec("INSERT INTO configuracion VALUES ('puntos_por_corte', '100')");
    $pdo->exec('CREATE TABLE referidos (id INTEGER PRIMARY KEY, cita_id INTEGER, estado TEXT, referente_id INTEGER, puntos_otorgados INTEGER)');
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN comision_porcentaje DECIMAL DEFAULT 50');
    $pdo->exec('ALTER TABLE usuarios ADD COLUMN comision_fin_semana DECIMAL DEFAULT 50');
    $pdo->exec("ALTER TABLE clientes ADD COLUMN telefono TEXT DEFAULT '0999999999'");
    $pdo->exec('ALTER TABLE servicios ADD COLUMN precio DECIMAL DEFAULT 15');
    $pdo->exec("ALTER TABLE citas ADD COLUMN fecha_hora TEXT DEFAULT '2026-11-19 10:00:00'");
    $pdo->exec('ALTER TABLE citas ADD COLUMN fecha_completada DATETIME');
    $pdo->exec('ALTER TABLE citas ADD COLUMN notif_calificacion_enviada INTEGER DEFAULT 0');
    $pdo->exec('ALTER TABLE citas ADD COLUMN servicios_adicionales_txt TEXT');
    $pdo->exec('CREATE TABLE citas_servicios_adicionales (id INTEGER PRIMARY KEY, cita_id INTEGER, servicio_id INTEGER, nombre TEXT, precio DECIMAL)');
    $pdo->exec('CREATE TABLE sucursales (id INTEGER PRIMARY KEY, nombre TEXT, direccion TEXT)');
    $pdo->exec("INSERT INTO sucursales VALUES (1, 'Local 1', 'Dirección 1'), (2, 'Local 2', 'Dirección 2')");
    $pdo->exec('CREATE TABLE notificaciones_pwa (id INTEGER PRIMARY KEY, cliente_id INTEGER, usuario_id INTEGER, cita_id INTEGER, titulo TEXT, mensaje TEXT, url TEXT, leido INTEGER, fecha_creacion DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE push_subscriptions (id INTEGER PRIMARY KEY, usuario_id INTEGER, cliente_id INTEGER, endpoint TEXT)');
    $pdo->exec('ALTER TABLE citas ADD COLUMN metodo_pago TEXT');
    $pdo->exec('ALTER TABLE citas ADD COLUMN reasignacion_pendiente INTEGER DEFAULT 0');
    $pdo->exec('ALTER TABLE citas ADD COLUMN barbero_anterior_id INTEGER');
    $pdo->exec('ALTER TABLE citas ADD COLUMN motivo_reasignacion TEXT');
    $pdo->exec('ALTER TABLE ventas_productos ADD COLUMN metodo_pago TEXT');
    $pdo->exec('ALTER TABLE ventas_productos ADD COLUMN cobro_id INTEGER');
    $pdo->exec('CREATE TABLE cobros_caja (id INTEGER PRIMARY KEY, cita_id INTEGER UNIQUE, sucursal_id INTEGER, registrado_por INTEGER, metodo_pago TEXT, servicios DECIMAL, productos DECIMAL, propina DECIMAL, total DECIMAL, fecha_pago DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE citas_reasignaciones (id INTEGER PRIMARY KEY, cita_id INTEGER, barbero_anterior_id INTEGER, barbero_nuevo_id INTEGER, motivo TEXT, registrado_por INTEGER, fecha DATETIME DEFAULT CURRENT_TIMESTAMP)');
    $pdo->exec('CREATE TABLE horarios_barberos (barbero_id INTEGER, dia_semana INTEGER, hora_inicio TEXT, hora_fin TEXT, activo INTEGER, UNIQUE(barbero_id,dia_semana))');
    $pdo->exec('CREATE TABLE dias_bloqueados (id INTEGER PRIMARY KEY, barbero_id INTEGER, fecha TEXT, motivo TEXT, creado_por INTEGER)');
    $pdo->exec('CREATE TABLE bloqueos_horas (id INTEGER PRIMARY KEY, barbero_id INTEGER, fecha TEXT, hora_inicio TEXT, hora_fin TEXT, motivo TEXT, creado_por INTEGER)');
    $pdo->exec('CREATE TABLE servicios_barberos (servicio_id INTEGER, barbero_id INTEGER)');
    $pdo->exec('ALTER TABLE servicios ADD COLUMN duracion_minutos INTEGER DEFAULT 45');
    return $pdo;
}
