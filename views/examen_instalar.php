<?php
require_once __DIR__ . '/../php/exam/ExamPdfHelper.php';
if (session_status() === PHP_SESSION_NONE) { session_start(); }
?>

<link rel="stylesheet" href="css/resultados.css">
<link rel="stylesheet" href="css/examenes.css">

<div class="result-container" style="max-width:720px;">
  <div class="result-header">
    <h2><i class="fas fa-info-circle"></i> Exámenes sin instalación extra</h2>
  </div>

  <div class="exam-msg ok" style="display:block;">
    <strong>No necesita Composer ni terminal.</strong> En hosting compartido el sistema ya genera:
    <ul style="margin:8px 0 0 18px;line-height:1.7;">
      <li>Examen en <strong>HTML</strong> (abrir y Ctrl+P → Guardar como PDF)</li>
      <li><strong>Código QR</strong> del audio de listening</li>
      <li><strong>CSV</strong> de hoja de respuestas</li>
    </ul>
  </div>

  <div class="banco-import-box" style="margin-top:16px;">
    <h3>Fases con nombre (no números)</h3>
    <p style="color:#555;line-height:1.6;">
      En el banco de preguntas y en el CSV use la columna <code>fase</code> con texto libre, por ejemplo:
      <strong>A1 1-4</strong>, <strong>B1+ 5-8</strong> (inglés) o <strong>Windows</strong>, <strong>Excel</strong> (computación).
    </p>
    <p style="color:#555;">
      Si ya creó las tablas con fase numérica, ejecute en <strong>phpMyAdmin</strong> el archivo:
      <code>sql/exam_fase_texto_migrate.sql</code>
    </p>
  </div>

  <button type="button" onclick="cargarSeccion('examen_generar')" class="primary" style="margin-top:12px;">
    Ir a generar examen
  </button>
  <button type="button" onclick="cargarSeccion('examen_banco_ingles')" style="margin-top:12px;margin-left:8px;">
    Ir al banco de preguntas
  </button>
</div>
