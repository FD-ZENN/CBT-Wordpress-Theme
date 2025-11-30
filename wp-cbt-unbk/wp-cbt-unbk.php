<?php
/**
 * Plugin Name: WP CBT UNBK
 * Description: Sistem Ujian CBT mirip Bimasoft, integrasi WordPress + Excel.
 * Author: Yang Mulia Raja + GPT
 * Version: 1.0.0
 */

if (!defined('ABSPATH')) exit;

class WPCBT_UNBK {

    const OPTION_KEY = 'wp_cbt_unbk_settings';
    const EXCEL_KEY  = '123'; // default, bisa diubah di settings

    public function __construct() {
        register_activation_hook(__FILE__, [$this, 'on_activate']);
        add_action('admin_menu', [$this, 'register_menu']);
        add_action('admin_init', [$this, 'register_settings']);
        add_shortcode('cbt_login', [$this, 'shortcode_login']);
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('rest_api_init', [$this, 'register_rest_api']);
    }

    public function on_activate() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // Buat tabel-tabel (pakai SQL di bagian sebelumnya)
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $prefix = $wpdb->prefix;

        // Contoh: tests
        $sql_tests = "CREATE TABLE {$prefix}cbt_tests (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            kode VARCHAR(50) UNIQUE,
            nama VARCHAR(255),
            status VARCHAR(20),
            subtest VARCHAR(50),
            tanggal DATE,
            waktu TIME,
            alokasi_menit INT,
            jumlah_soal INT,
            wajib_dikerjakan INT,
            shuffle_soal TINYINT(1),
            shuffle_option TINYINT(1),
            grouping VARCHAR(50),
            lock_soal TINYINT(1),
            time_method ENUM('classic','dynamic') DEFAULT 'classic',
            minimal_sisa_menit INT DEFAULT 60,
            token VARCHAR(10),
            token_valid_until DATETIME,
            created_at DATETIME,
            updated_at DATETIME
        ) $charset_collate;";

        dbDelta($sql_tests);

        // TODO: tambahkan dbDelta untuk cbt_students, cbt_student_tests,
        // cbt_questions, cbt_answers (pakai SQL di bagian DB tadi).

        // Set default options
        if (!get_option(self::OPTION_KEY)) {
            add_option(self::OPTION_KEY, [
                'token_lifetime'   => 15,
                'excel_key'        => '123',
                'timezone'         => 'Asia/Jakarta',
                'hide_mapel_choice'=> 0,
                'show_all_mapel'   => 0,
                'show_score_end'   => 1,
                'force_reset_exit' => 1,
                'allow_logout'     => 0,
                'self_register'    => 0,
                'auto_token'       => 1,
                'force_answer_all' => 0,
                'blocked_msg'      => 'Anda diblokir dari ujian ini.',
                'banned_msg'       => 'Anda terblokir, silakan hubungi pengawas.',
                'minimal_sisa_waktu' => 60,
                'time_method'      => 'classic',
                'save_method'      => 'realtime'
            ]);
        }
    }

    public function register_menu() {
        add_menu_page(
            'CBT UNBK',
            'CBT UNBK',
            'manage_options',
            'wp-cbt-unbk',
            [$this, 'render_settings_page'],
            'dashicons-welcome-learn-more',
            25
        );
    }

    public function register_settings() {
        register_setting('wp_cbt_unbk_group', self::OPTION_KEY);
    }

    public function render_settings_page() {
        $opts = get_option(self::OPTION_KEY);
        ?>
        <div class="wrap">
            <h1>Konfigurasi CBT UNBK</h1>
            <form method="post" action="options.php">
                <?php settings_fields('wp_cbt_unbk_group'); ?>
                <?php $o = $opts; ?>

                <table class="form-table">
                    <tr>
                        <th>Masa Aktif Token (menit)</th>
                        <td><input type="number" name="<?php echo self::OPTION_KEY; ?>[token_lifetime]" value="<?php echo esc_attr($o['token_lifetime']); ?>"></td>
                    </tr>
                    <tr>
                        <th>Excel Key</th>
                        <td><input type="text" name="<?php echo self::OPTION_KEY; ?>[excel_key]" value="<?php echo esc_attr($o['excel_key']); ?>"></td>
                    </tr>
                    <tr>
                        <th>Timezone</th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[timezone]">
                                <option value="Asia/Jakarta" <?php selected($o['timezone'], 'Asia/Jakarta'); ?>>WIB (Asia/Jakarta)</option>
                                <option value="Asia/Makassar" <?php selected($o['timezone'], 'Asia/Makassar'); ?>>WITA</option>
                                <option value="Asia/Jayapura" <?php selected($o['timezone'], 'Asia/Jayapura'); ?>>WIT</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Sembunyikan Pilihan Mapel</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[hide_mapel_choice]" value="1" <?php checked($o['hide_mapel_choice'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Tampilkan Semua Mapel</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_all_mapel]" value="1" <?php checked($o['show_all_mapel'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Tampilkan Nilai Siswa di Akhir Ujian</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[show_score_end]" value="1" <?php checked($o['show_score_end'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Wajib Reset Ketika Keluar Tanpa Logout</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[force_reset_exit]" value="1" <?php checked($o['force_reset_exit'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Siswa Boleh Logout Sebelum Selesai</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[allow_logout]" value="1" <?php checked($o['allow_logout'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Peserta Bisa Daftar Sendiri</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[self_register]" value="1" <?php checked($o['self_register'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Token Secara Otomatis</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[auto_token]" value="1" <?php checked($o['auto_token'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Tidak Wajib Menjawab Semua Soal</th>
                        <td><input type="checkbox" name="<?php echo self::OPTION_KEY; ?>[force_answer_all]" value="1" <?php checked($o['force_answer_all'],1); ?>></td>
                    </tr>
                    <tr>
                        <th>Pesan Error Siswa yg Diblokir</th>
                        <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[blocked_msg]" value="<?php echo esc_attr($o['blocked_msg']); ?>"></td>
                    </tr>
                    <tr>
                        <th>Pesan Error Siswa Terblokir</th>
                        <td><input type="text" class="regular-text" name="<?php echo self::OPTION_KEY; ?>[banned_msg]" value="<?php echo esc_attr($o['banned_msg']); ?>"></td>
                    </tr>
                    <tr>
                        <th>Minimal Sisa Waktu (menit)</th>
                        <td><input type="number" name="<?php echo self::OPTION_KEY; ?>[minimal_sisa_waktu]" value="<?php echo esc_attr($o['minimal_sisa_waktu']); ?>"></td>
                    </tr>
                    <tr>
                        <th>Metode Penghitungan Waktu</th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[time_method]">
                                <option value="classic" <?php selected($o['time_method'],'classic'); ?>>Classic</option>
                                <option value="dynamic" <?php selected($o['time_method'],'dynamic'); ?>>Dynamic (UNBK)</option>
                            </select>
                            <p class="description">
                                Dynamic: waktu mulai saat siswa login, berhenti saat logout/error, lanjut lagi saat login.<br>
                                Classic: waktu berjalan sesuai jadwal, walau siswa logout/mati lampu.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th>Metode Penyimpanan Jawaban</th>
                        <td>
                            <select name="<?php echo self::OPTION_KEY; ?>[save_method]">
                                <option value="realtime" <?php selected($o['save_method'],'realtime'); ?>>Realtime - langsung simpan ke server</option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button(); ?>
            </form>

            <hr>
            <h2>Token Aktif</h2>
            <?php $this->render_token_box(); ?>
        </div>
        <?php
    }

    private function render_token_box() {
        global $wpdb;
        $opts = get_option(self::OPTION_KEY);
        $prefix = $wpdb->prefix;

        // Contoh sederhana: 1 token global untuk semua test.
        $current_token = get_transient('wp_cbt_unbk_token');
        $expires       = get_transient('wp_cbt_unbk_token_exp');

        if (!$current_token || !$expires || current_time('timestamp') > $expires) {
            $current_token = $this->generate_token();
            $ttl = intval($opts['token_lifetime']) * 60;
            $exp = current_time('timestamp') + $ttl;
            set_transient('wp_cbt_unbk_token', $current_token, $ttl);
            set_transient('wp_cbt_unbk_token_exp', $exp, $ttl);
        }

        echo '<p><strong>Token Saat Ini:</strong> <code style="font-size:24px;">'.$current_token.'</code></p>';
        echo '<p>Masa aktif sampai: '.date_i18n('d-m-Y H:i:s', get_transient('wp_cbt_unbk_token_exp')).'</p>';
        echo '<form method="post">';
        if (isset($_POST['regenerate_token'])) {
            delete_transient('wp_cbt_unbk_token');
            delete_transient('wp_cbt_unbk_token_exp');
            echo '<meta http-equiv="refresh" content="0">';
        }
        submit_button('Generate Token Baru', 'secondary', 'regenerate_token', false);
        echo '</form>';
    }

    private function generate_token($length = 6) {
        $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $token = '';
        for ($i=0;$i<$length;$i++) {
            $token .= $chars[random_int(0, strlen($chars)-1)];
        }
        return $token;
    }

    public function enqueue_scripts() {
        if (!is_page()) return;
        wp_enqueue_style('wp-cbt-unbk', plugin_dir_url(__FILE__).'assets/cbt.css', [], '1.0');
        wp_enqueue_script('wp-cbt-unbk', plugin_dir_url(__FILE__).'assets/cbt.js', ['jquery'], '1.0', true);
        wp_localize_script('wp-cbt-unbk', 'CBT_API', [
            'ajax_url'    => admin_url('admin-ajax.php'),
            'rest_url'    => esc_url_raw( rest_url('cbt/v1/') ),
            'nonce'       => wp_create_nonce('wp_rest'),
        ]);
    }

    // Shortcode [cbt_login]
    public function shortcode_login() {
        ob_start();
        ?>
        <div class="cbt-login-wrapper">
          <h2>Login Ujian CBT</h2>
          <form id="cbt-login-form">
            <label>Username</label>
            <input type="text" name="username" required>

            <label>Password</label>
            <input type="password" name="password" required>

            <label>Mapel</label>
            <select name="kode_test" id="cbt-mapel-select">
              <!-- diisi via AJAX / WP_Query test aktif -->
            </select>

            <button type="button" id="cbt-btn-next-token">Lanjut</button>
          </form>

          <form id="cbt-token-form" style="display:none;">
            <label>Nama Lengkap</label>
            <input type="text" name="nama_siswa" required>

            <label>Token Ujian</label>
            <input type="text" name="token" required>

            <button type="button" id="cbt-btn-login-final">Masuk Ujian</button>
          </form>
        </div>
        <div id="cbt-login-message"></div>
        <?php
        return ob_get_clean();
    }

    // REST API untuk Excel dan front-end
    public function register_rest_api() {
        register_rest_route('cbt/v1', '/ping', [
            'methods'  => 'GET',
            'callback' => function() {
                return ['status' => 'ok'];
            },
            'permission_callback' => '__return_true'
        ]);

        // Excel: simpan test
        register_rest_route('cbt/v1', '/save-test', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_save_test'],
            'permission_callback' => '__return_true'
        ]);

        // Excel: simpan soal
        register_rest_route('cbt/v1', '/save-question', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_save_question'],
            'permission_callback' => '__return_true'
        ]);

        // Excel: simpan peserta
        register_rest_route('cbt/v1', '/save-student', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_save_student'],
            'permission_callback' => '__return_true'
        ]);

        // Excel: ambil hasil
        register_rest_route('cbt/v1', '/results', [
            'methods'  => 'GET',
            'callback' => [$this, 'api_get_results'],
            'permission_callback' => '__return_true'
        ]);

        // Front-end: login, ambil soal, simpan jawaban, selesai
        register_rest_route('cbt/v1', '/login', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_login'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbt/v1', '/questions', [
            'methods'  => 'GET',
            'callback' => [$this, 'api_get_questions'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbt/v1', '/save-answer', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_save_answer'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbt/v1', '/finish', [
            'methods'  => 'POST',
            'callback' => [$this, 'api_finish_test'],
            'permission_callback' => '__return_true'
        ]);
    }

    // ==== Helper validasi Excel Key ====
    private function validate_excel_key($request) {
        $opts = get_option(self::OPTION_KEY);
        $excel_key = $opts['excel_key'] ?? self::EXCEL_KEY;
        $key = $request->get_header('X-Excel-Key');
        if (!$key) $key = $request->get_param('excel_key');
        if ($key !== $excel_key) {
            return new WP_Error('forbidden', 'Excel Key salah', ['status'=>403]);
        }
        return true;
    }

    // ==== API Excel ====
    public function api_save_test($request) {
        $valid = $this->validate_excel_key($request);
        if (is_wp_error($valid)) return $valid;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $data = [
            'kode'             => sanitize_text_field($request['kode']),
            'nama'             => sanitize_text_field($request['nama']),
            'status'           => sanitize_text_field($request['status']),
            'subtest'          => sanitize_text_field($request['subtest']),
            'tanggal'          => sanitize_text_field($request['tanggal']),
            'waktu'            => sanitize_text_field($request['waktu']),
            'alokasi_menit'    => intval($request['alokasi']),
            'jumlah_soal'      => intval($request['jumlah_soal']),
            'wajib_dikerjakan' => intval($request['wajib_dikerjakan']),
            'shuffle_soal'     => intval($request['shuffle']),
            'shuffle_option'   => intval($request['shuffle2']),
            'updated_at'       => current_time('mysql')
        ];

        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}cbt_tests WHERE kode=%s",
            $data['kode']
        ));

        if ($exists) {
            $wpdb->update("{$prefix}cbt_tests", $data, ['id'=>$exists]);
            $id = $exists;
        } else {
            $data['created_at'] = current_time('mysql');
            $wpdb->insert("{$prefix}cbt_tests", $data);
            $id = $wpdb->insert_id;
        }

        return ['status'=>'ok','test_id'=>$id];
    }

    public function api_save_question($request) {
        $valid = $this->validate_excel_key($request);
        if (is_wp_error($valid)) return $valid;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $kode_test = sanitize_text_field($request['kode_test']);
        $no_soal   = intval($request['no_soal']);
        $kunci     = strtoupper(substr(sanitize_text_field($request['kunci']),0,1));
        $skor      = floatval($request['skor']);
        $grouping  = sanitize_text_field($request['grouping']);
        $lock      = intval($request['lock_soal']);

        $test_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}cbt_tests WHERE kode=%s",
            $kode_test
        ));

        if (!$test_id) {
            return new WP_Error('not_found','Test tidak ditemukan',['status'=>404]);
        }

        // Asumsi: guru sudah membuat post soal dan di-tag dengan meta cbt_test & no_soal
        $post_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM {$prefix}postmeta
            WHERE meta_key='_cbt_test_kode' AND meta_value=%s
            LIMIT 1
        ", $kode_test));

        // Untuk versi 1, kita tidak paksa per-no_soal, cukup link global.
        // Kalau mau advanced: meta `_cbt_no_soal` = $no_soal.

        $data = [
            'test_id'      => $test_id,
            'post_id'      => $post_id,
            'no_soal'      => $no_soal,
            'kunci_jawaban'=> $kunci,
            'skor'         => $skor,
            'grouping'     => $grouping,
            'lock_soal'    => $lock
        ];

        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$prefix}cbt_questions
            WHERE test_id=%d AND no_soal=%d
        ", $test_id, $no_soal));

        if ($exists) {
            $wpdb->update("{$prefix}cbt_questions", $data, ['id'=>$exists]);
            $id = $exists;
        } else {
            $wpdb->insert("{$prefix}cbt_questions", $data);
            $id = $wpdb->insert_id;
        }

        return ['status'=>'ok','question_id'=>$id];
    }

    public function api_save_student($request) {
        $valid = $this->validate_excel_key($request);
        if (is_wp_error($valid)) return $valid;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $kode  = sanitize_text_field($request['username']);
        $nis   = sanitize_text_field($request['nis']);
        $nama  = sanitize_text_field($request['nama']);
        $pass  = sanitize_text_field($request['password']);
        $ket1  = sanitize_text_field($request['ket1']);
        $ket2  = sanitize_text_field($request['ket2']);
        $ket3  = sanitize_text_field($request['ket3']);
        $server= sanitize_text_field($request['server']);
        $sesi  = sanitize_text_field($request['sesi']);

        $data = compact('kode','nis','nama','ket1','ket2','ket3','server','sesi');
        $data['pass'] = wp_hash_password($pass);

        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$prefix}cbt_students WHERE kode=%s
        ", $kode));

        if ($exists) {
            $wpdb->update("{$prefix}cbt_students",$data,['id'=>$exists]);
            $id = $exists;
        } else {
            $wpdb->insert("{$prefix}cbt_students",$data);
            $id = $wpdb->insert_id;
        }

        return ['status'=>'ok','student_id'=>$id];
    }

    public function api_get_results($request) {
        $valid = $this->validate_excel_key($request);
        if (is_wp_error($valid)) return $valid;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $kode_test = sanitize_text_field($request['kode_test']);

        $test_id = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$prefix}cbt_tests WHERE kode=%s
        ", $kode_test));

        if (!$test_id) {
            return new WP_Error('not_found','Test tidak ditemukan',['status'=>404]);
        }

        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT st.id as student_test_id, s.nama, s.kode as no_peserta,
                   st.subtest_terakhir, st.benar, st.salah, st.nilai, st.status
            FROM {$prefix}cbt_student_tests st
            JOIN {$prefix}cbt_students s ON s.id = st.student_id
            WHERE st.test_id=%d
        ", $test_id), ARRAY_A);

        return ['status'=>'ok','results'=>$rows];
    }

    // ==== API front-end (login, soal, jawaban, finish) ====
    public function api_login($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $opts = get_option(self::OPTION_KEY);

        $username   = sanitize_text_field($request['username']);
        $password   = sanitize_text_field($request['password']);
        $kode_test  = sanitize_text_field($request['kode_test']);
        $nama_input = sanitize_text_field($request['nama_siswa']);
        $token      = strtoupper(trim(sanitize_text_field($request['token'])));

        // Cek token global
        $current_token = get_transient('wp_cbt_unbk_token');
        if (!$current_token || $current_token !== $token) {
            return new WP_Error('token_invalid','Token salah atau kadaluarsa',['status'=>403]);
        }

        // Cek siswa
        $student = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$prefix}cbt_students WHERE kode=%s
        ", $username));

        if (!$student || !wp_check_password($password, $student->pass)) {
            return new WP_Error('login_failed','Username / Password salah',['status'=>403]);
        }

        // Update nama jika mau sinkron
        if ($nama_input && $nama_input !== $student->nama) {
            $wpdb->update("{$prefix}cbt_students", ['nama'=>$nama_input], ['id'=>$student->id]);
        }

        // Cek test
        $test = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$prefix}cbt_tests WHERE kode=%s AND status='aktif'
        ", $kode_test));

        if (!$test) {
            return new WP_Error('test_not_found','Test tidak ditemukan / tidak aktif',['status'=>404]);
        }

        // Mode Classic: cek waktu
        if ($opts['time_method'] === 'classic') {
            $now = current_time('timestamp');
            $start = strtotime($test->tanggal.' '.$test->waktu);
            $end   = $start + ($test->alokasi_menit * 60);
            if ($now < $start || $now > $end) {
                return new WP_Error('time_invalid','Bukan waktu ujian',['status'=>403]);
            }
        }

        // Buat / ambil record student_test
        $st = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$prefix}cbt_student_tests
            WHERE student_id=%d AND test_id=%d
        ", $student->id, $test->id));

        $now_mysql = current_time('mysql');
        if (!$st) {
            $data_st = [
                'student_id'    => $student->id,
                'test_id'       => $test->id,
                'status'        => 'ongoing',
                'starttime'     => $now_mysql,
                'last_activity' => $now_mysql,
                'elapsed_seconds'=> 0
            ];
            $wpdb->insert("{$prefix}cbt_student_tests",$data_st);
            $student_test_id = $wpdb->insert_id;
        } else {
            $student_test_id = $st->id;

            // Mode Dynamic: saat login lagi, time lanjut dari elapsed_seconds
            if ($opts['time_method'] === 'dynamic') {
                $wpdb->update("{$prefix}cbt_student_tests", [
                    'last_activity' => $now_mysql
                ], ['id'=>$st->id]);
            }
        }

        // Kembalikan token sesi (simple)
        $session_token = wp_generate_password(32, false);
        set_transient('cbt_session_'.$session_token, [
            'student_id'       => $student->id,
            'student_test_id'  => $student_test_id,
            'test_id'          => $test->id
        ], 2 * HOUR_IN_SECONDS);

        return [
            'status'          => 'ok',
            'session_token'   => $session_token,
            'student_name'    => $student->nama,
            'test_name'       => $test->nama,
            'alokasi_menit'   => intval($test->alokasi_menit),
            'time_method'     => $opts['time_method']
        ];
    }

    private function require_session($request) {
        $session_token = $request->get_header('X-CBT-Session');
        if (!$session_token) $session_token = $request->get_param('session_token');
        $data = get_transient('cbt_session_'.$session_token);
        if (!$data) return new WP_Error('session_invalid','Sesi ujian tidak valid',['status'=>403]);
        return $data;
    }

    public function api_get_questions($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $sess = $this->require_session($request);
        if (is_wp_error($sess)) return $sess;

        $test_id = intval($sess['test_id']);

        // ambil soal + post content
        $rows = $wpdb->get_results($wpdb->prepare("
            SELECT q.id as question_id, q.no_soal, p.post_title, p.post_content, q.lock_soal
            FROM {$prefix}cbt_questions q
            LEFT JOIN {$prefix}posts p ON p.ID = q.post_id
            WHERE q.test_id=%d
            ORDER BY q.no_soal ASC
        ", $test_id), ARRAY_A);

        // TODO: terapkan shuffle sesuai setting test (Soal/Option)

        return ['status'=>'ok','questions'=>$rows];
    }

    public function api_save_answer($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $opts = get_option(self::OPTION_KEY);

        $sess = $this->require_session($request);
        if (is_wp_error($sess)) return $sess;

        $student_test_id = intval($sess['student_test_id']);
        $question_id     = intval($request['question_id']);
        $no_soal         = intval($request['no_soal']);
        $jawaban         = strtoupper(substr(sanitize_text_field($request['jawaban']),0,1));

        // ambil kunci & skor
        $q = $wpdb->get_row($wpdb->prepare("
            SELECT kunci_jawaban, skor FROM {$prefix}cbt_questions WHERE id=%d
        ", $question_id));

        if (!$q) return new WP_Error('not_found','Soal tidak ditemukan',['status'=>404]);

        $benar = ($jawaban === $q->kunci_jawaban) ? 1 : 0;
        $skor  = $benar ? floatval($q->skor) : 0;

        $now = current_time('mysql');
        $exists = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$prefix}cbt_answers
            WHERE student_test_id=%d AND question_id=%d
        ", $student_test_id,$question_id));

        $data = [
            'student_test_id' => $student_test_id,
            'question_id'     => $question_id,
            'no_soal'         => $no_soal,
            'jawaban'         => $jawaban,
            'benar'           => $benar,
            'skor'            => $skor,
            'last_update'     => $now
        ];

        if ($exists) {
            $wpdb->update("{$prefix}cbt_answers",$data,['id'=>$exists]);
        } else {
            $wpdb->insert("{$prefix}cbt_answers",$data);
        }

        // update progress + elapsed (Dynamic)
        if ($opts['time_method'] === 'dynamic') {
            $st = $wpdb->get_row($wpdb->prepare("
                SELECT last_activity, elapsed_seconds FROM {$prefix}cbt_student_tests WHERE id=%d
            ", $student_test_id));
            if ($st) {
                $last_ts = strtotime($st->last_activity);
                $now_ts  = current_time('timestamp');
                $add = max(0, $now_ts - $last_ts);
                $wpdb->update("{$prefix}cbt_student_tests", [
                    'elapsed_seconds' => $st->elapsed_seconds + $add,
                    'last_activity'   => $now
                ],['id'=>$student_test_id]);
            }
        }

        return ['status'=>'ok'];
    }

    public function api_finish_test($request) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $sess = $this->require_session($request);
        if (is_wp_error($sess)) return $sess;

        $student_test_id = intval($sess['student_test_id']);

        // hitung nilai
        $row = $wpdb->get_row($wpdb->prepare("
            SELECT SUM(skor) as total_skor,
                   SUM(benar) as total_benar,
                   COUNT(*)   as total_dikerjakan
            FROM {$prefix}cbt_answers
            WHERE student_test_id=%d
        ", $student_test_id));

        $total_skor = floatval($row->total_skor);
        $total_soal = max(1, intval($row->total_dikerjakan));
        $nilai = ($total_skor / $total_soal) * 100;

        $wpdb->update("{$prefix}cbt_student_tests", [
            'status' => 'finish',
            'nilai'  => $nilai,
            'benar'  => intval($row->total_benar),
            'salah'  => $total_soal - intval($row->total_benar)
        ], ['id'=>$student_test_id]);

        // hapus sesi
        $session_token = $request->get_header('X-CBT-Session');
        if ($session_token) {
            delete_transient('cbt_session_'.$session_token);
        }

        return ['status'=>'ok','nilai'=>$nilai];
    }
}

new WPCBT_UNBK();
