<?php
/**
 * Carga todas las clases del módulo de exámenes (evita errores por require faltante).
 */
require_once __DIR__ . '/FaseHelper.php';
require_once __DIR__ . '/ExamPdfHelper.php';
require_once __DIR__ . '/ExamPrintTemplate.php';
require_once __DIR__ . '/BancoInglesService.php';
require_once __DIR__ . '/InglesExamService.php';
require_once __DIR__ . '/AnswerSheetLayout.php';
require_once __DIR__ . '/AnswerSheetTemplate.php';
require_once __DIR__ . '/AnswerSheetService.php';
