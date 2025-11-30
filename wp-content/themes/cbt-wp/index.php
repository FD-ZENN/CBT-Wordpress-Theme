<?php
/**
 * Index template
 */
if (!defined('ABSPATH')) {
    exit;
}
get_header();
?>
<div class="min-h-screen flex items-center justify-center bg-slate-900 text-slate-100">
  <div class="text-center px-4">
    <h1 class="text-2xl font-bold mb-2">Theme CBT WP Terpasang</h1>
    <p class="text-sm text-slate-300">
      Halaman depan situs akan memakai <code>front-page.php</code> sebagai login CBT.
    </p>
    <p class="text-xs text-slate-500 mt-3">
      Jika Anda melihat halaman ini, berarti ini bukan halaman depan (front page).
    </p>
  </div>
</div>
<?php
get_footer();
