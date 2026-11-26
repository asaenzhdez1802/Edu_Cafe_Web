<?php
// admin/sitio.php — 18 · Información del sitio (lo que se muestra en la parte pública y en el pie de página)
session_start();
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../includes/plantilla.php';

$u = requerir_rol('admin');

$campos = [
    'nombre_negocio' => 80, 'eslogan' => 150, 'nosotros' => 3000, 'historia' => 5000, 'mision' => 2000, 'vision' => 2000,
    'horario' => 150, 'direccion' => 200, 'telefono' => 20, 'correo' => 100, 'facebook' => 150, 'instagram' => 150,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();
    $d = [];
    $errores = [];
    foreach ($campos as $c => $max) {
        $d[$c] = trim(str_replace("\r\n", "\n", (string)($_POST[$c] ?? '')));
        if (mb_strlen($d[$c]) > $max) $errores[] = 'El campo ' . str_replace('_', ' ', $c) . ' admite hasta ' . $max . ' caracteres.';
    }
    if ($d['nombre_negocio'] === '') $errores[] = 'Escribe el nombre de la cafetería.';
    if ($d['correo'] !== '' && !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) $errores[] = 'El correo no es válido.';
    if (substr_count($d['eslogan'], '*') % 2 !== 0) $errores[] = 'En el eslogan, cada palabra en dorado debe ir entre dos asteriscos (*así*).';

    $foto = null;
    if (!$errores) {
        try {
            $foto = subir_imagen($_FILES['foto_historia'] ?? [], __DIR__ . '/../img/sitio', 'img/sitio', 'cafeteria');
        } catch (Exception $e) {
            $errores[] = $e->getMessage();
        }
    }
    if ($errores) {
        aviso('danger', implode(' ', $errores));
        recordar_formulario($d);
        redirigir('admin/sitio.php');
    }

    $sets = implode(', ', array_map(fn($c) => "$c = ?", array_keys($campos)));
    $params = array_map(fn($v) => $v === '' ? null : $v, array_values($d));
    $params[0] = $d['nombre_negocio'];
    if ($foto) { $sets .= ', foto_historia = ?'; $params[] = $foto; }
    $sets .= ', id_usuario = ?, fecha_actualizacion = ?';
    $params[] = $u['id_usuario'];
    $params[] = date('Y-m-d H:i:s');
    $stmt = mysqli_prepare($conexion, "UPDATE informacion_sitio SET $sets WHERE id_info = 1");
    $tipos = str_repeat("s", count($params) - 2) . "is"; // textos, luego id_usuario (i) y la fecha (s)
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    aviso('success', 'Información guardada. Los cambios ya se ven en la página pública y en la app.');
    redirigir('admin/sitio.php');
}

$info = info_sitio($conexion);
$v = [];
foreach ($campos as $c => $max) $v[$c] = valor_previo($c, (string)$info[$c]);
$fotoActual = img_sitio($info['foto_historia'], 'assets/img/cafeteria.jpg');

function campo_texto($nombre, $etiqueta, $valor, $max, $placeholder = '', $tipo = 'text')
{
    return '<div><label class="form-label" for="s-' . $nombre . '">' . e($etiqueta) . '</label>'
         . '<input class="form-control" id="s-' . $nombre . '" name="' . $nombre . '" type="' . $tipo . '" maxlength="' . $max . '" value="' . e($valor) . '" placeholder="' . e($placeholder) . '"></div>';
}
function campo_area($nombre, $etiqueta, $valor, $max, $filas = 4, $ayuda = '')
{
    return '<div><label class="form-label" for="s-' . $nombre . '">' . e($etiqueta) . '</label>'
         . '<textarea class="form-control" id="s-' . $nombre . '" name="' . $nombre . '" rows="' . $filas . '" maxlength="' . $max . '">' . e($valor) . '</textarea>'
         . ($ayuda ? '<div class="form-text">' . e($ayuda) . '</div>' : '') . '</div>';
}
function grupo($icono, $titulo)
{
    return '<div class="d-flex align-items-center gap-2 mt-2"><span class="icono-circulo chico" style="width:32px;height:32px;font-size:15px"><i class="bi ' . $icono . '"></i></span><h3 class="titulo-4 mb-0">' . e($titulo) . '</h3></div>';
}

