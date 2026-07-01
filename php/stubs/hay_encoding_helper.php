<?php

/** Stubs para Intelephense — implementación real en php/encoding_helper.php */

function hay_utf8_init(?PDO $pdo = null): void {}
function hay_html_utf8_header(): void {}
function hay_json_response(array $data, int $code = 200): void {}
function hay_web_root(): string { return '/'; }
function hay_asset_url(string $relative): string { return '/' . ltrim($relative, '/'); }
