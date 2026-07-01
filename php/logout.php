<?php

require_once __DIR__ . '/session_helper.php';

hay_session_no_cache_headers();

hay_session_destroy_completa();

header('Location: ../index.php?sesion=1');

exit;

