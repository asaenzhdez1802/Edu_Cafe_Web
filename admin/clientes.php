<?php
// admin/clientes.php — 15 · Clientes (10 por página; solo activar/desactivar)
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['accion'] ?? '') === 'estado') {
    verificar_csrf();
    $id = (int)($_POST['id_usuario'] ?? 0);
    $stmt = mysqli_prepare($conexion, "UPDATE usuarios SET estado = IF(estado = 'activo', 'inactivo', 'activo') WHERE id_usuario = ? AND rol = 'cliente'");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    $stmt = mysqli_prepare($conexion, "SELECT nombre, estado FROM usuarios WHERE id_usuario = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    if ($c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))) {
        aviso('success', $c['estado'] === 'activo'
            ? 'Se activó la cuenta de ' . $c['nombre'] . '.'
            : 'Se desactivó la cuenta de ' . $c['nombre'] . '. Ya no podrá iniciar sesión ni sumar puntos en caja.');
    }
    regresar();
}

[$mesDesde, $mesHasta] = rango('mes');
$sql = "SELECT
        (SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente') AS clientes,
        (SELECT COUNT(*) FROM ventas WHERE estado = 'completada' AND id_cliente IS NOT NULL AND fecha >= ? AND fecha < ?) AS compras_mes,
        (SELECT COALESCE(SUM(puntos_ganados), 0) FROM ventas WHERE estado = 'completada') AS puntos,
        (SELECT COUNT(*) FROM ventas WHERE estado = 'completada' AND id_recompensa IS NOT NULL) AS canjes";
$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "ss", $mesDesde, $mesHasta);
mysqli_stmt_execute($stmt);
$st = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

$filtro = in_array($_GET['filtro'] ?? '', ['activo', 'inactivo'], true) ? $_GET['filtro'] : '';
$q = trim((string)($_GET['q'] ?? ''));
$where = "u.rol = 'cliente'";
$tipos = '';
$params = [];
if ($filtro) { $where .= " AND u.estado = ?"; $tipos .= "s"; $params[] = $filtro; }
if ($q !== '') { $where .= " AND (u.nombre LIKE ? OR u.usuario LIKE ? OR u.celular LIKE ?)"; $tipos .= "sss"; array_push($params, "%$q%", "%$q%", '%' . preg_replace('/\D/', '', $q) . '%'); }
$stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM usuarios u WHERE $where");
if ($params) mysqli_stmt_bind_param($stmt, $tipos, ...$params);
mysqli_stmt_execute($stmt);
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
[$pagina, $paginas, $offset] = paginar($total, POR_PAGINA_CLIENTES);
$sql = "SELECT u.id_usuario, u.nombre, u.usuario, u.celular, u.puntos, u.estado,
               (SELECT COUNT(*) FROM ventas v WHERE v.id_cliente = u.id_usuario AND v.estado = 'completada') AS compras,
               (SELECT MAX(v.fecha) FROM ventas v WHERE v.id_cliente = u.id_usuario AND v.estado = 'completada') AS ultima
          FROM usuarios u WHERE $where ORDER BY u.nombre LIMIT " . POR_PAGINA_CLIENTES . " OFFSET $offset";
$stmt = mysqli_prepare($conexion, $sql);
if ($params) mysqli_stmt_bind_param($stmt, $tipos, ...$params);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$clientes = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

