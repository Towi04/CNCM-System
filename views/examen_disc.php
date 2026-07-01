<?php
require_once __DIR__ . '/../config.php';
global $pdo;

if (session_status() === PHP_SESSION_NONE) { session_start(); }
$idProspectoDocente = (int) ($_GET['prospecto_docente'] ?? 0);

// Si ya existe al menos 1 resultado, damos opción de ver resultados o repetir
$usuario_id = $_SESSION['user_id'] ?? null;
$ultimo = null;
if ($usuario_id) {
    try {
        $stmt = $pdo->prepare("SELECT id, creado_en FROM disc_res WHERE user_id = ? ORDER BY creado_en DESC LIMIT 1");
        $stmt->execute([$usuario_id]);
        $ultimo = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    } catch (Throwable $e) {
        $ultimo = null;
    }
}

try {
    $query = "SELECT * FROM disc_words ORDER BY sec ASC, ord ASC, id ASC";
    $stmt = $pdo->prepare($query);
    $stmt->execute();
    $todas_las_palabras = $stmt->fetchAll();

    $secciones = [];
    foreach ($todas_las_palabras as $fila) {
        $secciones[$fila['sec']][] = $fila;
    }
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>

<link rel="stylesheet" href="css/examen_disc.css">

<div class="disc-container">
    <?php if ($ultimo): ?>
        <div class="disc-header" id="disc-previo">
            <h2>Evaluación DISC</h2>
            <p>Ya presentaste este test. ¿Qué deseas hacer?</p>
            <div style="display:flex; gap:10px; justify-content:center; flex-wrap:wrap; margin-top:12px;">
                <button type="button" class="btn-nav" onclick="cargarUltimoResultadoDisc(<?php echo (int)$ultimo['id']; ?>)">Ver mis resultados</button>
                <button type="button" class="btn-nav" style="background:var(--azul); color:white; border-color:var(--azul);" onclick="mostrarTestDisc()">Volver a presentarlo</button>
            </div>
            <small style="display:block; margin-top:10px; color:#666;">
                Último intento: <?php echo htmlspecialchars($ultimo['creado_en']); ?>
            </small>
        </div>
        <script>
            function cargarUltimoResultadoDisc(id) {
                const contenedor = document.getElementById('main-content');
                fetch('views/resultado_disc.php?id=' + encodeURIComponent(id))
                    .then(r => r.text())
                    .then(html => { contenedor.innerHTML = html; })
                    .catch(() => { contenedor.innerHTML = '<div class="alert">Error al cargar resultado.</div>'; });
            }
            function mostrarTestDisc() {
                const previo = document.getElementById('disc-previo');
                const test = document.getElementById('disc-test');
                if (previo) previo.style.display = 'none';
                if (test) test.style.display = 'block';
            }
        </script>
    <?php endif; ?>

    <div id="disc-test" style="<?php echo $ultimo ? 'display:none;' : 'display:block;'; ?>">
    <div class="disc-header">
        <h2>Evaluación DISC</h2>
        <p>Selecciona una opción por columna en cada grupo.</p>
        <div class="progress-container" style="background:#eee; border-radius:10px; height:10px; margin-top:10px;">
            <div id="progress-bar" style="background:var(--azul); width:0%; height:100%; border-radius:10px; transition:0.3s;"></div>
        </div>
    </div>

    <form id="form-disc" action="php/procesar_disc.php" method="POST">
        <?php if ($idProspectoDocente > 0): ?>
            <input type="hidden" name="id_prospecto_docente" value="<?php echo $idProspectoDocente; ?>">
        <?php endif; ?>
        <?php 
        $total_secciones = count($secciones);
        $index = 1;
        foreach ($secciones as $num_seccion => $palabras): 
            $display = ($index === 1) ? 'block' : 'none';
        ?>
            <div class="disc-card seccion-step" id="step-<?php echo $index; ?>" style="display: <?php echo $display; ?>;">
                <div class="card-title">Grupo <?php echo $index; ?> de <?php echo $total_secciones; ?></div>
                <table class="disc-table">
                    <thead>
                        <tr>
                            <th>Palabra</th>
                            <th class="text-center">MÁS (+)</th>
                            <th class="text-center">MENOS (-)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($palabras as $p): ?>
                            <tr data-id="<?php echo $p['id']; ?>">
                                <td class="word-cell">
                                    <span class="word-text"><?php echo htmlspecialchars($p['word']); ?></span>
                                    <span class="definition"><?php echo htmlspecialchars($p['defn']); ?></span>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="mas[<?php echo $num_seccion; ?>]" 
                                           value="<?php echo $p['id']; ?>" 
                                           data-row="<?php echo $p['id']; ?>" class="radio-mas" required>
                                </td>
                                <td class="text-center">
                                    <input type="radio" name="menos[<?php echo $num_seccion; ?>]" 
                                           value="<?php echo $p['id']; ?>" 
                                           data-row="<?php echo $p['id']; ?>" class="radio-menos" required>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <div class="nav-buttons" style="padding: 20px; display: flex; justify-content: space-between;">
                    <?php if ($index > 1): ?>
                        <button type="button" class="btn-nav" onclick="cambiarPaso(<?php echo $index - 1; ?>)">Anterior</button>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>

                    <?php if ($index < $total_secciones): ?>
                        <button type="button" class="btn-nav" onclick="validarYPasar(<?php echo $index; ?>)">Siguiente</button>
                    <?php else: ?>
                        <button type="submit" class="btn-enviar" style="margin:0;">Finalizar Test</button>
                    <?php endif; ?>
                </div>
            </div>
        <?php $index++; endforeach; ?>
    </form>
</div>

<script>
    let pasoActual = 1;
    const totalPasos = <?php echo $total_secciones; ?>;

    // 1. Lógica para mostrar de uno en uno
    function cambiarPaso(nuevoPaso) {
        document.getElementById(`step-${pasoActual}`).style.display = 'none';
        document.getElementById(`step-${nuevoPaso}`).style.display = 'block';
        pasoActual = nuevoPaso;
        actualizarProgreso();
    }

    function validarYPasar(paso) {
        const contenedor = document.getElementById(`step-${paso}`);
        const masSeleccionado = contenedor.querySelector('input[class="radio-mas"]:checked');
        const menosSeleccionado = contenedor.querySelector('input[class="radio-menos"]:checked');

        if (!masSeleccionado || !menosSeleccionado) {
            alert("Por favor, selecciona una opción en cada columna antes de continuar.");
            return;
        }
        if (masSeleccionado.dataset.row === menosSeleccionado.dataset.row) {
            alert("No puedes seleccionar la misma palabra en MÁS y MENOS.");
            return;
        }
        cambiarPaso(paso + 1);
    }

    function actualizarProgreso() {
        const porcentaje = (pasoActual / totalPasos) * 100;
        document.getElementById('progress-bar').style.width = porcentaje + '%';
    }

    // 2. Lógica para bloquear la misma fila
    document.querySelectorAll('input[type="radio"]').forEach(radio => {
        radio.addEventListener('change', function() {
            const rowId = this.dataset.row;
            const isMas = this.classList.contains('radio-mas');
            const bloque = this.closest('.disc-card');
            
            // Buscar el radio opuesto en la misma fila
            const opuesto = bloque.querySelector(`.radio-${isMas ? 'menos' : 'mas'}[data-row="${rowId}"]`);
            
            // Habilitar todos primero en este bloque
            bloque.querySelectorAll('input[type="radio"]').forEach(r => r.disabled = false);
            
            // Bloquear los que ya están seleccionados de forma cruzada
            bloque.querySelectorAll('input[type="radio"]:checked').forEach(checkedRadio => {
                const cRowId = checkedRadio.dataset.row;
                const cIsMas = checkedRadio.classList.contains('radio-mas');
                const cOpuesto = bloque.querySelector(`.radio-${cIsMas ? 'menos' : 'mas'}[data-row="${cRowId}"]`);
                if (cOpuesto) cOpuesto.disabled = true;
            });
        });
    });

    actualizarProgreso();
</script>
    </div>