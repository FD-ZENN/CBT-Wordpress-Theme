<?php
if (!defined('ABSPATH')) {
    exit;
}
?>

<footer class="mt-8 border-t border-slate-200/70 dark:border-slate-700/70 bg-slate-50/95 dark:bg-slate-900/95">
  <div class="max-w-5xl mx-auto px-4 py-4 sm:py-5 flex flex-col sm:flex-row items-center gap-3 sm:gap-4 text-xs sm:text-[13px]">

    <!-- Kiri: branding / logo kecil -->
    <div class="flex items-center gap-2">
      <div class="h-8 w-8 rounded-md bg-sky-700 text-sky-50 flex items-center justify-center text-[10px] font-semibold leading-none">
        CBT
      </div>
      <div class="leading-tight text-slate-700 dark:text-slate-200">
        <div class="font-semibold">
          Sistem Ujian Berbasis Komputer
        </div>
        <div class="text-[11px] text-slate-500 dark:text-slate-400">
          Mode Tryout / Ujian Sekolah
        </div>
      </div>
    </div>

    <!-- Tengah: info sekolah -->
    <div class="sm:flex-1 text-center text-[11px] text-slate-500 dark:text-slate-400">
      <div class="font-semibold text-slate-700 dark:text-slate-200">
        <?php bloginfo('name'); ?>
      </div>
      <div class="hidden sm:block">
        &copy; <?php echo esc_html( date_i18n('Y') ); ?> &middot; 
        Semua hak dilindungi.
      </div>
    </div>

    <!-- Kanan: info teknis kecil -->
    <div class="text-right text-[11px] text-slate-500 dark:text-slate-400 leading-tight">
      <div>
        <span class="font-semibold">Server:</span>
        <span class="font-mono">
          <?php echo esc_html( $_SERVER['HTTP_HOST'] ?? '' ); ?>
        </span>
      </div>
      <div class="mt-0.5">
        <span class="font-semibold">Versi:</span>
        <span class="font-mono">
          CBT-WP v1.0
        </span>
      </div>
    </div>

  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