$sel = null;
$idSel = (int)($_GET['cliente'] ?? ($clientes[0]['id_usuario'] ?? 0));
if ($idSel) {
    $sql = "SELECT u.*, (SELECT COALESCE(SUM(total), 0) FROM ventas v WHERE v.id_cliente = u.id_usuario AND v.estado = 'completada') AS gastado
              FROM usuarios u WHERE u.id_usuario = ? AND u.rol = 'cliente'";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $idSel);
    mysqli_stmt_execute($stmt);
    $sel = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    if ($sel) {
        $sql = "SELECT folio, fecha, total, puntos_ganados, puntos_canjeados FROM ventas
                 WHERE id_cliente = ? AND estado = 'completada' ORDER BY fecha DESC LIMIT 3";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $idSel);
        mysqli_stmt_execute($stmt);
        $resultado = mysqli_stmt_get_result($stmt);
        $sel['compras'] = mysqli_fetch_all($resultado, MYSQLI_ASSOC);
        $sel['siguiente'] = siguiente_recompensa(recompensas_populares($conexion), (int)$sel['puntos']);
    }
}

function enlace_cli($cambios)
{
    $q = array_merge($_GET, $cambios);
    return '?' . http_build_query(array_filter($q, fn($v) => $v !== null && $v !== ''));
}

