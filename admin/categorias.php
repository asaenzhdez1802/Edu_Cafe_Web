<?php
// admin/categorias.php — 12 · Categorías (20 registros; descripción a 30 caracteres)
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');
$idComida = id_categoria_comida($conexion);

function productos_activos($conexion, $id)
{
    $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM productos WHERE id_categoria = ? AND estado = 'activo'");
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);
    return (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $accion = $_POST['accion'] ?? '';
    $id = (int)($_POST['id_categoria'] ?? 0);

    if ($accion === 'estado') {
        $stmt = mysqli_prepare($conexion, "SELECT nombre, estado FROM categorias WHERE id_categoria = ?");
        mysqli_stmt_bind_param($stmt, "i", $id);
        mysqli_stmt_execute($stmt);
        $c = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        if ($c && $c['estado'] === 'activo') {
            if ($id === $idComida) {
                aviso('danger', 'La categoría Comida del día la usa el sistema y no se puede desactivar.');
            } elseif (($n = productos_activos($conexion, $id)) > 0) {
                aviso('danger', 'No se puede desactivar ' . $c['nombre'] . ': todavía tiene ' . $n . ' productos activos.');
            } else {
                $stmt = mysqli_prepare($conexion, "UPDATE categorias SET estado = 'inactivo' WHERE id_categoria = ?");
                mysqli_stmt_bind_param($stmt, "i", $id);
                mysqli_stmt_execute($stmt);
                aviso('success', 'Se desactivó la categoría ' . $c['nombre'] . '.');
            }
        } elseif ($c) {
            $stmt = mysqli_prepare($conexion, "UPDATE categorias SET estado = 'activo' WHERE id_categoria = ?");
            mysqli_stmt_bind_param($stmt, "i", $id);
            mysqli_stmt_execute($stmt);
            aviso('success', 'Se activó la categoría ' . $c['nombre'] . '.');
        }
        regresar();
    }

    if ($accion === 'guardar') {
        $d = [
            'nombre' => trim((string)($_POST['nombre'] ?? '')),
            'descripcion' => trim((string)($_POST['descripcion'] ?? '')),
            'estado' => ($_POST['estado'] ?? 'activo') === 'inactivo' ? 'inactivo' : 'activo',
            'mostrar_filtro' => isset($_POST['mostrar_filtro']) ? 1 : 0,
        ];
        $errores = [];
        if ($d['nombre'] === '' || mb_strlen($d['nombre']) > 60) $errores[] = 'Escribe el nombre de la categoría (máximo 60 caracteres).';
        if (mb_strlen($d['descripcion']) > 200) $errores[] = 'La descripción admite hasta 200 caracteres.';
        $stmt = mysqli_prepare($conexion, "SELECT id_categoria FROM categorias WHERE nombre = ? AND id_categoria <> ?");
        mysqli_stmt_bind_param($stmt, "si", $d['nombre'], $id);
        mysqli_stmt_execute($stmt);
        if (mysqli_num_rows(mysqli_stmt_get_result($stmt)) > 0) $errores[] = 'Ya existe una categoría con ese nombre.';
        if ($id && $id === $idComida) {
            if ($d['nombre'] !== CATEGORIA_COMIDA_DIA) $errores[] = 'El nombre de Comida del día no se puede cambiar porque lo usa el sistema.';
            if ($d['estado'] === 'inactivo') $errores[] = 'Comida del día no se puede desactivar.';
        }
        if ($id && $d['estado'] === 'inactivo' && $id !== $idComida && ($n = productos_activos($conexion, $id)) > 0) {
            $errores[] = 'No se puede desactivar: todavía tiene ' . $n . ' productos activos.';
        }
        if ($errores) {
            aviso('danger', implode(' ', $errores));
            recordar_formulario($d);
            redirigir('admin/categorias.php' . ($id ? '?editar=' . $id : ''));
        }
        $descripcion = $d['descripcion'] !== '' ? $d['descripcion'] : null;
        if ($id) {
            $stmt = mysqli_prepare($conexion, "UPDATE categorias SET nombre = ?, descripcion = ?, estado = ?, mostrar_filtro = ? WHERE id_categoria = ?");
            mysqli_stmt_bind_param($stmt, "sssii", $d['nombre'], $descripcion, $d['estado'], $d['mostrar_filtro'], $id);
            mysqli_stmt_execute($stmt);
            aviso('success', 'Se guardaron los cambios de ' . $d['nombre'] . '.');
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO categorias (nombre, descripcion, estado, mostrar_filtro) VALUES (?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "sssi", $d['nombre'], $descripcion, $d['estado'], $d['mostrar_filtro']);
            mysqli_stmt_execute($stmt);
            aviso('success', 'Se agregó la categoría ' . $d['nombre'] . '.');
        }
        redirigir('admin/categorias.php');
    }
}