encabezado(['titulo' => 'Información del sitio', 'menu' => 'admin', 'activo' => 'configuracion']);
portada_interna('Configuración', 'Información del sitio', 'Edita lo que se muestra en la página pública: identidad, nosotros, nuestra historia, misión, visión, horario y contacto.');
?>
<section class="seccion-corta bg-crema">
    <div class="container-ec">
        <div class="row g-4 align-items-start">
            <div class="col-lg-8">
                <form class="tarjeta d-grid gap-3" method="post" enctype="multipart/form-data">
                    <?= csrf_campo() ?>
                    <?= grupo('bi-heart', 'Identidad') ?>
                    <div class="row g-3">
                        <div class="col-md-6"><?= campo_texto('nombre_negocio', 'Nombre de la cafetería', $v['nombre_negocio'], 80) ?></div>
                        <div class="col-md-6"><?= campo_texto('eslogan', 'Eslogan', $v['eslogan'], 150, 'Una pausa con *más sabor* y más color.') ?></div>
                    </div>
                    <p class="nota mb-0"><i class="bi bi-stars"></i> En el eslogan, escribe entre *asteriscos* las palabras que quieres en dorado. Es el título grande del Inicio.</p>

                    <?= grupo('bi-bullseye', 'Misión y visión') ?>
                    <div class="row g-3">
                        <div class="col-md-6"><?= campo_area('mision', 'Misión', $v['mision'], 2000) ?></div>
                        <div class="col-md-6"><?= campo_area('vision', 'Visión', $v['vision'], 2000) ?></div>
                    </div>

                    <?= grupo('bi-globe', 'Nosotros') ?>
                    <?= campo_area('nosotros', 'Texto de “Nosotros” (se muestra en Inicio)', $v['nosotros'], 3000, 3) ?>

                    <?= grupo('bi-book', 'Nuestra historia') ?>
                    <?= campo_area('historia', 'Texto de “Nuestra historia”', $v['historia'], 5000, 6, 'Deja una línea en blanco para separar párrafos.') ?>
                    <div>
                        <label class="form-label">Foto de la cafetería</label>
                        <label class="caja-subir">
                            <img id="vistaFoto" src="<?= e($fotoActual) ?>" alt="" style="width:200px;height:130px">
                            <span class="pequeno texto-sec"><i class="bi bi-upload text-caramelo fs-5"></i><br>Arrastra la foto aquí o elígela desde tu equipo<br>JPG, PNG o WEBP · máximo 2 MB · de preferencia horizontal<br><span class="btn btn-ec btn-contorno btn-sm mt-2">Elegir foto</span></span>
                            <input type="file" name="foto_historia" accept="image/jpeg,image/png,image/webp" class="d-none" data-vista-previa="#vistaFoto">
                        </label>
                        <div class="form-text">Se muestra junto a “Nuestra historia” en la página Nosotros y en el Inicio. Al subir una nueva, reemplaza a la anterior.</div>
                    </div>

                    <?= grupo('bi-clock', 'Horario y contacto') ?>
                    <div class="row g-3">
                        <div class="col-md-6"><?= campo_texto('horario', 'Horario', $v['horario'], 150, 'Lunes a viernes de 7:00 a 18:00 h') ?></div>
                        <div class="col-md-6"><?= campo_texto('telefono', 'Teléfono', $v['telefono'], 20) ?></div>
                        <div class="col-md-6"><?= campo_texto('correo', 'Correo', $v['correo'], 100, '', 'email') ?></div>
                        <div class="col-md-6"><?= campo_texto('direccion', 'Ubicación', $v['direccion'], 200) ?></div>
                        <div class="col-md-6"><?= campo_texto('facebook', 'Facebook', $v['facebook'], 150, 'facebook.com/tu.pagina') ?></div>
                        <div class="col-md-6"><?= campo_texto('instagram', 'Instagram', $v['instagram'], 150, '@tu.cuenta') ?></div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end mt-2">
                        <a class="btn btn-ec btn-contorno" href="<?= e(url('admin/sitio.php')) ?>">Descartar cambios</a>
                        <button class="btn btn-ec btn-cafe" type="submit">Guardar cambios</button>
                    </div>
                </form>
            </div>
            <div class="col-lg-4">
                <div class="tarjeta" style="position:sticky;top:100px">
                    <div class="pequeno fw-semi mb-3" style="color:var(--cafe)"><i class="bi bi-eye text-caramelo"></i> Vista previa en la página pública</div>
                    <div class="rounded-4 p-3 mb-3" style="background:var(--oscuro);color:var(--claro)">
                        <div class="titulo-3 text-white mb-1" id="pvNombre"><?= e($v['nombre_negocio']) ?></div>
                        <div class="pequeno" id="pvEslogan"><?= eslogan_html($v['eslogan']) ?></div>
                    </div>
                    <div class="tarjeta-durazno mb-3">
                        <img class="foto-redonda w-100 mb-2" style="height:120px;border-radius:14px" src="<?= e($fotoActual) ?>" alt="" id="pvFoto">
                        <div class="fw-semi pequeno" style="color:var(--cafe)"><i class="bi bi-book text-caramelo"></i> Nuestra historia</div>
                        <div class="pequeno texto-sec"><?= e(texto_corto($v['historia'], 120)) ?></div>
                    </div>
                    <div class="tarjeta-durazno mb-3"><div class="fw-semi pequeno" style="color:var(--cafe)"><i class="bi bi-bullseye text-caramelo"></i> Misión</div><div class="pequeno texto-sec"><?= e(texto_corto($v['mision'], 140)) ?></div></div>
                    <div class="tarjeta-durazno mb-3"><div class="fw-semi pequeno" style="color:var(--cafe)"><i class="bi bi-eye text-caramelo"></i> Visión</div><div class="pequeno texto-sec"><?= e(texto_corto($v['vision'], 140)) ?></div></div>
                    <ul class="list-unstyled pequeno d-grid gap-1 mb-3">
                        <li><i class="bi bi-clock text-caramelo"></i> <?= e($v['horario']) ?></li>
                        <li><i class="bi bi-telephone text-caramelo"></i> <?= e($v['telefono']) ?></li>
                        <li><i class="bi bi-envelope text-caramelo"></i> <?= e($v['correo']) ?></li>
                        <li><i class="bi bi-geo-alt text-caramelo"></i> <?= e($v['direccion']) ?></li>
                    </ul>
                    <div class="pequeno texto-sec pt-2" style="border-top:1px solid var(--borde)">Última actualización: <?= e(fecha_corta($info['fecha_actualizacion'] ?? null)) ?> · <?= e(hora($info['fecha_actualizacion'] ?? null)) ?><?= $info['editado_por'] ? ' · ' . e($info['editado_por']) : '' ?></div>
                </div>
            </div>
        </div>
    </div>
</section>
<script>
(() => {
    const esc = (t) => t.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const eslogan = document.getElementById('s-eslogan');
    const nombre = document.getElementById('s-nombre_negocio');
    eslogan.addEventListener('input', () => {
        document.getElementById('pvEslogan').innerHTML = esc(eslogan.value).replace(/\*(.+?)\*/g, '<span class="text-dorado">$1</span>');
    });
    nombre.addEventListener('input', () => { document.getElementById('pvNombre').textContent = nombre.value; });
    document.querySelector('input[name=foto_historia]').addEventListener('change', (e) => {
        if (e.target.files[0]) document.getElementById('pvFoto').src = URL.createObjectURL(e.target.files[0]);
    });
})();
</script>
<?php pie(['menu' => 'admin']); ?>