encabezado(['titulo' => 'Clientes', 'menu' => 'admin', 'activo' => 'clientes']);
portada_interna('Clientes y recompensas', 'Clientes', 'Consulta a los clientes registrados, sus compras y los puntos que han acumulado.',
    '<a class="btn btn-ec btn-naranja" href="' . e(url('admin/recompensas.php')) . '">Ver recompensas</a>');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="row g-3 mb-4">
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo"><i class="bi bi-people"></i></span><div><div class="stat-titulo">Clientes registrados</div><div class="stat-valor"><?= (int)$st['clientes'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo verde"><i class="bi bi-bag-check"></i></span><div><div class="stat-titulo">Compras este mes</div><div class="stat-valor"><?= (int)$st['compras_mes'] ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo amarillo"><i class="bi bi-star"></i></span><div><div class="stat-titulo">Puntos entregados</div><div class="stat-valor"><?= number_format((int)$st['puntos']) ?></div></div></div></div>
            <div class="col-6 col-lg-3"><div class="stat"><span class="icono-circulo"><i class="bi bi-gift"></i></span><div><div class="stat-titulo">Recompensas canjeadas</div><div class="stat-valor"><?= (int)$st['canjes'] ?></div></div></div></div>
        </div>

        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="tarjeta">
                    <form class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" method="get">
                        <h2 class="titulo-3 mb-0">Lista de clientes</h2>
                        <div class="d-flex flex-wrap gap-2">
                            <div class="buscador"><i class="bi bi-search"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Nombre, usuario o celular…"></div>
                            <select class="form-select" style="width:auto;border-radius:40px" name="filtro" onchange="this.form.submit()">
                                <option value="">Todos</option><option value="activo" <?= $filtro === 'activo' ? 'selected' : '' ?>>Activos</option><option value="inactivo" <?= $filtro === 'inactivo' ? 'selected' : '' ?>>Inactivos</option>
                            </select>
                        </div>
                    </form>
                    <div class="table-responsive">
                        <table class="tabla-ec tabla-compacta">
                            <thead><tr><th>Cliente</th><th>Celular</th><th>Compras</th><th>Puntos</th><th>Última compra</th><th>Estado</th><th>Detalle</th></tr></thead>
                            <tbody>
                            <?php foreach ($clientes as $c): $enlace = enlace_cli(['cliente' => $c['id_usuario']]); ?>
                                <tr class="<?= $sel && $sel['id_usuario'] == $c['id_usuario'] ? 'seleccionada' : '' ?>" data-href="<?= e($enlace) ?>">
                                    <td><div class="d-flex align-items-center gap-2"><span class="avatar"><?= e(iniciales($c['nombre'])) ?></span><div><div class="fw-semi" style="color:var(--cafe)"><?= e($c['nombre']) ?></div><div class="pequeno texto-sec">@<?= e($c['usuario']) ?></div></div></div></td>
                                    <td><?= e(celular_bonito($c['celular'])) ?></td>
                                    <td><?= (int)$c['compras'] ?></td>
                                    <td><?= etiqueta(puntos_texto($c['puntos']), 'naranja') ?></td>
                                    <td><?= fecha_corta($c['ultima']) ?></td>
                                    <td><?= $c['estado'] === 'activo' ? etiqueta('Activo', 'verde') : etiqueta('Inactivo', 'gris') ?></td>
                                    <td><a class="btn-icono" href="<?= e($enlace) ?>" title="Ver detalle"><i class="bi bi-eye"></i></a></td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$clientes): ?><tr><td colspan="7" class="tabla-vacia">No hay clientes con ese filtro.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <span class="pequeno texto-sec"><?= e(texto_mostrando($offset, count($clientes), $total, 'clientes')) ?></span>
                        <?= enlaces_paginacion($pagina, $paginas) ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="tarjeta">
                <?php if ($sel): ?>
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <span class="avatar" style="width:52px;height:52px;font-size:17px"><?= e(iniciales($sel['nombre'])) ?></span>
                        <div><h2 class="titulo-3 mb-0"><?= e($sel['nombre']) ?></h2><div class="pequeno texto-sec">@<?= e($sel['usuario']) ?> · <?= e(celular_bonito($sel['celular'])) ?></div></div>
                    </div>
                    <div class="tarjeta-puntos mb-3" style="padding:20px">
                        <span class="numero" style="font-size:44px"><?= (int)$sel['puntos'] ?></span> <span>puntos disponibles</span>
                        <p class="pequeno mb-0 mt-2"><?= $sel['siguiente'] ? 'Le faltan ' . ((int)$sel['siguiente']['puntos_requeridos'] - (int)$sel['puntos']) . ' puntos para: ' . e($sel['siguiente']['nombre']) . ' (' . (int)$sel['siguiente']['puntos_requeridos'] . ' pts)' : 'Le alcanza para cualquier recompensa activa.' ?></p>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6"><div class="dato d-block"><div class="etq">Total gastado</div><div class="val"><?= dinero($sel['gastado']) ?></div></div></div>
                        <div class="col-6"><div class="dato d-block"><div class="etq">Cliente desde</div><div class="val"><?= e(mes_anio($sel['fecha_registro'])) ?></div></div></div>
                    </div>
                    <h3 class="titulo-4">Últimas compras</h3>
                    <?php foreach ($sel['compras'] as $v): ?>
                        <div class="renglon"><span><span class="fw-semi"><?= e($v['folio']) ?></span><br><span class="pequeno texto-sec"><?= fecha_corta($v['fecha']) ?></span></span><span class="text-end"><?= dinero($v['total']) ?><br><?= etiqueta('+' . $v['puntos_ganados'], 'naranja') ?></span></div>
                    <?php endforeach; ?>
                    <?php if (!$sel['compras']): ?><p class="pequeno texto-sec">Sin compras todavía.</p><?php endif; ?>
                    <form method="post" class="mt-3">
                        <?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_usuario" value="<?= (int)$sel['id_usuario'] ?>">
                        <?php if ($sel['estado'] === 'activo'): ?>
                            <button class="btn btn-ec btn-peligro w-100" data-confirmar="¿Desactivar a <?= e($sel['nombre']) ?>? No podrá iniciar sesión ni sumar puntos en caja."><i class="bi bi-slash-circle"></i> Desactivar</button>
                        <?php else: ?>
                            <button class="btn btn-ec btn-cafe w-100"><i class="bi bi-check-lg"></i> Activar</button>
                        <?php endif; ?>
                    </form>
                <?php else: ?>
                    <p class="texto-sec text-center py-4 mb-0">Selecciona un cliente para ver su detalle.</p>
                <?php endif; ?>
                </div>
            </div>
        </div>
        <p class="nota mt-4 mb-0"><i class="bi bi-info-circle"></i> Los clientes crean su cuenta desde la página o desde la app. Los puntos solo cambian con las ventas y los canjes en caja.</p>
    </div>
</section>
<?php pie(['menu' => 'admin']); ?>
