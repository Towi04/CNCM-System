<?php

/**

 * Banner HID — visible solo si JS detecta que falta el driver (clase is-visible).

 * @var string $hid_banner_context

 */

$hidLinks = hay_hid_lite_client_links();

$ctx = $hid_banner_context ?? 'lector U.areU 5300';

?>

<div id="hid-lite-client-banner-wrap" class="hid-lite-client-banner-wrap" hidden>

<?php require __DIR__ . '/hid_lite_client_install.php'; ?>

</div>


