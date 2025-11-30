<?php
if (!defined('ABSPATH')) {
    exit;
}

get_header();

// Ambil opsi & token state kalau fungsi tersedia
$opts          = function_exists('cbt_bima_get_options') ? cbt_bima_get_options() : [];
$token_state   = function_exists('cbt_bima_get_token_state') ? cbt_bima_get_token_state() : [
    'token'    => '',
    'expires'  => 0,
    'duration' => 15,
];
$token         = isset($token_state['token']) ? $token_state['token'] : '';
$expires_ts    = isset($token_state['expires']) ? (int) $token_state['expires'] : 0;
$duration_min  = isset($token_state['duration']) ? (int) $token_state['duration'] : 15;
$now_ts        = time();
$is_active     = ($token && $expires_ts > $now_ts);
$allow_self_reg = !empty($opts['allow_self_registration']);

$expires_text = $expires_ts > 0
    ? date_i18n('d-m-Y H:i:s', $expires_ts)
    : '-';

$timezone_label = isset($opts['timezone']) ? $opts['timezone'] : 'WIB';
?>
<main class="min-h-[calc(100vh-80px)] flex items-center justify-center py-8 px-4">
  <div class="w-full max-w-5xl mx-auto grid gap-6 md:grid-cols-[minmax(0,2fr)_minmax(320px,1.3fr)] items-stretch">

    <!-- Panel Kiri: Branding / Info -->
    <section class="hidden md:flex flex-col justify-center rounded-3xl border border-slate-700/70 bg-gradient-to-br from-slate-900 via-slate-900 to-slate-950 shadow-2xl px-8 py-10 space-y-6">
      <div>
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-emerald-400/50 bg-emerald-500/10 text-emerald-200 text-xs mb-4">
          <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span>CBT Mode Aktif</span>
        </div>
        <h1 class="text-2xl md:text-3xl font-semibold tracking-tight text-slate-50 mb-2">
          Ujian Berbasis Komputer
        </h1>
        <p class="text-sm text-slate-300 max-w-md">
          Silakan login menggunakan akun CBT yang sudah diberikan. 
          Token ujian akan berubah setiap beberapa menit sesuai pengaturan.
        </p>
      </div>

      <div class="space-y-3 text-sm text-slate-200/90">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="text-[11px] uppercase tracking-wide text-slate-400">
              Token Aktif
            </div>
            <div class="mt-1 font-mono text-lg">
              <?php if ($token) : ?>
                <?php echo esc_html($token); ?>
              <?php else : ?>
                <span class="text-slate-500 italic">Belum ada token</span>
              <?php endif; ?>
            </div>
          </div>
          <div class="text-right text-xs text-slate-300">
            <div>Masa aktif: <?php echo (int) $duration_min; ?> menit</div>
            <div>Kadaluarsa: <?php echo esc_html($expires_text); ?></div>
            <div>Zona waktu: <?php echo esc_html($timezone_label); ?></div>
            <?php if ($token) : ?>
              <div class="mt-1 text-[11px]">
                Status:
                <?php if ($is_active) : ?>
                  <span class="text-emerald-400 font-semibold">AKTIF</span>
                <?php else : ?>
                  <span class="text-red-400 font-semibold">KADALUARSA</span>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="border-t border-slate-700/70 pt-3 text-xs text-slate-400">
          <p>
            Pastikan token yang dimasukkan siswa sama dengan token yang tampil di layar pengawas.
          </p>
        </div>
      </div>
    </section>

    <!-- Panel Kanan: Login & Register -->
    <section class="rounded-3xl border border-slate-700/70 bg-slate-900/90 shadow-2xl px-5 py-6 md:px-7 md:py-8">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-slate-50">
            Portal Ujian CBT
          </h2>
          <p class="text-xs text-slate-400">
            Login menggunakan username, password, token, dan mapel ujian.
          </p>
        </div>
        <div class="hidden md:block text-right text-[11px] text-slate-400">
          <div>Token layar:</div>
          <div id="cbt-token-info" class="font-mono text-xs text-emerald-300">
            <!-- diisi oleh JS /token -->
            <?php if ($token): ?>
              <?php echo esc_html($token); ?>
            <?php else: ?>
              -
            <?php endif; ?>
          </div>
          <button
            id="cbt-refresh-token"
            type="button"
            class="mt-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full border border-slate-600 text-[10px] text-slate-200 hover:bg-slate-800">
            Refresh Token
          </button>
        </div>
      </div>

      <!-- PANEL LOGIN -->
      <div id="cbt-login-panel" class="space-y-4">
        <div class="space-y-3 text-sm">
          <div>
            <label for="cbt-username" class="block mb-1 text-xs font-medium text-slate-200">
              Username CBT
            </label>
            <input
              id="cbt-username"
              type="text"
              autocomplete="username"
              class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>

          <div>
            <label for="cbt-password" class="block mb-1 text-xs font-medium text-slate-200">
              Password
            </label>
            <input
              id="cbt-password"
              type="password"
              autocomplete="current-password"
              class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label for="cbt-token" class="block mb-1 text-xs font-medium text-slate-200">
                Token Ujian
              </label>
              <input
                id="cbt-token"
                type="text"
                inputmode="numeric"
                class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" />
            </div>
            <div>
              <label for="cbt-test-code" class="block mb-1 text-xs font-medium text-slate-200">
                Mapel / Kode Ujian
              </label>
              <select
                id="cbt-test-code"
                class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-2 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Memuat mapel...</option>
              </select>
            </div>
          </div>
        </div>

        <button
          id="cbt-login-btn"
          type="button"
          class="w-full mt-1 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-sm font-semibold text-white shadow-md shadow-blue-900/50 transition-colors">
          Masuk ke Ujian
        </button>

        <div class="mt-3 text-[11px] text-slate-400 border-t border-slate-700/80 pt-3">
          <p>
            Setelah login, Anda akan langsung masuk ke halaman ujian CBT sesuai mapel dan sesi yang ditentukan.
          </p>
        </div>
      </div>

      <!-- PANEL REGISTER (SELF REG) -->
      <div
        id="cbt-register-panel"
        class="space-y-3 mt-4"
        style="display: <?php echo $allow_self_reg ? 'none' : 'none'; ?>;">
        <?php if ($allow_self_reg) : ?>
          <h3 class="text-sm font-semibold text-slate-100">
            Daftar Peserta Baru
          </h3>

          <div class="space-y-3 text-sm">
            <div>
              <label for="cbt-reg-fullname" class="block mb-1 text-xs font-medium text-slate-200">
                Nama Lengkap
              </label>
              <input
                id="cbt-reg-fullname"
                type="text"
                class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
            </div>

            <div>
              <label for="cbt-reg-nis" class="block mb-1 text-xs font-medium text-slate-200">
                NIS (opsional)
              </label>
              <input
                id="cbt-reg-nis"
                type="text"
                class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label for="cbt-reg-username" class="block mb-1 text-xs font-medium text-slate-200">
                  Username
                </label>
                <input
                  id="cbt-reg-username"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
              </div>
              <div>
                <label for="cbt-reg-password" class="block mb-1 text-xs font-medium text-slate-200">
                  Password
                </label>
                <input
                  id="cbt-reg-password"
                  type="password"
                  class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-3 py-2 text-sm text-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500" />
              </div>
            </div>

            <div>
              <label for="cbt-reg-mapel" class="block mb-1 text-xs font-medium text-slate-200">
                Pilih Mapel / Kode Ujian
              </label>
              <select
                id="cbt-reg-mapel"
                class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-2 py-2 text-xs text-slate-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                <option value="">Memuat mapel...</option>
              </select>
            </div>

            <div class="grid grid-cols-3 gap-2">
              <div>
                <label for="cbt-reg-ket1" class="block mb-1 text-[10px] font-medium text-slate-200">
                  Ket. 1
                </label>
                <input
                  id="cbt-reg-ket1"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-2 py-1 text-[11px] text-slate-50" />
              </div>
              <div>
                <label for="cbt-reg-ket2" class="block mb-1 text-[10px] font-medium text-slate-200">
                  Ket. 2
                </label>
                <input
                  id="cbt-reg-ket2"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-2 py-1 text-[11px] text-slate-50" />
              </div>
              <div>
                <label for="cbt-reg-ket3" class="block mb-1 text-[10px] font-medium text-slate-200">
                  Ket. 3
                </label>
                <input
                  id="cbt-reg-ket3"
                  type="text"
                  class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-2 py-1 text-[11px] text-slate-50" />
              </div>
            </div>

            <div>
              <label for="cbt-reg-sesi" class="block mb-1 text-[10px] font-medium text-slate-200">
                Sesi (opsional)
              </label>
              <input
                id="cbt-reg-sesi"
                type="text"
                placeholder="1, 2, dst."
                class="w-full rounded-lg border border-slate-600 bg-slate-900/80 px-2 py-1 text-[11px] text-slate-50" />
            </div>

            <button
              id="cbt-register-btn"
              type="button"
              class="w-full mt-1 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-sm font-semibold text-white shadow-md shadow-emerald-900/50 transition-colors">
              Daftar & Simpan
            </button>

            <p class="mt-2 text-[11px] text-slate-400">
              Setelah pendaftaran berhasil, silakan login menggunakan username & password yang baru dibuat.
            </p>
          </div>
        <?php else : ?>
          <p class="text-xs text-slate-400 italic">
            Pendaftaran mandiri dimatikan oleh admin.
          </p>
        <?php endif; ?>
      </div>

      <!-- TOGGLE LOGIN / REGISTER -->
      <?php if ($allow_self_reg) : ?>
        <div class="mt-4 text-center text-xs text-slate-300">
          <button
            id="cbt-toggle-register"
            type="button"
            class="underline underline-offset-2 hover:text-emerald-300">
            Belum punya akun? Daftar di sini
          </button>
        </div>
      <?php endif; ?>
    </section>
  </div>
</main>

<?php
get_footer();