$q = trim((string)($_GET['q'] ?? ''));
$where = $q !== '' ? "WHERE c.nombre LIKE ?" : '';
$buscar = "%$q%";
$stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS total FROM categorias c $where");
if ($q !== '') mysqli_stmt_bind_param($stmt, "s", $buscar);
mysqli_stmt_execute($stmt);
$total = (int)mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['total'];
[$pagina, $paginas, $offset] = paginar($total, POR_PAGINA_CATEGORIAS);
$sql = "SELECT c.*, (SELECT COUNT(*) FROM productos p WHERE p.id_categoria = c.id_categoria) AS productos
          FROM categorias c $where ORDER BY c.id_categoria LIMIT " . POR_PAGINA_CATEGORIAS . " OFFSET $offset";
$stmt = mysqli_prepare($conexion, $sql);
if ($q !== '') mysqli_stmt_bind_param($stmt, "s", $buscar);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);
$categorias = mysqli_fetch_all($resultado, MYSQLI_ASSOC);

$editar = null;
if (!empty($_GET['editar'])) {
    $idEditar = (int)$_GET['editar'];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM categorias WHERE id_categoria = ?");
    mysqli_stmt_bind_param($stmt, "i", $idEditar);
    mysqli_stmt_execute($stmt);
    $editar = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
}
$f = $editar ?: ['id_categoria' => 0, 'nombre' => '', 'descripcion' => '', 'estado' => 'activo', 'mostrar_filtro' => 1];
foreach (['nombre', 'descripcion', 'estado', 'mostrar_filtro'] as $c) $f[$c] = valor_previo($c, $f[$c]);

