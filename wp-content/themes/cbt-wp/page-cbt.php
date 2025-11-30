<?php
/**
 * Template Halaman CBT
 * File: page-cbt.php
 */

if (!defined('ABSPATH')) exit;

get_header();

global $wpdb;

$session_param       = isset($_GET['session']) ? sanitize_text_field($_GET['session']) : '';
$numeric_session_id  = 0;
$table_tests         = $wpdb->prefix . 'cbt_tests';
$table_students      = $wpdb->prefix . 'cbt_students';
$table_sessions      = $wpdb->prefix . 'cbt_sessions';

/**
 * 1. MODE LAMA: ?session=5 (angka murni)
 */
if ($session_param !== '' && ctype_digit($session_param)) {
    $numeric_session_id = (int) $session_param;
}
/**
 * 2. MODE SLUG: ?session=MTK-01-siswa1  (kodeTes + "-" + username/nis)
 */
elseif ($session_param !== '') {
    $lastDashPos = strrpos($session_param, '-');
    if ($lastDashPos !== false) {
        $code_part = substr($session_param, 0, $lastDashPos);      // "MTK-01"
        $user_part = substr($session_param, $lastDashPos + 1);     // "siswa1" / "1001"

        $test_code = trim($code_part);
        $user_key  = trim($user_part);

        // Ambil data tes
        $test = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_tests WHERE code = %s LIMIT 1", $test_code),
            ARRAY_A
        );

        // Ambil data siswa berdasarkan username ATAU NIS
        $student = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM $table_students WHERE username = %s OR nis = %s LIMIT 1",
                $user_key,
                $user_key
            ),
            ARRAY_A
        );

        if ($test && $student) {
            // Cari sesi yang masih in_progress
            $session = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM $table_sessions
                     WHERE student_id = %d AND test_id = %d AND status = 'in_progress'
                     ORDER BY id DESC LIMIT 1",
                    $student['id'],
                    $test['id']
                ),
                ARRAY_A
            );

            if ($session) {
                $numeric_session_id = (int) $session['id'];
            } else {
                // Kalau tidak ada in_progress, cek apakah sudah selesai (finished)
                $finished = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table_sessions
                         WHERE student_id = %d AND test_id = %d AND status = 'finished'
                         ORDER BY id DESC LIMIT 1",
                        $student['id'],
                        $test['id']
                    ),
                    ARRAY_A
                );

                if ($finished) {
                    ?>
                    <div class="cbt-shell">
                      <div class="cbt-exam-container">
                        <div class="cbt-message-box cbt-message-finished">
                          <h2>Ujian Sudah Selesai</h2>
                          <p>
                            Anda sudah menyelesaikan ujian
                            <strong><?php echo esc_html($test['name']); ?></strong>.<br>
                            Silakan hubungi pengawas jika membutuhkan reset.
                          </p>
                          <p>
                            <a href="<?php echo esc_url(home_url('/')); ?>" class="cbt-button">
                              Kembali ke Halaman Login
                            </a>
                          </p>
                        </div>
                      </div>
                    </div>
                    <?php
                    get_footer();
                    exit;
                }
            }
        }
    }
}

/**
 * 3. Kalau tetap tidak ketemu session_id → tampilkan pesan error
 */
if ($numeric_session_id <= 0) {
    ?>
    <div class="cbt-shell">
      <div class="cbt-exam-container">
        <div class="cbt-message-box cbt-message-error">
          <h2>Sesi Tidak Ditemukan</h2>
          <p>
            Sesi ujian tidak valid atau sudah berakhir.<br>
            Silakan login ulang melalui halaman utama.
          </p>
          <p>
            <a href="<?php echo esc_url(home_url('/')); ?>" class="cbt-button">
              Kembali ke Halaman Login
            </a>
          </p>
        </div>
      </div>
    </div>
    <?php
    get_footer();
    exit;
}
?>

<div class="cbt-shell">
  <div class="cbt-exam-container">
    <div id="cbt-exam-root"
         data-session-id="<?php echo esc_attr($numeric_session_id); ?>">
      <p>Memuat ujian...</p>
    </div>
  </div>
</div>

<?php
get_footer();
