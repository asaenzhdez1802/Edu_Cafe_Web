<?php
// admin/recompensas.php — 16 · Recompensas y regla de puntos (las define el admin)
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';

    if ($accion === 'regla') {
        $monto = (string)($_POST['monto'] ?? '');
        $puntos = (string)($_POST['puntos'] ?? '');
        if (!is_numeric($monto) || $monto <= 0 || $monto > 10000 || !ctype_digit($puntos) || (int)$puntos <= 0 || (int)$puntos > 1000) {
            aviso('danger', 'Escribe un monto mayor a 0 y un número entero de puntos mayor a 0.');
            regresar();
        }
        // Se cierra la regla vigente y se crea la nueva (queda el historial en regla_puntos)
        mysqli_begin_transaction($conexion);
        mysqli_query($conexion, "UPDATE regla_puntos SET fecha_fin = CURDATE() WHERE fecha_fin IS NULL");
        $puntosRegla = (int)$puntos;
        $stmt = mysqli_prepare($conexion, "INSERT INTO regla_puntos (monto, puntos, fecha_inicio, fecha_fin, id_usuario) VALUES (?, ?, CURDATE(), NULL, ?)");
        mysqli_stmt_bind_param($stmt, "dii", $monto, $puntosRegla, $u['id_usuario']);
        mysqli_stmt_execute($stmt);
        mysqli_commit($conexion);
        aviso('success', 'Nueva regla: ' . puntos_texto((int)$puntos) . ' por cada ' . dinero($monto) . '. Se aplica desde la siguiente venta.');
        regresar();
    }

    if ($accion === 'estado') {
        $idRecompensa = (int)($_POST['id_recompensa'] ?? 0);
        $stmt = mysqli_prepare($conexion, "UPDATE recompensas SET estado = IF(estado = 'activo', 'inactivo', 'activo') WHERE id_recompensa = ?");
        mysqli_stmt_bind_param($stmt, "i", $idRecompensa);
        mysqli_stmt_execute($stmt);
        aviso('success', 'Se cambió el estado de la recompensa.');
        regresar();
    }

    if ($accion === 'guardar') {
        $id = (int)($_POST['id_recompensa'] ?? 0);
        $d = [
            'nombre' => trim((string)($_POST['nombre'] ?? '')),
            'descripcion' => trim((string)($_POST['descripcion'] ?? '')),
            'puntos_requeridos' => (string)($_POST['puntos_requeridos'] ?? ''),
            'tipo' => ($_POST['tipo'] ?? '') === 'descuento' ? 'descuento' : 'producto',
            'id_producto' => (int)($_POST['id_producto'] ?? 0),
            'valor_descuento' => (string)($_POST['valor_descuento'] ?? ''),
            'estado' => isset($_POST['activa']) ? 'activo' : 'inactivo',
        ];
        $errores = [];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 80) $errores[] = 'Escribe el nombre de la recompensa (máximo 80 caracteres).';
        if (mb_strlen($d['descripcion']) > 200) $errores[] = 'La descripción admite hasta 200 caracteres.';
        if (!ctype_digit($d['puntos_requeridos']) || (int)$d['puntos_requeridos'] <= 0) $errores[] = 'Los puntos necesarios deben ser un número entero mayor a 0.';
        if ($d['tipo'] === 'producto') {
            $stmt = mysqli_prepare($conexion, "SELECT id_producto FROM productos WHERE id_producto = ? AND estado = 'activo'");
            mysqli_stmt_bind_param($stmt, "i", $d['id_producto']);
            mysqli_stmt_execute($stmt);
            if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) === 0) $errores[] = 'Elige el producto que se regala.';
        } elseif (!is_numeric($d['valor_descuento']) || $d['valor_descuento'] <= 0 || $d['valor_descuento'] > 10000) {
            $errores[] = 'Escribe el monto del descuento.';
        }
        if ($errores) {
            aviso('danger', implode(' ', $errores));
            recordar_formulario($d);
            redirigir('admin/recompensas.php' . ($id ? '?editar=' . $id : '') . '#formulario');
        }
        $descripcion = $d['descripcion'] !== '' ? $d['descripcion'] : null;
        $puntosReq = (int)$d['puntos_requeridos'];
        $idProducto = $d['tipo'] === 'producto' ? $d['id_producto'] : null;
        $valor = $d['tipo'] === 'descuento' ? round((float)$d['valor_descuento'], 2) : null;
        if ($id) {
            $sql = "UPDATE recompensas SET nombre = ?, descripcion = ?, puntos_requeridos = ?, tipo = ?, id_producto = ?,
                           valor_descuento = ?, estado = ? WHERE id_recompensa = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "ssisidsi", $d['nombre'], $descripcion, $puntosReq, $d['tipo'], $idProducto, $valor, $d['estado'], $id);
            mysqli_stmt_execute($stmt);
            aviso('success', 'Se guardaron los cambios de ' . $d['nombre'] . '.');
        } else {
            $sql = "INSERT INTO recompensas (nombre, descripcion, puntos_requeridos, tipo, id_producto, valor_descuento, estado)
                    VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "ssisids", $d['nombre'], $descripcion, $puntosReq, $d['tipo'], $idProducto, $valor, $d['estado']);
            mysqli_stmt_execute($stmt);
            aviso('success', 'Se agregó la recompensa ' . $d['nombre'] . '.');
        }
        redirigir('admin/recompensas.php');
    }
}