encabezado(['titulo' => 'Categorías', 'menu' => 'admin', 'activo' => 'catalogo']);
portada_interna('Catálogo', 'Categorías', 'Organiza los productos en categorías. Las categorías activas se pueden mostrar como filtro en el menú.',
    '<a class="btn btn-ec btn-naranja" href="' . e(url('admin/categorias.php')) . '#formulario"><i class="bi bi-plus-lg"></i> Nueva categoría</a>');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <div class="tarjeta">
                    <form class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3" method="get">
                        <h2 class="titulo-3 mb-0">Lista de categorías</h2>
                        <div class="buscador"><i class="bi bi-search"></i><input class="form-control" type="search" name="q" value="<?= e($q) ?>" placeholder="Buscar categoría…"></div>
                    </form>
                    <div class="table-responsive">
                        <table class="tabla-ec tabla-compacta">
                            <thead><tr><th>ID</th><th>Categoría</th><th>Descripción</th><th>Productos</th><th>Filtro</th><th>Estado</th><th>Acciones</th></tr></thead>
                            <tbody>
                            <?php foreach ($categorias as $c): $q2 = $_GET; $q2['editar'] = $c['id_categoria']; ?>
                                <tr class="<?= $editar && $editar['id_categoria'] == $c['id_categoria'] ? 'seleccionada' : '' ?>">
                                    <td class="pequeno">CAT<?= str_pad($c['id_categoria'], 2, '0', STR_PAD_LEFT) ?></td>
                                    <td class="fw-semi"><?= e($c['nombre']) ?></td>
                                    <td class="pequeno texto-sec" title="<?= e($c['descripcion']) ?>"><?= e(texto_corto($c['descripcion'], 30)) ?></td>
                                    <td><?= (int)$c['productos'] ?></td>
                                    <td><?= $c['mostrar_filtro'] ? '<i class="bi bi-check-circle text-ok"></i>' : '<i class="bi bi-dash-circle texto-sec"></i>' ?></td>
                                    <td><?= $c['estado'] === 'activo' ? etiqueta('Activo', 'verde') : etiqueta('Inactivo', 'gris') ?></td>
                                    <td class="text-nowrap">
                                        <a class="btn-icono" href="?<?= e(http_build_query($q2)) ?>#formulario" title="Editar"><i class="bi bi-pencil"></i></a>
                                        <form method="post" class="d-inline"><?= csrf_campo() ?><input type="hidden" name="accion" value="estado"><input type="hidden" name="id_categoria" value="<?= $c['id_categoria'] ?>">
                                            <button class="btn-icono <?= $c['estado'] === 'activo' ? 'rojo' : 'verde' ?>" title="<?= $c['estado'] === 'activo' ? 'Desactivar' : 'Activar' ?>" data-confirmar="<?= $c['estado'] === 'activo' ? '¿Desactivar ' . e($c['nombre']) . '?' : '¿Activar ' . e($c['nombre']) . '?' ?>"><i class="bi <?= $c['estado'] === 'activo' ? 'bi-slash-circle' : 'bi-check-lg' ?>"></i></button>
                                        </form>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            <?php if (!$categorias): ?><tr><td colspan="7" class="tabla-vacia">No hay categorías con ese nombre.</td></tr><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mt-3">
                        <span class="pequeno texto-sec"><?= e(texto_mostrando($offset, count($categorias), $total, 'categorías')) ?></span>
                        <?= enlaces_paginacion($pagina, $paginas) ?>
                    </div>
                </div>
            </div>
            <div class="col-lg-4" id="formulario">
                <form class="tarjeta" method="post">
                    <?= csrf_campo() ?>
                    <input type="hidden" name="accion" value="guardar">
                    <input type="hidden" name="id_categoria" value="<?= (int)$f['id_categoria'] ?>">
                    <div class="d-flex align-items-center gap-3 mb-2"><span class="icono-circulo chico"><i class="bi bi-tags"></i></span><h2 class="titulo-3 mb-0"><?= $editar ? 'Editar categoría' : 'Información de la categoría' ?></h2></div>
                    <p class="pequeno texto-sec">Crea una categoría nueva o edita una existente.</p>
                    <div class="mb-3"><label class="form-label" for="nombre">Nombre de la categoría</label><input class="form-control" id="nombre" name="nombre" maxlength="60" value="<?= e($f['nombre']) ?>" placeholder="Ej. Bebidas calientes" required></div>
                    <div class="mb-3"><label class="form-label" for="descripcion">Descripción</label><textarea class="form-control" id="descripcion" name="descripcion" rows="3" maxlength="200" placeholder="Ej. Café, capuchino, chocolate y otras bebidas"><?= e($f['descripcion']) ?></textarea></div>
                    <div class="mb-3"><label class="form-label" for="estado">Estado</label>
                        <select class="form-select" id="estado" name="estado"><option value="activo" <?= $f['estado'] === 'activo' ? 'selected' : '' ?>>Activo</option><option value="inactivo" <?= $f['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option></select>
                    </div>
                    <div class="interruptor mb-4">
                        <div><div class="fw-semi" style="color:var(--cafe)">Mostrar como filtro en el menú</div><div class="pequeno texto-sec">Los clientes podrán filtrar el catálogo por esta categoría.</div></div>
                        <div class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" name="mostrar_filtro" value="1" <?= $f['mostrar_filtro'] ? 'checked' : '' ?> aria-label="Mostrar como filtro"></div>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-ec btn-cafe flex-grow-1"><?= $editar ? 'Editar categoría' : 'Guardar categoría' ?></button>
                        <a class="btn btn-ec btn-contorno" href="<?= e(url('admin/categorias.php')) ?>">Cancelar</a>
                    </div>
                </form>
            </div>
        </div>
        <p class="nota mt-4 mb-0"><i class="bi bi-info-circle"></i> No se puede desactivar una categoría que todavía tiene productos activos. "Comida del día" la usa el sistema y no se puede renombrar.</p>
    </div>
</section>
<?php pie(['menu' => 'admin']); ?>
