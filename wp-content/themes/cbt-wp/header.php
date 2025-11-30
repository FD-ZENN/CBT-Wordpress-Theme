<?php
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
  <meta charset="<?php bloginfo('charset'); ?>">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Konfigurasi Tailwind: dark mode pakai class -->
  <script>
    tailwind = {
      config: {
        darkMode: 'class'
      }
    }
  </script>
  <script src="https://cdn.tailwindcss.com"></script>

  <?php wp_head(); ?>
</head>

<body <?php body_class('min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-slate-100'); ?>>

<div id="page" class="min-h-screen flex flex-col">

  <!-- ================= HEADER MIRIP UNBK ================= -->
  <header class="shadow-md">
    <!-- Bar atas: judul sistem -->
    <div class="bg-gradient-to-r from-sky-700 via-sky-800 to-sky-900 text-slate-50">
      <div class="max-w-5xl mx-auto px-4 py-3 flex items-center gap-3">
        <!-- Logo kecil / inisial -->
        <div class="flex items-center gap-3">
          <div class="h-10 w-10 rounded-md bg-white/15 flex items-center justify-center text-[11px] font-semibold leading-none">
            CBT
          </div>
          <div>
            <div class="text-[10px] uppercase tracking-[0.25em] text-sky-100/80">
              Ujian Berbasis Komputer
            </div>
            <div class="text-base md:text-lg font-semibold leading-snug">
              Sistem Ujian Berbasis Komputer (CBT)
            </div>
            <div class="text-[11px] md:text-xs text-sky-100/90 mt-0.5">
              Tahun Pelaksanaan <?php echo esc_html( date_i18n('Y') ); ?> &bull; Mode Tryout
            </div>
          </div>
        </div>

        <!-- Info sekolah di kanan -->
        <div class="ml-auto text-right hidden md:block text-[11px] leading-tight text-sky-100/80">
          <div class="font-semibold">
            <?php bloginfo('name'); ?>
          </div>
          <div class="text-[10px] opacity-85">
            <?php bloginfo('description'); ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Bar bawah: server, tanggal, jam (khas UNBK) -->
    <div class="bg-sky-900 text-sky-50 border-t border-sky-700/60">
      <div class="max-w-5xl mx-auto px-4 py-1.5 flex items-center justify-between text-[11px] md:text-xs">
        <div class="flex items-center gap-2">
          <span class="font-semibold">Server:</span>
          <span class="font-mono text-[11px]">
            <?php echo esc_html( $_SERVER['HTTP_HOST'] ?? '' ); ?>
          </span>
        </div>
        <div class="flex items-center gap-4">
          <div>
            <span class="font-semibold">Tanggal:</span>
            <span><?php echo esc_html( date_i18n('d F Y') ); ?></span>
          </div>
          <div>   
         </div>
       </div>
     </div>
   </div>
</header>