$regla = regla_vigente($conexion);
$sql = "SELECT r.*, p.nombre AS producto, p.imagen,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_recompensa = r.id_recompensa AND v.estado = 'completada') AS canjes
          FROM recompensas r LEFT JOIN productos p ON p.id_producto = r.id_producto
         ORDER BY r.estado, r.puntos_requeridos";
$resultado = mysqli_query($conexion, $sql);
$recompensas = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
$resultado = mysqli_query($conexion, "SELECT id_producto, nombre, precio_venta FROM productos WHERE estado = 'activo' ORDER BY nombre");
$productos = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

$editar = null;
if (!empty($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM recompensas WHERE id_recompensa = ?");
    mysqli_stmt_bind_param($stmt, "i", $idEditar);
    mysqli_stmt_execute($stmt);
    $editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
}
$f = $editar ?: ['id_recompensa' => 0, 'nombre' => '', 'descripcion' => '', 'puntos_requeridos' => '', 'tipo' => 'producto',
                 'id_producto' => 0, 'valor_descuento' => '', 'estado' => 'activo'];
foreach (['nombre', 'descripcion', 'puntos_requeridos', 'tipo', 'id_producto', 'valor_descuento', 'estado'] as $c) $f[$c] = valor_previo($c, $f[$c]);
$ejemplo = 85;

encabezado(['titulo' => 'Recompensas', 'menu' => 'admin', 'activo' => 'clientes']);
portada_interna('Clientes y recompensas', 'Recompensas', 'Define cuántos puntos gana el cliente por cada compra y qué puede canjear con ellos.',
    '<a class="btn btn-ec btn-naranja" href="' . e(url('admin/recompensas.php')) . '#formulario"><i class="bi bi-plus-lg"></i> Nueva recompensa</a>');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <form class="tarjeta mb-4" method="post">
            <?= csrf_campo() ?><input type="hidden" name="accion" value="regla">
            <div class="d-flex align-items-center gap-3 mb-3"><span class="icono-circulo chico"><i class="bi bi-star"></i></span><div><h2 class="titulo-3 mb-0">Regla para ganar puntos</h2><p class="pequeno texto-sec mb-0">Se aplica automáticamente a cada venta que el cajero liga a un cliente (en la web y en la app).</p></div></div>
            <div class="row g-3 align-items-end">
                <div class="col-md-4"><label class="form-label" for="monto">Por cada compra de</label><input class="form-control" id="monto" name="monto" type="number" min="1" step="0.01" value="<?= e($regla['monto']) ?>"></div>
                <div class="col-md-4"><label class="form-label" for="puntos">El cliente gana</label><input class="form-control" id="puntos" name="puntos" type="number" min="1" step="1" value="<?= e($regla['puntos']) ?>"></div>
                <div class="col-md-4"><button class="btn btn-ec btn-cafe w-100" type="submit">Guardar regla</button></div>
            </div>
            <p class="nota mt-3 mb-0"><i class="bi bi-info-circle"></i> Ejemplo: una compra de <?= dinero($ejemplo) ?> le otorga al cliente <?= puntos_texto(puntos_por_compra($ejemplo, $regla)) ?>.</p>
        </form>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="tarjeta">
                    <h2 class="titulo-3 mb-1">Catálogo de recompensas</h2>
                    <p class="pequeno texto-sec">Lo que el cliente puede canjear con sus puntos. Las activas se ven en la página, en el panel del cliente, en la app y en el punto de venta.</p>
                    <div class="row g-3">
                        <?php foreach ($recompensas as $r): ?>
                        <div class="col-md-6">
                            <div class="tarjeta-durazno h-100 d-flex flex-column gap-2 <?= $editar && $editar['id_recompensa'] == $r['id_recompensa'] ? 'border border-2' : '' ?>" style="<?= $r['estado'] === 'inactivo' ? 'opacity:.65' : '' ?>">
                                <div class="d-flex justify-content-between gap-2">
                                    <div class="d-flex gap-2 align-items-center"><span class="icono-circulo chico"><i class="bi bi-gift"></i></span><div class="fw-semi" style="color:var(--cafe)"><?= e($r['nombre']) ?></div></div>
                                    <?= etiqueta(puntos_texto($r['puntos_requeridos']), 'naranja') ?>
                                </div>
                                <div class="pequeno texto-sec"><?= e($r['descripcion']) ?></div>
                                <div class="pequeno"><?= $r['tipo'] === 'producto' ? '<i class="bi bi-cup-hot text-caramelo"></i> Producto gratis: ' . e($r['producto'] ?: 'sin producto') : '<i class="bi bi-percent text-caramelo"></i> Descuento de ' . dinero($r['valor_descuento']) ?></div>
                                <div class="d-flex justify-content-between align-items-center mt-auto">
                                    <span><?= $r['estado'] === 'activo' ? etiqueta('Activa', 'verde') : etiqueta('Inactiva', 'gris') ?> <span class="pequeno texto-sec"><?= (int)$r['canjes'] ?> canjes</span></span>
                                    <span class="text-nowrap">
                                        <a class="btn-icono" href="?editar=<?= $r['id_recompensa'] ?>#formulario" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_recompensa" value="<?= $r['id_recompensa'] ?>">
                                            <button class="btn-icono <?= $r['estado'] === 'activo' ? 'rojo' : 'verde' ?>" title="<?= $r['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>"><i class="bi <?= $r['estado'] === 'activo' ? 'bi-slash-circle' : 'bi-check-lg' ?>"></i></button>
                                        </form>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php if (!$recompensas): ?><div class="col-12 tabla-vacia">Aún no hay recompensas.</div><?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4" id="formulario">
                <form class="tarjeta" method="post">
                    <?= csrf_campo() ?><input type="hidden" name="accion" value="guardar"><input type="hidden" name="id_recompensa" value="<?= (int)$f['id_recompensa'] ?>">
                    <div class="d-flex align-items-center gap-3 mb-2"><span class="icono-circulo chico"><i class="bi bi-gift"></i></span><h2 class="titulo-3 mb-0"><?= $editar ? 'Editar recompensa' : 'Agregar recompensa' ?></h2></div>
                    <p class="pequeno texto-sec">Las recompensas activas se muestran al cliente en su panel.</p>
                    <div class="mb-3"><label class="form-label" for="r-nombre">Nombre de la recompensa</label><input class="form-control" id="r-nombre" name="nombre" maxlength="80" value="<?= e($f['nombre']) ?>" placeholder="Ej. Café americano gratis" required></div>
                    <div class="mb-3"><label class="form-label" for="r-desc">Descripción</label><textarea class="form-control" id="r-desc" name="descripcion" rows="2" maxlength="200" placeholder="Ej. Un café americano de 12 oz."><?= e($f['descripcion']) ?></textarea></div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><label class="form-label" for="r-puntos">Puntos necesarios</label><input class="form-control" id="r-puntos" name="puntos_requeridos" type="number" min="1" step="1" value="<?= e($f['puntos_requeridos']) ?>" placeholder="Ej. 100" required></div>
                        <div class="col-6"><label class="form-label" for="r-tipo">Tipo</label>
                            <select class="form-select" id="r-tipo" name="tipo"><option value="producto" <?= $f['tipo'] === 'producto' ? 'selected' : '' ?>>Producto gratis</option><option value="descuento" <?= $f['tipo'] === 'descuento' ? 'selected' : '' ?>>Descuento</option></select>
                        </div>
                    </div>
                    <div class="mb-3" id="campoProducto"><label class="form-label" for="r-producto">Producto que se regala</label>
                        <select class="form-select" id="r-producto" name="id_producto">
                            <option value="0">Elige un producto</option>
                            <?php foreach ($productos as $p): ?><option value="<?= $p['id_producto'] ?>" <?= $f['id_producto'] == $p['id_producto'] ? 'selected' : '' ?>><?= e($p['nombre']) ?> (<?= dinero($p['precio_venta']) ?>)</option><?php endforeach; ?>
                        </select>
                        <div class="form-text">En caja se agrega como renglón y su precio se descuenta.</div>
                    </div>
                    <div class="mb-3" id="campoMonto"><label class="form-label" for="r-monto">Monto del descuento</label><input class="form-control" id="r-monto" name="valor_descuento" type="number" min="1" step="0.01" value="<?= e($f['valor_descuento']) ?>" placeholder="$0.00"></div>
                    <div class="interruptor mb-4">
                        <div><div class="fw-semi" style="color:var(--cafe)">Recompensa activa</div><div class="pequeno texto-sec">Los clientes podrán canjearla en caja.</div></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="activa" value="1" <?= $f['estado'] === 'activo' ? 'checked' : '' ?> aria-label="Recompensa activa"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-ec btn-cafe flex-grow-1" type="submit"><?= $editar ? 'Editar recompensa' : 'Guardar recompensa' ?></button>
                        <a class="btn btn-ec btn-contorno" href="<?= e(url('admin/recompensas.php')) ?>">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <p class="nota mt-4 mb-0"><i class="bi bi-info-circle"></i> Cuando el cliente canjea una recompensa, el cajero la aplica en el punto de venta y se descuentan sus puntos. Solo se permite un canje por compra.</p>
    </div>
</section>
<script>
(() => {
    const tipo = document.getElementById('r-tipo');
    const pintar = () => {
        document.getElementById('campoProducto').classList.toggle('d-none', tipo.value !== 'producto');
        document.getElementById('campoMonto').classList.toggle('d-none', tipo.value !== 'descuento');
    };
    tipo.addEventListener('change', pintar); pintar();
})();
</script>
<?php pie(['menu' => 'admin']); ?>
