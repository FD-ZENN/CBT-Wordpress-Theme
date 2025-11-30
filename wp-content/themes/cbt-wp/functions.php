<?php
if (!defined('ABSPATH')) exit;

/**
 * =========================================================
 * 1. LOAD SCRIPT & CBT_GLOBAL (Tailwind, Axios, SweetAlert, cbt.js)
 * =========================================================
 */
add_action('wp_enqueue_scripts', function () {
    // Tailwind CDN
    wp_enqueue_script(
        'tailwindcdn',
        'https://cdn.tailwindcss.com',
        [],
        null,
        false
    );

    // Axios
    wp_enqueue_script(
        'axios',
        'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js',
        [],
        null,
        true
    );

    // SweetAlert2
    wp_enqueue_script(
        'sweetalert2',
        'https://cdn.jsdelivr.net/npm/sweetalert2@11',
        [],
        null,
        true
    );

    $cbt_js_path = get_template_directory() . '/assets/js/cbt.js';
$cbt_js_ver  = file_exists($cbt_js_path) ? filemtime($cbt_js_path) : time();

wp_enqueue_script(
    'cbt-js',
    get_template_directory_uri() . '/assets/js/cbt.js',
    ['axios', 'sweetalert2'],
    $cbt_js_ver,
    true
);

    // setelah enqueue cbt.js
    wp_enqueue_script(
        'cbt-security',
        get_template_directory_uri() . '/assets/js/cbt-security.js',
        array('sweetalert2'), // biar Swal sudah ada
        '1.0',
        true
    );


    // Data global CBT untuk JS
    $token_state = [
        'token'    => get_option('cbt_active_token', ''),
        'expires'  => (int) get_option('cbt_token_expires_at', 0),
        'duration' => (int) get_option('cbt_token_duration', 15),
    ];

    wp_localize_script('cbt-js', 'CBT_GLOBAL', [
        'rest_url'   => esc_url_raw(rest_url('cbt/v1/')),
        'nonce'      => wp_create_nonce('wp_rest'),
        'site_url'   => home_url('/'),
        'token'      => $token_state['token'],
        'token_exp'  => $token_state['expires'],
        'timezone'   => get_option('cbt_timezone', 'Asia/Jakarta'),
    ]);
});

/**
 * =========================================================
 * 2. FRONT PAGE → LOGIN CBT
 * =========================================================
 */
add_filter('template_include', function ($template) {
    if (is_front_page()) {
        $custom = get_template_directory() . '/front-page.php';
        if (file_exists($custom)) return $custom;
    }
    return $template;
});

/**
 * =========================================================
 * 3. PAGE TEMPLATE UNTUK HALAMAN UJIAN CBT (page-cbt.php)
 * =========================================================
 */
add_filter('theme_page_templates', function ($templates) {
    $templates['page-cbt.php'] = 'Halaman Ujian CBT';
    return $templates;
});

add_filter('template_include', function ($template) {
    if (is_page()) {
        $t = get_page_template_slug(get_queried_object_id());
        if ($t === 'page-cbt.php') {
            $custom = get_template_directory() . '/page-cbt.php';
            if (file_exists($custom)) return $custom;
        }
    }
    return $template;
});

/**
 * =========================================================
 * 4. CREATE TABLE CBT (tests, students, sessions, answers)
 * =========================================================
 */
function cbt_bima_create_tables()
{
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset_collate = $wpdb->get_charset_collate();

    $table_tests    = $wpdb->prefix . 'cbt_tests';
    $table_students = $wpdb->prefix . 'cbt_students';
    $table_sessions = $wpdb->prefix . 'cbt_sessions';
    $table_answers  = $wpdb->prefix . 'cbt_answers';
    $table_keys     = $wpdb->prefix . 'cbt_keys';

    $sql_tests = "CREATE TABLE $table_tests (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        status VARCHAR(20) DEFAULT 'draft',
        subtest VARCHAR(100) DEFAULT '',
        test_date DATE NULL,
        start_time TIME NULL,
        duration_minutes INT NOT NULL DEFAULT 90,
        num_questions INT NOT NULL DEFAULT 0,
        must_answer INT NOT NULL DEFAULT 0,
        shuffle_questions TINYINT(1) DEFAULT 1,
        shuffle_options TINYINT(1) DEFAULT 1,
        min_submit_minutes INT NOT NULL DEFAULT 0,
        time_method VARCHAR(20) DEFAULT 'classic',  -- classic | dynamic
        save_method VARCHAR(20) DEFAULT 'realtime', -- realtime
        config_json LONGTEXT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (status),
        INDEX (test_date),
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_students = "CREATE TABLE $table_students (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        nis VARCHAR(50) DEFAULT '',
        username VARCHAR(100) NOT NULL UNIQUE,
        pass_hash VARCHAR(255) NOT NULL,
        full_name VARCHAR(255) NOT NULL,
        ket1 VARCHAR(255) DEFAULT '',
        ket2 VARCHAR(255) DEFAULT '',
        ket3 VARCHAR(255) DEFAULT '',
        mapel VARCHAR(100) DEFAULT '',
        server VARCHAR(100) DEFAULT '',
        session_label VARCHAR(50) DEFAULT '',
        status VARCHAR(20) DEFAULT 'active', -- active | blocked
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    $sql_sessions = "CREATE TABLE $table_sessions (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        student_id BIGINT UNSIGNED NOT NULL,
        test_id BIGINT UNSIGNED NOT NULL,
        token_used VARCHAR(20) DEFAULT '',
        start_time DATETIME NULL,
        end_time DATETIME NULL,
        status VARCHAR(20) DEFAULT 'in_progress', -- in_progress | finished | blocked
        last_question INT DEFAULT 1,
        last_activity DATETIME DEFAULT CURRENT_TIMESTAMP,
        score DECIMAL(6,2) DEFAULT 0,
        detail_json LONGTEXT NULL,
        PRIMARY KEY (id),
        INDEX (student_id),
        INDEX (test_id),
        INDEX (status)
    ) $charset_collate;";

    $sql_answers = "CREATE TABLE $table_answers (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        session_id BIGINT UNSIGNED NOT NULL,
        question_post_id BIGINT UNSIGNED NOT NULL,
        question_no INT NOT NULL,
        answer CHAR(1) DEFAULT NULL,
        is_correct TINYINT(1) DEFAULT 0,
        score DECIMAL(6,2) DEFAULT 0,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX (session_id),
        INDEX (question_post_id),
        INDEX (question_no)
    ) $charset_collate;";

    // 🔐 Tabel kunci jawaban (TIDAK pernah dikirim ke browser)
    $sql_keys = "CREATE TABLE $table_keys (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        test_id BIGINT UNSIGNED NOT NULL,
        question_no INT NOT NULL,
        answer_key CHAR(1) NOT NULL,
        score DECIMAL(6,2) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY uq_key (test_id, question_no),
        INDEX (test_id),
        INDEX (question_no)
    ) $charset_collate;";

    dbDelta($sql_tests);
    dbDelta($sql_students);
    dbDelta($sql_sessions);
    dbDelta($sql_answers);
    dbDelta($sql_keys);

    // Beberapa default penting (boleh kamu sesuaikan)
    if (!get_option('cbt_token_duration')) {
        update_option('cbt_token_duration', 15); // menit
    }
    if (!get_option('cbt_excel_key')) {
        update_option('cbt_excel_key', '123');
    }
    if (!get_option('cbt_timezone')) {
        update_option('cbt_timezone', 'Asia/Jakarta');
    }
}

add_action('after_switch_theme', 'cbt_bima_create_tables');

/**
 * Ambil innerHTML sebuah DOMNode (biar gambar & HTML ikut ke CBT)
 */
function cbt_bima_inner_html(DOMNode $node) {
    $html = '';
    foreach ($node->childNodes as $child) {
        $html .= $node->ownerDocument->saveHTML($child);
    }
    return $html;
}

/**
 * Parse tabel Word → array soal:
 *
 * MAPEL | MTK
 * No    | 1
 * Soal  | ...
 * A     | ...
 * B     | ...
 * C     | ...
 * D     | ...
 * E     | ...
 * No    | 2
 * ...
 *
 * return: [
 *   [
 *     'number'  => 1,
 *     'mapel'   => 'MTK',
 *     'content' => '<p>Soal...</p><p><img ...></p>',
 *     'options' => ['A'=>..., 'B'=>..., ...],
 *   ],
 *   ...
 * ]
 */
function cbt_bima_parse_questions_from_post($post_id) {
    $content = get_post_field('post_content', $post_id);
    if (!$content) {
        return [];
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
    libxml_clear_errors();

    $tables = $doc->getElementsByTagName('table');
    if (!$tables->length) {
        return [];
    }

    // untuk sederhana, pakai tabel pertama
    $table = $tables->item(0);
    $rows  = $table->getElementsByTagName('tr');

    $questions = [];
    $current   = null;
    $mapel     = '';

    foreach ($rows as $tr) {
        $cells = $tr->getElementsByTagName('td');
        if ($cells->length < 2) {
            continue;
        }

        $labelNode = $cells->item(0);
        $valueNode = $cells->item(1);

        $labelText = trim(mb_strtoupper($labelNode->textContent, 'UTF-8'));
        $valueHtml = trim(cbt_bima_inner_html($valueNode));
        $valueText = trim($valueNode->textContent);

        switch ($labelText) {
            case 'MAPEL':
                $mapel = $valueText;
                break;

            case 'NO':
                // simpan soal sebelumnya
                if ($current && !empty($current['soal'])) {
                    $questions[] = $current;
                }
                $current = [
                    'number' => (int) $valueText,
                    'soal'   => '',
                    'A'      => '',
                    'B'      => '',
                    'C'      => '',
                    'D'      => '',
                    'E'      => '',
                    'mapel'  => $mapel,
                ];
                break;

            case 'SOAL':
                if ($current) {
                    $current['soal'] = $valueHtml ?: $valueText;
                }
                break;

            case 'A':
            case 'B':
            case 'C':
            case 'D':
            case 'E':
                if ($current) {
                    $current[$labelText] = $valueHtml ?: $valueText;
                }
                break;
        }
    }

    // push soal terakhir
    if ($current && !empty($current['soal'])) {
        $questions[] = $current;
    }

    $result = [];
    $i = 1;
    foreach ($questions as $q) {
        $num = $q['number'] ?: $i;
        $result[] = [
            'number'  => $num,
            'mapel'   => $q['mapel'],
            'content' => $q['soal'],
            'options' => [
                'A' => $q['A'],
                'B' => $q['B'],
                'C' => $q['C'],
                'D' => $q['D'],
                'E' => $q['E'],
            ],
        ];
        $i++;
    }

    return $result;
}


/**
 * =========================================================
 * 5. TOKEN: GENERATE & STATE
 * =========================================================
 */

// Generate token baru (6 digit)
function cbt_bima_generate_token()
{
    $duration = (int) get_option('cbt_token_duration', 15);
    $now      = time();
    $token    = wp_rand(100000, 999999);

    update_option('cbt_active_token', $token);
    update_option('cbt_token_generated_at', $now);
    update_option('cbt_token_expires_at', $now + ($duration * 60));

    return $token;
}

// Ambil state token saat ini
function cbt_bima_get_token_state()
{
    $duration = (int) get_option('cbt_token_duration', 15);
    return [
        'token'    => get_option('cbt_active_token', ''),
        'expires'  => (int) get_option('cbt_token_expires_at', 0),
        'duration' => $duration,
    ];
}

// Quick regen via URL: ?cbt_regen_token=yes
add_action('init', function () {
    if (isset($_GET['cbt_regen_token']) && $_GET['cbt_regen_token'] === 'yes') {
        if (is_user_logged_in() && current_user_can('manage_options')) {
            $state = cbt_bima_generate_token();
            $now   = time();
            $exp   = (int) get_option('cbt_token_expires_at', 0);

            wp_die(
                'Token baru: <strong>' . esc_html($state) . '</strong><br>Expired: ' .
                esc_html(date_i18n('d-m-Y H:i:s', $exp ?: $now))
            );
        } else {
            wp_die('Unauthorized');
        }
    }
});
/**
 * Cek state token, kalau kosong / kadaluarsa dan auto_token = ON
 * maka generate token baru.
 *
 * @return array { token, expires, duration }
 */
if (!function_exists('cbt_bima_maybe_rotate_token')) {
    function cbt_bima_maybe_rotate_token() {
        $opts  = cbt_bima_get_options();
        $state = cbt_bima_get_token_state();
        $now   = time();

        // Kalau belum ada token sama sekali → wajib buat
        if (empty($state['token'])) {
            if (!empty($opts['auto_token'])) {
                $state = cbt_bima_generate_token();
            }
            return $state;
        }

        // Kalau sudah expired
        if ($state['expires'] > 0 && $state['expires'] <= $now) {
            if (!empty($opts['auto_token'])) {
                // Auto regenerate
                $state = cbt_bima_generate_token();
            } else {
                // Auto token OFF → biarkan saja expired
                // (bisa kamu tambahkan log / notifikasi kalau mau)
            }
        }

        return $state;
    }
}
/**
 * Setiap request, kalau auto_token ON, cek & putar token kalau sudah habis.
 */
add_action('init', function () {
    $opts = cbt_bima_get_options();
    if (empty($opts['auto_token'])) {
        return;
    }

    // Ini cukup panggil helper, dia yang akan muter kalau perlu
    cbt_bima_maybe_rotate_token();
});


/**
 * =========================================================
 * 6. HELPER: AMBIL ROW TEST BY CODE
 * =========================================================
 */
function cbt_bima_get_test_by_code($code)
{
    global $wpdb;
    $table = $wpdb->prefix . 'cbt_tests';
    return $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table WHERE code = %s LIMIT 1", $code),
        ARRAY_A
    );
}

/**
 * =========================================================
 * 7. REST API: TOKEN, LOGIN, EXAM, ANSWER, FINISH, EXCEL
 * =========================================================
 */
add_action('rest_api_init', function () {

    $namespace = 'cbt/v1';

    // === TOKEN AKTIF ===
    register_rest_route($namespace, '/token', [
        'methods'  => 'GET',
        'callback' => function () {
            $state = cbt_bima_maybe_rotate_token();

            return [
                'token'    => $state['token'],
                'expires'  => $state['expires'],
                'duration' => $state['duration'],
            ];
        },
        'permission_callback' => '__return_true',
    ]);

    // === LOGIN SISWA ===
    register_rest_route($namespace, '/login', [
        'methods'  => 'POST',
        'callback' => 'cbt_bima_login_student',
        'permission_callback' => '__return_true',
    ]);

    // === DATA UJIAN + SOAL ===
    register_rest_route($namespace, '/exam', [
        'methods'  => 'GET',
        'callback' => 'cbt_bima_get_exam',
        'permission_callback' => '__return_true',
    ]);

    // === SIMPAN JAWABAN ===
    register_rest_route($namespace, '/answer', [
        'methods'  => 'POST',
        'callback' => 'cbt_bima_save_answer',
        'permission_callback' => '__return_true',
    ]);

    // === SELESAI UJIAN ===
    register_rest_route($namespace, '/finish', [
        'methods'  => 'POST',
        'callback' => 'cbt_bima_finish_exam',
        'permission_callback' => '__return_true',
    ]);

    // === EXCEL SAVE ===
    register_rest_route($namespace, '/excel/save', [
        'methods'  => 'POST',
        'callback' => 'cbt_bima_excel_save',
        'permission_callback' => '__return_true',
    ]);

    // === EXCEL RESULTS ===
    register_rest_route($namespace, '/excel/results', [
        'methods'  => 'GET',
        'callback' => 'cbt_bima_excel_results',
        'permission_callback' => '__return_true',
    ]);

    // === DAFTAR MAPEL / TES AKTIF (UNTUK DROPDOWN LOGIN) ===
    register_rest_route($namespace, '/tests', [
        'methods'  => 'GET',
        'callback' => 'cbt_bima_get_tests',
        'permission_callback' => '__return_true',
    ]);
    register_rest_route('cbt/v1', '/students_bulk', [
    'methods'  => 'POST',
    'callback' => 'cbt_import_students_bulk',
    'permission_callback' => '__return_true', // atau cek excel_key
]);
        // === REGISTER SISWA (SELF REGISTRATION) ===
    register_rest_route($namespace, '/register', [
        'methods'  => 'POST',
        'callback' => 'cbt_bima_register_student',
        'permission_callback' => '__return_true',
    
    
]);

function cbt_import_students_bulk( WP_REST_Request $request ) {
    $params   = $request->get_json_params();
    $excelKey = $params['excel_key'] ?? '';
    $testCode = $params['test_code'] ?? '';
    $students = $params['students'] ?? [];

    // validasi excelKey / testCode...

    global $wpdb;
    $table = $wpdb->prefix . 'cbt_students';

    $inserted = 0;

    foreach ($students as $s) {
        $data = [
            'test_code'   => $testCode,
            'no'          => intval($s['no'] ?? 0),
            'nis'         => $s['nis'] ?? '',
            'student_name'=> $s['name'] ?? '',
            'student_class'=> $s['class'] ?? '',
            'password'    => $s['password'] ?? '',
        ];

        $ok = $wpdb->insert($table, $data);
        if ($ok !== false) {
            $inserted++;
        }
    }

    return [
        'status'      => 'ok',
        'inserted'    => $inserted,
        'students_cnt'=> count($students),
    ];
}

});

/**
 * =========================================================
 * 7.1 LOGIN SISWA
 * =========================================================
 * Body:
 * {
 *   "username": "siswa1",
 *   "password": "xxx",
 *   "mapel": "MTK",
 *   "token": "123456",
 *   "test_code": "MTK-01"
 * }
 */

function cbt_bima_list_tests(WP_REST_Request $req) {
    global $wpdb;

    $opts = function_exists('cbt_bima_get_options') ? cbt_bima_get_options() : [];
    $show_all = !empty($opts['show_all_subjects']);

    $table_tests = $wpdb->prefix . 'cbt_tests';
    $today = current_time('Y-m-d');

    $rows = $wpdb->get_results("SELECT * FROM $table_tests WHERE status = 'active' ORDER BY test_date, start_time", ARRAY_A);

    $result = [];
    foreach ($rows as $r) {
        // filter tanggal: kalau ada test_date dan show_all_subjects = 0 → hanya hari ini
        if (!$show_all && !empty($r['test_date']) && $r['test_date'] !== $today) {
            continue;
        }

        $label = $r['name']; // cukup nama saja, biar nggak kepanjangan
        $result[] = [
            'id'          => (int) $r['id'],
            'code'        => $r['code'],
            'name'        => $r['name'],
            'label'       => $label,
            'test_date'   => $r['test_date'],
            'start_time'  => $r['start_time'],
            'duration'    => (int) $r['duration_minutes'],
        ];
    }

    return $result;
}


function cbt_bima_login_student(WP_REST_Request $req)
{
    global $wpdb;
    $body = $req->get_json_params();

    $username  = isset($body['username']) ? trim($body['username']) : '';
    $password  = isset($body['password']) ? $body['password'] : '';
    $mapel     = isset($body['mapel']) ? trim($body['mapel']) : '';
    $token     = isset($body['token']) ? trim($body['token']) : '';
    $test_code = isset($body['test_code']) ? trim($body['test_code']) : '';

    if ($username === '' || $password === '' || $token === '' || $test_code === '') {
        return new WP_Error('cbt_login_invalid', 'Data login tidak lengkap.', ['status' => 400]);
    }

    // Cek token (tanpa expiry, simple)
    // Ambil token state (TIDAK auto-rotate di sini, supaya konsisten dengan layar pengawas)
$state        = cbt_bima_get_token_state();
$active_token = $state['token'];
$expires      = (int) $state['expires'];
$now          = time();

if (empty($active_token) || $expires <= $now) {
    return new WP_Error(
        'cbt_token_expired',
        'Token sudah kadaluarsa. Silakan gunakan token terbaru.',
        ['status' => 403]
    );
}

if ($token !== $active_token) {
    return new WP_Error(
        'cbt_token_invalid',
        'Token salah. Silakan cek token di layar pengawas.',
        ['status' => 403]
    );
}


    // Cek siswa
    $table_students = $wpdb->prefix . 'cbt_students';
    $student = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_students WHERE username = %s LIMIT 1", $username),
        ARRAY_A
    );
    if (!$student) {
        return new WP_Error('cbt_student_not_found', 'Peserta tidak terdaftar.', ['status' => 404]);
    }

    if ($student['status'] === 'blocked') {
        return new WP_Error('cbt_student_blocked', 'Akun Anda diblokir. Hubungi pengawas.', ['status' => 403]);
    }

    // Cek password (hash atau plain)
    $pass_ok = false;
    if (!empty($student['pass_hash'])) {
        if (password_verify($password, $student['pass_hash'])) {
            $pass_ok = true;
        } elseif ($password === $student['pass_hash']) {
            $pass_ok = true;
        }
    }
    if (!$pass_ok) {
        return new WP_Error('cbt_login_failed', 'Username atau password salah.', ['status' => 401]);
    }

    // Cek test
    $test = cbt_bima_get_test_by_code($test_code);
    if (!$test || $test['status'] !== 'active') {
        return new WP_Error('cbt_test_not_active', 'Tes tidak ditemukan atau belum aktif.', ['status' => 404]);
    }

    $table_sessions = $wpdb->prefix . 'cbt_sessions';

    // Kalau sudah pernah selesai → tolak login ulang
    $finished_session = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_sessions 
             WHERE student_id = %d AND test_id = %d AND status = 'finished'
             ORDER BY id DESC LIMIT 1",
            $student['id'],
            $test['id']
        ),
        ARRAY_A
    );
    if ($finished_session) {
        return new WP_Error(
            'cbt_already_finished',
            'Anda sudah menyelesaikan ujian ini. Tidak dapat login lagi.',
            ['status' => 403]
        );
    }

    // Cari sesi in_progress → lanjutkan
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

    $now = current_time('mysql');

    if (!$session) {
        // Buat sesi baru
        $wpdb->insert($table_sessions, [
            'student_id'   => $student['id'],
            'test_id'      => $test['id'],
            'token_used'   => $token,
            'start_time'   => $now,
            'last_activity'=> $now,
            'status'       => 'in_progress',
            'last_question'=> 1,
        ]);
        $session_id = $wpdb->insert_id;
        $session = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_sessions WHERE id = %d", $session_id),
            ARRAY_A
        );
    }

        // Hitung end_time (classic/dynamic)
    $duration    = (int) $test['duration_minutes'];
    $time_method = $test['time_method'];

    if ($time_method === 'classic' && !empty($test['test_date']) && !empty($test['start_time'])) {
        $test_start = $test['test_date'] . ' ' . $test['start_time'];
        $end_ts     = strtotime($test_start . ' + ' . $duration . ' minutes');
    } else {
        $end_ts     = strtotime($session['start_time'] . ' + ' . $duration . ' minutes');
    }

    // 🔑 SESSION KEY – kunci rahasia per sesi
    $table_sessions = $wpdb->prefix . 'cbt_sessions';
    $session_key    = isset($session['session_key']) ? $session['session_key'] : '';

    if (empty($session_key)) {
        $session_key = wp_generate_password(24, false, false);
        $wpdb->update(
            $table_sessions,
            ['session_key' => $session_key],
            ['id' => $session['id']]
        );
    }

    // SLUG SESSION UNTUK URL: kodeTest-username
    $session_slug = sanitize_title($test['code']) . '-' . sanitize_title($student['username']);

    return [
        'session_id'       => (int) $session['id'],
        'session_slug'     => $session_slug,
        'session_auth'     => $session_key,          // ⬅️ KIRIM KE JS
        'student_name'     => $student['full_name'],
        'test_name'        => $test['name'],
        'test_code'        => $test['code'],
        'duration_minutes' => $duration,
        'time_method'      => $time_method,
        'end_timestamp'    => $end_ts * 1000,
        'last_question'    => (int) $session['last_question'],
    ];
}

/**
 * REST: Pendaftaran siswa mandiri
 * POST /wp-json/cbt/v1/register
 *
 * Body JSON:
 * {
 *   "username": "siswa1",
 *   "password": "123456",
 *   "full_name": "Nama Lengkap",
 *   "nis": "12345",
 *   "mapel": "MTK-01",   // test_code
 *   "ket1": "",
 *   "ket2": "",
 *   "ket3": "",
 *   "sesi": "1"
 * }
 */
function cbt_bima_register_student( WP_REST_Request $req ) {
    global $wpdb;

    // --- BACA BODY JSON ---
    $body = $req->get_json_params();

    // Bisa kamu buka di debug.log kalau mau cek apa yang diterima:
    // error_log('CBT REGISTER BODY: ' . print_r($body, true));

    // Alias field dari JS:
    $username = isset($body['username']) ? trim($body['username']) : '';
    $password = isset($body['password']) ? (string) $body['password'] : '';

    // full_name / nama
    $full_name = '';
    if (!empty($body['full_name'])) {
        $full_name = trim($body['full_name']);
    } elseif (!empty($body['nama'])) {
        $full_name = trim($body['nama']);
    }

    // mapel_code / mapel
    $mapel_code = '';
    if (!empty($body['mapel_code'])) {
        $mapel_code = trim($body['mapel_code']);
    } elseif (!empty($body['mapel'])) {
        $mapel_code = trim($body['mapel']);
    }

    // field tambahan
    $nis   = isset($body['nis'])   ? trim($body['nis'])   : '';
    $ket1  = isset($body['ket1'])  ? trim($body['ket1'])  : '';
    $ket2  = isset($body['ket2'])  ? trim($body['ket2'])  : '';
    $ket3  = isset($body['ket3'])  ? trim($body['ket3'])  : '';
    $sesi  = isset($body['sesi'])  ? trim($body['sesi'])  : '';
    $server = isset($body['server']) ? trim($body['server']) : '';

    // --- CEK GLOBAL OPSI: apakah self-registration diizinkan? ---
    $opts = cbt_bima_get_options();
    if ( empty($opts['allow_self_registration']) ) {
        return new WP_Error(
            'cbt_reg_disabled',
            'Pendaftaran mandiri dinonaktifkan. Hubungi admin.',
            ['status' => 403]
        );
    }

    // --- VALIDASI WAJIB ---
    if ( $username === '' || $password === '' || $full_name === '' || $mapel_code === '' ) {
        return new WP_Error(
            'cbt_reg_incomplete',
            'Data pendaftaran tidak lengkap. Username, password, nama, dan mapel wajib diisi.',
            ['status' => 400]
        );
    }

    // --- CEK USERNAME SUDAH ADA BELUM ---
    $table_students = $wpdb->prefix . 'cbt_students';
    $existing = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT id FROM $table_students WHERE username = %s LIMIT 1",
            $username
        ),
        ARRAY_A
    );

    if ( $existing ) {
        return new WP_Error(
            'cbt_reg_exists',
            'Username sudah terdaftar. Silakan gunakan username lain.',
            ['status' => 409]
        );
    }

    // --- INSERT PESERTA BARU ---
    $pass_hash = password_hash( $password, PASSWORD_DEFAULT );

    $insert_data = [
        'nis'           => $nis,
        'username'      => $username,
        'pass_hash'     => $pass_hash,
        'full_name'     => $full_name,
        'ket1'          => $ket1,
        'ket2'          => $ket2,
        'ket3'          => $ket3,
        'mapel'         => $mapel_code, // otomatis dari pilihan mapel
        'server'        => $server,
        'session_label' => $sesi,
        'status'        => 'active',
        'created_at'    => current_time( 'mysql' ),
    ];

    $ok = $wpdb->insert( $table_students, $insert_data );

    if ( false === $ok ) {
        return new WP_Error(
            'cbt_reg_db_error',
            'Gagal menyimpan data peserta: ' . $wpdb->last_error,
            ['status' => 500]
        );
    }

    return [
        'status'  => 'ok',
        'message' => 'Pendaftaran berhasil. Silakan login dengan username & password yang baru dibuat.',
        'student' => [
            'id'        => (int) $wpdb->insert_id,
            'username'  => $username,
            'full_name' => $full_name,
            'mapel'     => $mapel_code,
            'nis'       => $nis,
            'ket1'      => $ket1,
            'ket2'      => $ket2,
            'ket3'      => $ket3,
            'sesi'      => $sesi,
        ],
    ];
}



/**
 * =========================================================
 * 7.2 AMBIL DATA UJIAN + SOAL (BY SESSION_ID)
 * =========================================================
 */
function cbt_bima_get_exam(WP_REST_Request $req)
{
    global $wpdb;
    $session_id = (int) $req->get_param('session_id');
    if ($session_id <= 0) {
        return new WP_Error('cbt_exam_invalid', 'Session ID tidak valid.', ['status' => 400]);
    }

    $table_sessions = $wpdb->prefix . 'cbt_sessions';
    $session = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_sessions WHERE id = %d LIMIT 1", $session_id),
        ARRAY_A
    );
    if (!$session) {
        return new WP_Error('cbt_session_not_found', 'Sesi ujian tidak ditemukan.', ['status' => 404]);
    }
    if ($session['status'] !== 'in_progress') {
        return new WP_Error('cbt_session_not_active', 'Sesi ujian sudah selesai / tidak aktif.', ['status' => 403]);
    }

    $table_tests = $wpdb->prefix . 'cbt_tests';
    $test = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_tests WHERE id = %d LIMIT 1", $session['test_id']),
        ARRAY_A
    );
    if (!$test) {
        return new WP_Error('cbt_test_not_found', 'Tes tidak ditemukan.', ['status' => 404]);
    }

   // Ambil post-post soal untuk test ini
$mapel_slug = sanitize_title( $test['code'] ); // contoh: "MTK-10" -> "mtk-10"

// 1) Prioritas: pakai meta _cbt_test_code (di-set dari Excel)
$q = new WP_Query([
    'post_type'      => 'post',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'   => '_cbt_test_code',
            'value' => $test['code'],   // "MTK-10", "MTK-01", dll
        ]
    ],
    'orderby'   => 'ID',
    'order'     => 'ASC',
]);

// 2) Fallback: kalau belum ada post yang punya meta itu,
//    coba cari berdasarkan slug sama dengan kode test (mtk-10)
if ( ! $q->have_posts() ) {
    wp_reset_postdata();

    $q = new WP_Query([
        'post_type'      => 'post',
        'posts_per_page' => -1,
        'name'           => $mapel_slug, // slug post = "mtk-10"
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ]);
}


    $questions = [];
    if ($q->have_posts()) {
        while ($q->have_posts()) {
            $q->the_post();
            $post_id = get_the_ID();

            // Parse banyak soal dari 1 post (tabel MAPEL/No/Soal/A..E)
            $parsed = cbt_bima_parse_questions_from_post($post_id);
            foreach ($parsed as $pq) {
                $questions[] = [
                    'post_id'  => $post_id,
                    'number'   => (int) $pq['number'],  // ini yang harus sama dengan "No" di Word & di Excel
                    'content'  => $pq['content'],
                    'options'  => $pq['options'],
                    'score'    => 1,   // skor akan diambil dari cbt_keys saat hitung nilai; di sini boleh 1 saja
                ];
            }
        }
        wp_reset_postdata();
    }

    if (empty($questions)) {
        return new WP_Error('cbt_no_questions', 'Belum ada soal untuk tes ini.', ['status' => 404]);
    }

    // Shuffle kalau diaktifkan
    if (!empty($test['shuffle_questions'])) {
        shuffle($questions);
    }

    // Display number 1..N, tapi 'number' tetap nomor asli (buat kunci Excel)
    $i = 1;
    foreach ($questions as &$qq) {
        $qq['display_no'] = $i++;
    }
    unset($qq);

    // Ambil jawaban yang sudah ada
    $table_answers = $wpdb->prefix . 'cbt_answers';
    $answers = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_answers WHERE session_id = %d", $session_id),
        ARRAY_A
    );
    $answer_map = [];
    foreach ($answers as $a) {
        // kunci: question_no (nomor asli dari Excel/Word)
        $answer_map[(int)$a['question_no']] = $a['answer'];
    }

    foreach ($questions as &$qq) {
        $qn = (int)$qq['number'];
        $qq['current_answer'] = isset($answer_map[$qn]) ? $answer_map[$qn] : null;
    }
    unset($qq);

    return [
        'test_name'     => $test['name'],
        'test_code'     => $test['code'],
        'session_id'    => (int) $session['id'],
        'questions'     => $questions,
        'last_question' => (int) $session['last_question'],
    ];
}



/**
 * Daftar mapel / test aktif
 * GET /cbt/v1/tests
 *
 * Menghormati opsi:
 *  - show_all_subjects  = 1 → tampilkan semua mapel aktif
 *  - show_all_subjects  = 0 → hanya mapel dengan test_date = hari ini
 */
function cbt_bima_get_tests( WP_REST_Request $req ) {
    global $wpdb;

    $opts        = cbt_bima_get_options();
    $table_tests = $wpdb->prefix . 'cbt_tests';

    $today  = current_time('Y-m-d');
    $now_ts = current_time('timestamp');

    // Base query: hanya status active
    $sql_base = "
        SELECT id, code, name, subtest, test_date, start_time, duration_minutes
        FROM $table_tests
        WHERE status = 'active'
    ";

    $rows = [];

    // =============== MODE: TIDAK "Tampilkan Semua Mapel" ===============
    if ( empty( $opts['show_all_subjects'] ) ) {

        // 1) Coba dulu filter test_date = hari ini
        $sql_today = $sql_base
            . $wpdb->prepare( " AND (test_date = %s)", $today )
            . " ORDER BY test_date, start_time, code";

        $rows = $wpdb->get_results( $sql_today, ARRAY_A );

        // 2) Kalau hasilnya kosong -> fallback: semua active (tanpa filter tanggal)
        if ( empty( $rows ) ) {
            $sql_all = $sql_base . " ORDER BY test_date, start_time, code";
            $rows    = $wpdb->get_results( $sql_all, ARRAY_A );
        }

    // =============== MODE: "Tampilkan Semua Mapel" ===============
    } else {
        $sql_all = $sql_base . " ORDER BY test_date, start_time, code";
        $rows    = $wpdb->get_results( $sql_all, ARRAY_A );
    }

    $tests = [];

    foreach ( (array) $rows as $r ) {
        $start_ts  = 0;
        $end_ts    = 0;
        $available = 1; // default: kalau tidak ada jadwal anggap available

        if ( ! empty( $r['test_date'] ) && ! empty( $r['start_time'] ) ) {
            $start_ts  = strtotime( $r['test_date'] . ' ' . $r['start_time'] );
            $end_ts    = $start_ts + ( (int) $r['duration_minutes'] * 60 );
            $available = ( $now_ts >= $start_ts && $now_ts <= $end_ts ) ? 1 : 0;
        }

        $tests[] = [
            'id'               => (int) $r['id'],
            'code'             => $r['code'],
            'name'             => $r['name'],
            'subtest'          => $r['subtest'],
            'test_date'        => $r['test_date'],
            'start_time'       => $r['start_time'],
            'duration_minutes' => (int) $r['duration_minutes'],
            'available_now'    => $available,
            'is_today'         => ($r['test_date'] === $today ? 1 : 0),
        ];
    }

    return [
        'hide_subject_choice' => ! empty( $opts['hide_subject_choice'] ) ? 1 : 0,
        'show_all_subjects'   => ! empty( $opts['show_all_subjects'] ) ? 1 : 0,
        'tests'               => $tests,
    ];
}


/**
 * =========================================================
 * 7.3 SIMPAN JAWABAN (REALTIME)
 * =========================================================
 */
function cbt_bima_save_answer(WP_REST_Request $req)
{
    global $wpdb;

    $body = $req->get_json_params();

    $session_id       = (int) ($body['session_id']      ?? 0);
    $session_auth_in  =       ($body['session_auth']    ?? '');
    $question_post_id = (int) ($body['question_post_id'] ?? 0);
    $question_no      = (int) ($body['question_no']     ?? 0);
    $answer           = isset($body['answer']) ? strtoupper(substr($body['answer'], 0, 1)) : '';

    if ($session_id <= 0 || $question_post_id <= 0 || $question_no <= 0 || !in_array($answer, ['A','B','C','D','E'], true)) {
        return new WP_Error('cbt_answer_invalid', 'Data jawaban tidak lengkap.', ['status' => 400]);
    }

    // ================== CEK SESI + SESSION_AUTH ==================
    $table_sessions = $wpdb->prefix . 'cbt_sessions';
    $session = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_sessions WHERE id = %d LIMIT 1", $session_id),
        ARRAY_A
    );

    if (!$session) {
        return new WP_Error('cbt_session_not_found', 'Sesi ujian tidak ditemukan.', ['status' => 404]);
    }

    if ($session['status'] !== 'in_progress') {
        return new WP_Error('cbt_session_not_active', 'Sesi ujian sudah selesai / tidak aktif.', ['status' => 403]);
    }

    // Baca session_auth dari detail_json
    $stored_auth = '';
    if (!empty($session['detail_json'])) {
        $detail = json_decode($session['detail_json'], true);
        if (is_array($detail) && !empty($detail['session_auth'])) {
            $stored_auth = (string) $detail['session_auth'];
        }
    }

    // Kalau sudah pakai mekanisme session_auth → wajib cocok
    if ($stored_auth !== '' && $session_auth_in !== $stored_auth) {
        return new WP_Error(
            'cbt_session_invalid',
            'Sesi ujian tidak valid atau sudah berakhir. Silakan login ulang.',
            ['status' => 403]
        );
    }

    // ================== AMBIL KUNCI DARI wpx_cbt_keys ==================
    $table_keys = $wpdb->prefix . 'cbt_keys';

    $key_row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT answer_key, score 
             FROM $table_keys 
             WHERE test_id = %d AND question_no = %d 
             LIMIT 1",
            $session['test_id'],
            $question_no
        ),
        ARRAY_A
    );

    $answer_key = null;
    $score      = 0.0;
    $is_correct = 0;

    if ($key_row) {
        $answer_key = strtoupper(trim($key_row['answer_key']));
        $full_score = (float) $key_row['score'];

        if ($answer !== '' && $answer_key !== '' && $answer === $answer_key) {
            $is_correct = 1;
            $score      = $full_score;
        }
    } else {
        // Tidak ada kunci di tabel → tetap simpan jawaban, tapi nilai 0
        $answer_key = null;
        $score      = 0.0;
        $is_correct = 0;
    }

    // ================== UPSERT KE TABEL JAWABAN ==================
    $table_answers = $wpdb->prefix . 'cbt_answers';

    $exist = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $table_answers 
             WHERE session_id = %d AND question_no = %d 
             LIMIT 1",
            $session_id,
            $question_no
        ),
        ARRAY_A
    );

    if ($exist) {
        $wpdb->update(
            $table_answers,
            [
                'question_post_id' => $question_post_id,
                'answer'           => $answer,
                'is_correct'       => $is_correct,
                'score'            => $score,
            ],
            ['id' => $exist['id']]
        );
    } else {
        $wpdb->insert(
            $table_answers,
            [
                'session_id'       => $session_id,
                'question_post_id' => $question_post_id,
                'question_no'      => $question_no,
                'answer'           => $answer,
                'is_correct'       => $is_correct,
                'score'            => $score,
            ]
        );
    }

    // Update last_activity & last_question
    $wpdb->update(
        $table_sessions,
        [
            'last_activity' => current_time('mysql'),
            'last_question' => $question_no,
        ],
        ['id' => $session_id]
    );

    return [
        'status'     => 'ok',
        'correct'    => (bool) $is_correct,
        'score'      => $score,
        'answer_key' => $answer_key, // opsional, JANGAN ditampilkan ke siswa di front-end
    ];
}



/**
 * =========================================================
 * 7.4 SELESAI UJIAN (PAKAI PENGATURAN)
 * =========================================================
 */
function cbt_bima_finish_exam(WP_REST_Request $req)
{
    global $wpdb;
    $body = $req->get_json_params();
    $session_id = (int) ($body['session_id'] ?? 0);

    if ($session_id <= 0) {
        return new WP_Error('cbt_finish_invalid', 'Session ID tidak valid.', ['status' => 400]);
    }

    $opts = cbt_bima_get_options();

    $table_sessions = $wpdb->prefix . 'cbt_sessions';
    $table_answers  = $wpdb->prefix . 'cbt_answers';
    $table_tests    = $wpdb->prefix . 'cbt_tests';

    $session = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_sessions WHERE id = %d LIMIT 1", $session_id),
        ARRAY_A
    );
    if (!$session) {
        return new WP_Error('cbt_session_not_found', 'Sesi tidak ditemukan.', ['status' => 404]);
    }
    if ($session['status'] === 'finished') {
        return [
            'status'     => 'already_finished',
            'score'      => (float) $session['score'],
            'show_score' => (int) $opts['show_score_at_end'],
        ];
    }

    $test = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM $table_tests WHERE id = %d LIMIT 1", $session['test_id']),
        ARRAY_A
    );
    if (!$test) {
        return new WP_Error('cbt_test_not_found', 'Tes tidak ditemukan.', ['status' => 404]);
    }

    $time_method = $test['time_method'] ?: $opts['time_mode'];  // classic / dynamic
    $duration    = (int) $test['duration_minutes'];

    // === 1) Minimal sisa waktu ===
    if (!empty($opts['min_remaining_minutes'])) {
        $now_ts = current_time('timestamp');

        if ($time_method === 'classic' && !empty($test['test_date']) && !empty($test['start_time'])) {
            $test_start_ts = strtotime($test['test_date'] . ' ' . $test['start_time']);
            $end_ts        = $test_start_ts + ($duration * 60);
        } else {
            $start_ts = strtotime($session['start_time']);
            $end_ts   = $start_ts + ($duration * 60);
        }

        $remaining_sec = $end_ts - $now_ts;
        $remaining_min = floor($remaining_sec / 60);

        if ($remaining_min > $opts['min_remaining_minutes']) {
            return new WP_Error(
                'cbt_too_early_finish',
                'Belum boleh mengumpulkan. Minimal sisa waktu harus kurang atau sama dengan ' .
                intval($opts['min_remaining_minutes']) . ' menit.',
                ['status' => 403]
            );
        }
    }

    // === 2) Wajib menjawab semua soal ===
    if (!empty($opts['require_all_answered'])) {
        $expected = (int) $test['num_questions'];

        if ($expected > 0) {
            $answered = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_answers WHERE session_id = %d AND answer IS NOT NULL",
                    $session_id
                )
            );

            if ($answered < $expected) {
                return new WP_Error(
                    'cbt_not_all_answered',
                    'Anda belum menjawab semua soal (' . $answered . ' / ' . $expected . ').',
                    ['status' => 403]
                );
            }
        }
    }

    // === 3) Hitung total skor ===
    $answers = $wpdb->get_results(
        $wpdb->prepare("SELECT SUM(score) AS total FROM $table_answers WHERE session_id = %d", $session_id),
        ARRAY_A
    );
    $total = isset($answers[0]['total']) ? (float) $answers[0]['total'] : 0.0;

    $wpdb->update($table_sessions, [
        'status'   => 'finished',
        'end_time' => current_time('mysql'),
        'score'    => $total,
    ], [
        'id' => $session_id,
    ]);

    return [
        'status'     => 'finished',
        'score'      => $total,
        'show_score' => (int) $opts['show_score_at_end'],
    ];
}

/**
/**
 * =========================================================
 * 7.5 EXCEL SAVE (CONFIG + PESERTA + META SOAL)
 * =========================================================
 */
/**
 * =========================================================
 * 7.5 EXCEL SAVE (CONFIG + PESERTA + META SOAL)
 * =========================================================
 */
/**
 * =========================================================
 * 7.5 EXCEL SAVE (CONFIG + PESERTA + META SOAL)
 * =========================================================
 */
function cbt_bima_excel_save(WP_REST_Request $req)
{
    global $wpdb;

    $body = $req->get_json_params();

    // === 1. Validasi Excel Key ===
    $excel_key = $body['excel_key'] ?? '';
    $valid_key = get_option('cbt_excel_key', '123');
    if ($excel_key !== $valid_key) {
        return new WP_Error('cbt_excel_key_invalid', 'Excel Key salah.', ['status' => 403]);
    }

    $test_data     = $body['test'] ?? null;
    $students_data = $body['students'] ?? [];
    $qmeta_data    = $body['questions_meta'] ?? [];

    $table_tests    = $wpdb->prefix . 'cbt_tests';
    $table_students = $wpdb->prefix . 'cbt_students';
    $table_keys     = $wpdb->prefix . 'cbt_keys';

    // 🔧 ambil pengaturan global CBT
    $opts = function_exists('cbt_bima_get_options') ? cbt_bima_get_options() : [];
    $global_time_method = !empty($opts['time_mode']) ? $opts['time_mode'] : 'classic'; // dynamic | classic
    $global_min_submit  = isset($opts['min_remaining_minutes'])
        ? (int) $opts['min_remaining_minutes']
        : 0;

    $test_id = 0;

    // === 2. Upsert TEST (cbt_tests) berdasarkan test_code ===
    if ($test_data) {
        $code = $test_data['code'] ?? '';
        if ($code === '') {
            return new WP_Error('cbt_test_code_empty', 'Kode Test kosong.', ['status' => 400]);
        }
        $existing = cbt_bima_get_test_by_code($code);

        // Durasi dari Excel (kalau ada), default 90
        $duration_minutes = isset($test_data['duration_minutes'])
            ? (int) $test_data['duration_minutes']
            : 90;

        // ===========================
        //  SHUFFLE dari Excel
        // ===========================
        $shuffle_questions = 0;
        if (isset($test_data['shuffle_questions'])) {
            $shuffle_questions = (int) $test_data['shuffle_questions'] ? 1 : 0;
        } elseif (isset($test_data['ShuffleSoal'])) {
            $shuffle_questions = (int) $test_data['ShuffleSoal'] ? 1 : 0;
        }

        $shuffle_options = 0;
        if (isset($test_data['shuffle_options'])) {
            $shuffle_options = (int) $test_data['shuffle_options'] ? 1 : 0;
        } elseif (isset($test_data['ShuffleOpsi'])) {
            $shuffle_options = (int) $test_data['ShuffleOpsi'] ? 1 : 0;
        }

        $data = [
            'code'              => $code,
            'name'              => $test_data['name'] ?? $code,
            'status'            => $test_data['status'] ?? 'active',
            'subtest'           => $test_data['subtest'] ?? '',
            'duration_minutes'  => $duration_minutes,

            // ⬇️ ini murni dari Excel (per test)
            'shuffle_questions' => $shuffle_questions,
            'shuffle_options'   => $shuffle_options,
            'num_questions'   => isset($test_data['num_questions']) ? (int) $test_data['num_questions'] : 0,
'must_answer'     => isset($test_data['must_answer']) ? (int) $test_data['must_answer'] : 0,


            // ⬇️ ini SELALU ikut global web admin
            'min_submit_minutes'=> $global_min_submit,
            'time_method'       => $global_time_method,

            'save_method'       => 'realtime',
            'config_json'       => isset($test_data['config_json']) ? wp_json_encode($test_data['config_json']) : null,
        ];

        if (!empty($test_data['test_date'])) {
            $data['test_date'] = $test_data['test_date'];
        }
        if (!empty($test_data['start_time'])) {
            $data['start_time'] = $test_data['start_time'];
        }

        if ($existing) {
            $wpdb->update($table_tests, $data, ['id' => $existing['id']]);
            $test_id = (int) $existing['id'];
        } else {
            $wpdb->insert($table_tests, $data);
            $test_id = (int) $wpdb->insert_id;
        }
    }

    // backup kalau tadi belum dapat id
    if (!$test_id && !empty($test_data['code'])) {
        $again = cbt_bima_get_test_by_code($test_data['code']);
        if ($again) {
            $test_id = (int) $again['id'];
        }
    }

    if (!$test_id) {
        return new WP_Error('cbt_test_not_found', 'Tes tidak ditemukan / gagal disimpan.', ['status' => 500]);
    }

    // === 3. Upsert STUDENTS (cbt_students) ===
    foreach ($students_data as $s) {
        $username = trim($s['username'] ?? '');
        if ($username === '') {
            continue;
        }

        $nama  = $s['nama'] ?? $username;
        $pass  = $s['password'] ?? '123456';

        $exist = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_students WHERE username = %s LIMIT 1", $username),
            ARRAY_A
        );

        // mapel otomatis dari test code
        $data_s = [
            'nis'           => $s['nis'] ?? '',
            'username'      => $username,
            'full_name'     => $nama,
            'ket1'          => $s['ket1'] ?? '',
            'ket2'          => $s['ket2'] ?? '',
            'ket3'          => $s['ket3'] ?? '',
            'mapel'         => $test_data['code'] ?? '',
            'server'        => $s['server'] ?? '',
            'session_label' => $s['sesi'] ?? '',
            'status'        => 'active',
        ];

        if ($exist) {
            if (!empty($pass)) {
                $data_s['pass_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            }
            $wpdb->update($table_students, $data_s, ['id' => $exist['id']]);
        } else {
            $data_s['pass_hash'] = password_hash($pass, PASSWORD_DEFAULT);
            $wpdb->insert($table_students, $data_s);
        }
    }

    // === 4. Simpan KUNCI ke wpx_cbt_keys ===
    $inserted = 0;

    if (!empty($qmeta_data)) {
        // hapus kunci lama test ini
        $wpdb->delete($table_keys, ['test_id' => $test_id], ['%d']);

        foreach ($qmeta_data as $qm) {
            // alias nama field biar fleksibel
            $number = 0;
            if (isset($qm['number'])) {
                $number = (int) $qm['number'];
            } elseif (isset($qm['No'])) {
                $number = (int) $qm['No'];
            } elseif (isset($qm['no'])) {
                $number = (int) $qm['no'];
            }

            if ($number <= 0) {
                continue;
            }

            $raw_key = '';
            if (isset($qm['key'])) {
                $raw_key = $qm['key'];
            } elseif (isset($qm['Kunci'])) {
                $raw_key = $qm['Kunci'];
            } elseif (isset($qm['kunci'])) {
                $raw_key = $qm['kunci'];
            }

            $key   = strtoupper(trim((string) $raw_key));
            $score = 0;
            if (isset($qm['score'])) {
                $score = (float) $qm['score'];
            } elseif (isset($qm['Skor'])) {
                $score = (float) $qm['Skor'];
            } elseif (isset($qm['skor'])) {
                $score = (float) $qm['skor'];
            }

            $group = '';
            if (isset($qm['group'])) {
                $group = sanitize_text_field($qm['group']);
            } elseif (isset($qm['Group'])) {
                $group = sanitize_text_field($qm['Group']);
            }

            $lock = 0;
            if (isset($qm['lock'])) {
                $lock = !empty($qm['lock']) ? 1 : 0;
            } elseif (isset($qm['Lock'])) {
                $lock = !empty($qm['Lock']) ? 1 : 0;
            }

            // Validasi kunci
            if (!in_array($key, ['A', 'B', 'C', 'D', 'E'], true)) {
                continue;
            }

            $ok = $wpdb->insert($table_keys, [
                'test_id'     => $test_id,
                'question_no' => $number,
                'answer_key'  => $key,
                'score'       => $score,
                'grouping'    => $group,
                'is_locked'   => $lock,
            ]);

            if ($ok !== false) {
                $inserted++;
            } else {
                error_log('CBT KEYS INSERT ERROR: ' . $wpdb->last_error);
            }
        }
    }

    return [
        'status'         => 'ok',
        'test_id'        => $test_id,
        'students_cnt'   => count($students_data),
        'keys_input_cnt' => count($qmeta_data),  // baris dari Excel
        'keys_inserted'  => $inserted,           // baris yang masuk DB
        'debug_shuffle_questions' => isset($test_data['shuffle_questions']) ? $test_data['shuffle_questions'] : null,
        'debug_shuffle_options'   => isset($test_data['shuffle_options']) ? $test_data['shuffle_options'] : null,
    ];
}




add_action('rest_api_init', function () {
    register_rest_route(
        'cbt/v1',
        '/excel/save',
        [
            'methods'             => 'POST',
            'callback'            => 'cbt_excel_save_handler_fast',
            'permission_callback' => '__return_true',
        ]
    );
});


add_action('rest_api_init', function () {
    register_rest_route('cbt/v1', '/excel/results', [
        'methods'  => 'GET',
        'callback' => 'cbt_bima_excel_results',
        'permission_callback' => '__return_true',
    ]);
});
function cbt_bima_excel_results(WP_REST_Request $req)
{
    global $wpdb;

    $excel_key = $req->get_param('excel_key');
    $valid_key = get_option('cbt_excel_key', '123');
    if ($excel_key !== $valid_key) {
        return new WP_Error('cbt_excel_key_invalid', 'Excel Key salah.', ['status' => 403]);
    }

    $test_code = $req->get_param('test_code');
    if (!$test_code) {
        return new WP_Error('cbt_test_code_empty', 'Kode test kosong.', ['status' => 400]);
    }

    // Ambil test
    $test = cbt_bima_get_test_by_code($test_code);
    if (!$test) {
        return new WP_Error('cbt_test_not_found', 'Tes tidak ditemukan.', ['status' => 404]);
    }

    $table_sessions = $wpdb->prefix . 'cbt_sessions';
    $table_students = $wpdb->prefix . 'cbt_students';

    // Ambil daftar sesi & nilai
    $sql = "
        SELECT 
            s.id          AS session_id,
            s.score       AS score,
            s.status      AS status,
            st.full_name  AS full_name,
            st.username   AS username,
            st.nis        AS nis,
            st.session_label AS sesi,
            st.ket1       AS ket1,
            st.ket2       AS ket2,
            st.ket3       AS ket3
        FROM $table_sessions s
        JOIN $table_students st ON st.id = s.student_id
        WHERE s.test_id = %d
        ORDER BY st.full_name ASC
    ";

    $rows = $wpdb->get_results(
        $wpdb->prepare($sql, $test['id']),
        ARRAY_A
    );

    return [
        'test_code' => $test['code'],
        'test_name' => $test['name'],
        'rows'      => $rows,
    ];
}

/**
 * Endpoint cepat: impor dari Excel
 */
function cbt_excel_save_handler_fast(WP_REST_Request $request) {
    global $wpdb;

    @set_time_limit(0);
    @ini_set('memory_limit', '512M');
    ignore_user_abort(true);

    if (function_exists('wp_suspend_cache_addition')) {
        wp_suspend_cache_addition(true);
    }

    $params = $request->get_json_params();

    $excel_key = isset($params['excel_key']) ? (string) $params['excel_key'] : '';
    $test_obj  = isset($params['test']) ? (array) $params['test'] : [];
    $students  = isset($params['students']) ? (array) $params['students'] : [];
    $keys      = isset($params['questions_meta']) ? (array) $params['questions_meta'] : [];

    // ----------------- VALIDASI EXCEL KEY -----------------
    $valid_key = get_option('cbt_excel_key', '123');
    if ($excel_key === '' || $excel_key !== $valid_key) {
        return new WP_REST_Response(
            ['status' => 'error', 'message' => 'Excel key tidak valid'],
            403
        );
    }

    // ----------------- VALIDASI TEST -----------------
    $code = isset($test_obj['code']) ? trim($test_obj['code']) : '';
    $name = isset($test_obj['name']) ? trim($test_obj['name']) : '';

    if ($code === '' || $name === '') {
        return new WP_REST_Response(
            ['status' => 'error', 'message' => 'Test code / name kosong'],
            400
        );
    }

    $status           = isset($test_obj['status']) ? trim($test_obj['status']) : 'active';
    $test_date        = isset($test_obj['test_date']) ? trim($test_obj['test_date']) : null;
    $start_time       = isset($test_obj['start_time']) ? trim($test_obj['start_time']) : null;
    $duration_minutes = isset($test_obj['duration_minutes']) ? (int)$test_obj['duration_minutes'] : 0;
    $shuffle_questions= !empty($test_obj['shuffle_questions']) ? 1 : 0;
    $shuffle_options  = !empty($test_obj['shuffle_options']) ? 1 : 0;
    $num_questions    = isset($test_obj['num_questions']) ? (int)$test_obj['num_questions'] : 0;
    $must_answer      = isset($test_obj['must_answer']) ? (int)$test_obj['must_answer'] : 0;

    $table_tests    = $wpdb->prefix . 'cbt_tests';
    $table_students = $wpdb->prefix . 'cbt_students';
    $table_keys     = $wpdb->prefix . 'cbt_keys';

    // ----------------- TRANSAKSI -----------------
    $wpdb->query('START TRANSACTION');

    // 1) Cari atau buat test_id
    $code_esc = $wpdb->_real_escape($code);
    $sql_find = "SELECT id FROM {$table_tests} WHERE code = '{$code_esc}' LIMIT 1";
    $test_id  = (int)$wpdb->get_var($sql_find);

    if ($test_id > 0) {
        // UPDATE test
        $sql_upd = $wpdb->prepare(
            "UPDATE {$table_tests}
             SET name=%s, status=%s, test_date=%s, start_time=%s,
                 duration_minutes=%d, shuffle_questions=%d, shuffle_options=%d,
                 num_questions=%d, must_answer=%d
             WHERE id=%d",
            $name,
            $status,
            $test_date ?: null,
            $start_time ?: null,
            $duration_minutes,
            $shuffle_questions,
            $shuffle_options,
            $num_questions,
            $must_answer,
            $test_id
        );
        $wpdb->query($sql_upd);
    } else {
        // INSERT test
        $sql_ins = $wpdb->prepare(
            "INSERT INTO {$table_tests}
             (code, name, status, test_date, start_time,
              duration_minutes, shuffle_questions, shuffle_options,
              num_questions, must_answer)
             VALUES (%s,%s,%s,%s,%s,%d,%d,%d,%d,%d)",
            $code,
            $name,
            $status,
            $test_date ?: null,
            $start_time ?: null,
            $duration_minutes,
            $shuffle_questions,
            $shuffle_options,
            $num_questions,
            $must_answer
        );
        $wpdb->query($sql_ins);
        $test_id = (int)$wpdb->insert_id;
    }

    if ($test_id <= 0) {
        $wpdb->query('ROLLBACK');
        return new WP_REST_Response(
            ['status' => 'error', 'message' => 'Gagal menyimpan test'],
            500
        );
    }

    // ----------------- 2) HAPUS PESERTA & KUNCI LAMA -----------------
    $wpdb->query("DELETE FROM {$table_students} WHERE test_id = {$test_id}");
    $wpdb->query("DELETE FROM {$table_keys}     WHERE test_id = {$test_id}");

    // ----------------- 3) INSERT PESERTA (BULK, TANPA PREPARE BESAR) -----------------
    $inserted_students = 0;

    if (!empty($students)) {
        $values_chunks = [];
        $chunk         = [];
        $chunk_size    = 200; // biar string SQL tidak terlalu jumbo

        foreach ($students as $row) {
            $nis = isset($row['nis']) ? trim((string)$row['nis']) : '';
            if ($nis === '') {
                continue;
            }

            $username = isset($row['username']) ? (string)$row['username'] : '';
            $password = isset($row['password']) ? (string)$row['password'] : '';
            $nama     = isset($row['nama']) ? (string)$row['nama'] : '';
            $ket1     = isset($row['ket1']) ? (string)$row['ket1'] : '';
            $ket2     = isset($row['ket2']) ? (string)$row['ket2'] : '';
            $ket3     = isset($row['ket3']) ? (string)$row['ket3'] : '';
            $mapel    = isset($row['mapel']) ? (string)$row['mapel'] : '';
            $server   = isset($row['server']) ? (string)$row['server'] : '';
            $sesi     = isset($row['sesi']) ? (string)$row['sesi'] : '';

            // ESCAPE manual
            $nis_e      = $wpdb->_real_escape($nis);
            $user_e     = $wpdb->_real_escape($username);
            $pass_e     = $wpdb->_real_escape($password);
            $nama_e     = $wpdb->_real_escape($nama);
            $ket1_e     = $wpdb->_real_escape($ket1);
            $ket2_e     = $wpdb->_real_escape($ket2);
            $ket3_e     = $wpdb->_real_escape($ket3);
            $mapel_e    = $wpdb->_real_escape($mapel);
            $server_e   = $wpdb->_real_escape($server);
            $sesi_e     = $wpdb->_real_escape($sesi);

            $chunk[] = "(" .
                (int)$test_id . "," .
                "'{$nis_e}'," .
                "'{$user_e}'," .
                "'{$pass_e}'," .
                "'{$nama_e}'," .
                "'{$ket1_e}'," .
                "'{$ket2_e}'," .
                "'{$ket3_e}'," .
                "'{$mapel_e}'," .
                "'{$server_e}'," .
                "'{$sesi_e}'" .
            ")";

            $inserted_students++;

            // tiap 200 baris, flush ke query
            if (count($chunk) >= $chunk_size) {
                $values_chunks[] = implode(',', $chunk);
                $chunk = [];
            }
        }

        if (!empty($chunk)) {
            $values_chunks[] = implode(',', $chunk);
        }

        foreach ($values_chunks as $values_sql) {
            $sql_ins_students = "
                INSERT INTO {$table_students}
                (test_id, nis, username, password, full_name, ket1, ket2, ket3, mapel, server, session_label)
                VALUES {$values_sql}
            ";
            $wpdb->query($sql_ins_students);
        }
    }

    // ----------------- 4) INSERT KUNCI (BULK, TANPA PREPARE BESAR) -----------------
    $inserted_keys = 0;

    if (!empty($keys)) {
        $values_chunks_k = [];
        $chunk_k         = [];
        $chunk_size_k    = 200;

        foreach ($keys as $row) {
            if (!isset($row['number']) || !isset($row['key'])) {
                continue;
            }

            $number = (int)$row['number'];
            $keyChr = strtoupper(substr((string)$row['key'], 0, 1));
            if ($number <= 0 || $keyChr === '') {
                continue;
            }

            $score = isset($row['score']) ? (float)$row['score'] : 1.0;
            $grp   = isset($row['group']) ? (string)$row['group'] : '';
            $lock  = !empty($row['lock']) ? 1 : 0;

            $key_e  = $wpdb->_real_escape($keyChr);
            $grp_e  = $wpdb->_real_escape($grp);

            // dec float -> string dengan titik
            $score_s = str_replace(',', '.', (string)$score);

            $chunk_k[] = "(" .
                (int)$test_id . "," .
                (int)$number . "," .
                "'{$key_e}'," .
                "{$score_s}," .
                "'{$grp_e}'," .
                (int)$lock .
            ")";

            $inserted_keys++;

            if (count($chunk_k) >= $chunk_size_k) {
                $values_chunks_k[] = implode(',', $chunk_k);
                $chunk_k = [];
            }
        }

        if (!empty($chunk_k)) {
            $values_chunks_k[] = implode(',', $chunk_k);
        }

        foreach ($values_chunks_k as $values_sql_k) {
            $sql_ins_keys = "
                INSERT INTO {$table_keys}
                (test_id, number, `key`, score, `group`, `lock`)
                VALUES {$values_sql_k}
            ";
            $wpdb->query($sql_ins_keys);
        }
    }

    // ----------------- COMMIT -----------------
    $wpdb->query('COMMIT');

    return new WP_REST_Response(
        [
            'status'            => 'ok',
            'test_id'           => $test_id,
            'students_cnt'      => count($students),
            'students_inserted' => $inserted_students,
            'keys_cnt'          => count($keys),
            'keys_inserted'     => $inserted_keys,
        ],
        200
    );
}
/**
 * =========================================================
 * 8. OPTIONS & ADMIN MENU CBT BIMA
 * =========================================================
 */
function cbt_bima_default_options()
{
    return [
        'hide_subject_choice'     => 0,
        'show_all_subjects'       => 0,
        'show_score_at_end'       => 1,
        'force_reset_on_exit'     => 1,
        'allow_logout_before_end' => 0,
        'allow_self_registration' => 0,
        'auto_token'              => 1,
        'require_all_answered'    => 0,
        'blocked_message'         => 'Akun Anda diblokir. Silakan hubungi pengawas.',
        'locked_message'          => 'Sesi ujian Anda terkunci. Silakan hubungi pengawas.',
        'min_remaining_minutes'   => 60,
        'time_mode'               => 'dynamic',
        'save_mode'               => 'realtime',
    ];
}

function cbt_bima_get_options()
{
    $base = [
        'token_duration' => (int) get_option('cbt_token_duration', 15),
        'excel_key'      => get_option('cbt_excel_key', '123'),
        'timezone'       => get_option('cbt_timezone', 'Asia/Jakarta'),
    ];

    $extra = get_option('cbt_bima_options', []);
    if (!is_array($extra)) {
        $extra = [];
    }

    return array_merge($base, wp_parse_args($extra, cbt_bima_default_options()));
}

function cbt_bima_save_options($data)
{
    $defaults = cbt_bima_default_options();

    $opt = [];
    $opt['token_duration'] = isset($data['token_duration'])
        ? max(1, (int) $data['token_duration'])
        : 15;

    $opt['excel_key'] = isset($data['excel_key'])
        ? sanitize_text_field($data['excel_key'])
        : '123';

    $opt['timezone'] = isset($data['timezone'])
        ? sanitize_text_field($data['timezone'])
        : 'Asia/Jakarta';

    update_option('cbt_token_duration', $opt['token_duration']);
    update_option('cbt_excel_key', $opt['excel_key']);
    update_option('cbt_timezone', $opt['timezone']);

    $extra = [];
    $extra['hide_subject_choice']     = !empty($data['hide_subject_choice']) ? 1 : 0;
    $extra['show_all_subjects']       = !empty($data['show_all_subjects']) ? 1 : 0;
    $extra['show_score_at_end']       = !empty($data['show_score_at_end']) ? 1 : 0;
    $extra['force_reset_on_exit']     = !empty($data['force_reset_on_exit']) ? 1 : 0;
    $extra['allow_logout_before_end'] = !empty($data['allow_logout_before_end']) ? 1 : 0;
    $extra['allow_self_registration'] = !empty($data['allow_self_registration']) ? 1 : 0;
    $extra['auto_token']              = !empty($data['auto_token']) ? 1 : 0;
    $extra['require_all_answered']    = !empty($data['require_all_answered']) ? 1 : 0;

    $extra['blocked_message'] = isset($data['blocked_message'])
        ? sanitize_textarea_field($data['blocked_message'])
        : $defaults['blocked_message'];

    $extra['locked_message'] = isset($data['locked_message'])
        ? sanitize_textarea_field($data['locked_message'])
        : $defaults['locked_message'];

    $extra['min_remaining_minutes'] = isset($data['min_remaining_minutes'])
        ? max(0, (int) $data['min_remaining_minutes'])
        : $defaults['min_remaining_minutes'];

    $allowed_time_mode = ['dynamic', 'classic'];
    $extra['time_mode'] = in_array($data['time_mode'] ?? '', $allowed_time_mode, true)
        ? $data['time_mode']
        : $defaults['time_mode'];

    $allowed_save_mode = ['realtime', 'classic'];
    $extra['save_mode'] = in_array($data['save_mode'] ?? '', $allowed_save_mode, true)
        ? $data['save_mode']
        : $defaults['save_mode'];

    update_option('cbt_bima_options', $extra);

    return array_merge($opt, $extra);
}
/**
 * Reset satu peserta untuk satu test:
 * - Hapus jawaban di wpx_cbt_answers
 * - Hapus sesi di wpx_cbt_sessions
 */
if (!function_exists('cbt_bima_reset_peserta_for_test')) {
    /**
 * Reset login peserta untuk satu test:
 * - TIDAK menghapus jawaban di wpx_cbt_answers
 * - Mengubah semua sesi test tsb untuk peserta ini menjadi in_progress
 *   supaya bisa login lagi (jawaban lama tetap ada).
 */
if (!function_exists('cbt_bima_reset_peserta_for_test')) {
    function cbt_bima_reset_peserta_for_test($test_id, $student_id) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'cbt_sessions';

        // Ambil semua sesi peserta ini pada test tsb
        $sessions = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, status FROM $table_sessions 
                 WHERE test_id = %d AND student_id = %d",
                $test_id,
                $student_id
            ),
            ARRAY_A
        );

        if (empty($sessions)) {
            return [
                'sessions_updated' => 0,
            ];
        }

        // Update semua sesi jadi in_progress, buka end_time, refresh last_activity
        $session_ids = array_map(
            fn($row) => (int)$row['id'],
            $sessions
        );
        $in_ids = implode(',', $session_ids);

        $now = current_time('mysql');

        $updated = $wpdb->query(
            "UPDATE $table_sessions 
             SET status = 'in_progress',
                 end_time = NULL,
                 last_activity = '$now'
             WHERE id IN ($in_ids)"
        );

        return [
            'sessions_updated' => (int)$updated,
        ];
    }
}

}

/**
 * Reset semua peserta untuk 1 test (hapus semua sesi + jawaban test itu)
 */
if (!function_exists('cbt_bima_reset_all_for_test')) {
    function cbt_bima_reset_all_for_test($test_id) {
        global $wpdb;
        $table_sessions = $wpdb->prefix . 'cbt_sessions';
        $table_answers  = $wpdb->prefix . 'cbt_answers';

        $session_ids = $wpdb->get_col(
            $wpdb->prepare("SELECT id FROM $table_sessions WHERE test_id = %d", $test_id)
        );

        if (empty($session_ids)) {
            return [ 'sessions_deleted' => 0, 'answers_deleted' => 0 ];
        }

        $session_ids_int = array_map('intval', $session_ids);
        $in_ids = implode(',', $session_ids_int);

        $answers_cnt = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM $table_answers WHERE session_id IN ($in_ids)"
        );

        if ($answers_cnt > 0) {
            $wpdb->query("DELETE FROM $table_answers WHERE session_id IN ($in_ids)");
        }

        $sessions_cnt = count($session_ids_int);
        $wpdb->query("DELETE FROM $table_sessions WHERE id IN ($in_ids)");

        return [
            'sessions_deleted' => $sessions_cnt,
            'answers_deleted'  => $answers_cnt,
        ];
    }
}
if (!function_exists('cbt_bima_render_reset_page')) {
    function cbt_bima_render_reset_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_tests    = $wpdb->prefix . 'cbt_tests';
        $table_students = $wpdb->prefix . 'cbt_students';

        // Ambil daftar test biar bisa dipilih
        $tests = $wpdb->get_results(
            "SELECT id, code, name, status FROM $table_tests ORDER BY test_date DESC, id DESC",
            ARRAY_A
        );

        $notice = '';

        // === Handle reset satu peserta ===
        if (!empty($_POST['cbt_bima_do_reset_one'])) {
            check_admin_referer('cbt_bima_reset_one');

            $test_id   = isset($_POST['reset_test_id']) ? intval($_POST['reset_test_id']) : 0;
            $user_key  = isset($_POST['reset_user_key']) ? trim(sanitize_text_field($_POST['reset_user_key'])) : '';

            if ($test_id <= 0 || $user_key === '') {
                $notice = '<div class="notice notice-error"><p>Mohon pilih Test dan isi Username/NIS peserta.</p></div>';
            } else {
                // Cari peserta by username ATAU NIS
                $student = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT * FROM $table_students WHERE username = %s OR nis = %s LIMIT 1",
                        $user_key,
                        $user_key
                    ),
                    ARRAY_A
                );

                if (!$student) {
                    $notice = '<div class="notice notice-error"><p>Peserta dengan username/NIS <strong>' . esc_html($user_key) . '</strong> tidak ditemukan.</p></div>';
                } else {
                    $res = cbt_bima_reset_peserta_for_test($test_id, (int)$student['id']);

                    $notice = '<div class="notice notice-success"><p>Login peserta 
    <strong>' . esc_html($student['full_name']) . '</strong> (' . esc_html($student['username']) . ')
    untuk test ID ' . intval($test_id) . ' berhasil di-reset.
    Sesi yang diubah ke <code>in_progress</code>: <strong>' . intval($res['sessions_updated']) . '</strong>.
    Jawaban tetap aman.</p></div>';

                }
            }
        }

        // === Handle reset semua peserta di 1 test ===
        if (!empty($_POST['cbt_bima_do_reset_all'])) {
            check_admin_referer('cbt_bima_reset_all');

            $test_id_all = isset($_POST['reset_all_test_id']) ? intval($_POST['reset_all_test_id']) : 0;
            if ($test_id_all <= 0) {
                $notice = '<div class="notice notice-error"><p>Mohon pilih Test yang ingin direset.</p></div>';
            } else {
                $res = cbt_bima_reset_all_for_test($test_id_all);

                $notice = '<div class="notice notice-success"><p>Reset semua peserta untuk Test ID ' . intval($test_id_all) .
                    '. Sesi terhapus: <strong>' . intval($res['sessions_deleted']) .
                    '</strong>, jawaban terhapus: <strong>' . intval($res['answers_deleted']) . '</strong>.</p></div>';
            }
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Reset Peserta CBT</h1>
            <hr class="wp-header-end" />

            <?php
            if ($notice) {
                echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            }
            ?>

            <div style="max-width: 900px; margin-top: 20px;">

                <!-- RESET SATU PESERTA -->
                <div class="postbox" style="margin-bottom: 20px;">
                    <h2 class="hndle"><span>Reset Satu Peserta</span></h2>
                    <div class="inside">
                       <p>
    Fitur ini akan <strong>membuka kembali sesi ujian</strong> peserta untuk satu test
    (status diubah menjadi <code>in_progress</code>) sehingga peserta bisa login lagi.
    <br>Jawaban yang sudah tersimpan <strong>TIDAK dihapus</strong>.
</p>


                        <form method="post">
                            <?php wp_nonce_field('cbt_bima_reset_one'); ?>
                            <input type="hidden" name="cbt_bima_do_reset_one" value="1" />

                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="reset_test_id">Pilih Test</label></th>
                                        <td>
                                            <select name="reset_test_id" id="reset_test_id">
                                                <option value="0">— Pilih Test —</option>
                                                <?php foreach ($tests as $t) : ?>
                                                    <option value="<?php echo intval($t['id']); ?>">
                                                        <?php
                                                        echo esc_html($t['code'] . ' — ' . $t['name'] . ' [' . $t['status'] . ']');
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Test yang akan direset untuk peserta ini.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th scope="row"><label for="reset_user_key">Username / NIS Peserta</label></th>
                                        <td>
                                            <input type="text" name="reset_user_key" id="reset_user_key" class="regular-text" />
                                            <p class="description">Isi dengan <strong>username</strong> atau <strong>NIS</strong> peserta.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-secondary"
                                        onclick="return confirm('Yakin ingin reset peserta ini untuk test terpilih? Semua sesi & jawaban akan dihapus.');">
                                    Reset Peserta
                                </button>
                            </p>
                        </form>
                    </div>
                </div>

                <!-- RESET SEMUA PESERTA DI TEST -->
                <div class="postbox">
                    <h2 class="hndle"><span>Reset Semua Peserta pada Satu Test</span></h2>
                    <div class="inside">
                        <p><strong>PERINGATAN:</strong> Ini akan menghapus semua sesi & jawaban untuk test yang dipilih. Gunakan saat akan mengulang tryout dari nol.</p>

                        <form method="post">
                            <?php wp_nonce_field('cbt_bima_reset_all'); ?>
                            <input type="hidden" name="cbt_bima_do_reset_all" value="1" />

                            <table class="form-table">
                                <tbody>
                                    <tr>
                                        <th scope="row"><label for="reset_all_test_id">Pilih Test</label></th>
                                        <td>
                                            <select name="reset_all_test_id" id="reset_all_test_id">
                                                <option value="0">— Pilih Test —</option>
                                                <?php foreach ($tests as $t) : ?>
                                                    <option value="<?php echo intval($t['id']); ?>">
                                                        <?php
                                                        echo esc_html($t['code'] . ' — ' . $t['name'] . ' [' . $t['status'] . ']');
                                                        ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <p class="description">Semua peserta pada test ini akan direset.</p>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>

                            <p class="submit">
                                <button type="submit" class="button button-danger"
                                        onclick="return confirm('YAKIN benar-benar ingin reset SEMUA peserta untuk test ini? Semua sesi & jawaban akan dihapus.');">
                                    Reset Semua Peserta pada Test Ini
                                </button>
                            </p>
                        </form>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}



// Admin menu
// ================== ADMIN MENU CBT BIMA ==================
if (!function_exists('cbt_bima_register_admin_menu')) {
    function cbt_bima_register_admin_menu() {
        // Halaman utama: Pengaturan
        add_menu_page(
            'CBT Bima',                          // Page title
            'CBT Bima',                          // Menu title
            'manage_options',                    // Capability
            'cbt_bima_settings',                 // Slug
            'cbt_bima_render_settings_page',     // Callback
            'dashicons-welcome-learn-more',      // Icon
            26                                   // Position
        );

        // Submenu: Pengaturan (default)
        add_submenu_page(
            'cbt_bima_settings',
            'Pengaturan CBT Bima',
            'Pengaturan',
            'manage_options',
            'cbt_bima_settings',
            'cbt_bima_render_settings_page'
        );

        // Submenu: Reset Peserta
        add_submenu_page(
            'cbt_bima_settings',
            'Reset Peserta CBT',
            'Reset Peserta',
            'manage_options',
            'cbt_bima_reset',
            'cbt_bima_render_reset_page'
        );
    }
}
add_action('admin_menu', 'cbt_bima_register_admin_menu');

// Render halaman pengaturan
function cbt_bima_render_settings_page()
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = '';

    if (!empty($_POST['cbt_bima_save_settings'])) {
        check_admin_referer('cbt_bima_save_settings');
        $saved  = cbt_bima_save_options($_POST['cbt_bima'] ?? []);
        $notice = '<div class="updated notice"><p>Pengaturan CBT berhasil disimpan.</p></div>';
    }

    if (!empty($_POST['cbt_bima_regen_token'])) {
        check_admin_referer('cbt_bima_regen_token');
        $new_token = cbt_bima_generate_token();
        $notice = '<div class="updated notice"><p>Token baru berhasil digenerate: <strong>' .
            esc_html($new_token) . '</strong></p></div>';
    }

    $opts  = cbt_bima_get_options();
    $state = cbt_bima_maybe_rotate_token();

    $now        = time();
    $expires_ts = (int) $state['expires'];
    $expires_txt = $expires_ts > 0 ? date_i18n('d-m-Y H:i:s', $expires_ts) : '-';
    $is_active  = ($expires_ts > $now && !empty($state['token']));
    // Kalau token sudah kadaluarsa dan opsi auto_token aktif → buat token baru otomatis
if ( !$is_active && !empty( $opts['auto_token'] ) ) {
    $state = cbt_bima_generate_token();
    $now   = time();
    $expires_txt = $state['expires'] > 0 ? date_i18n('d-m-Y H:i:s', $state['expires']) : '-';
    $is_active   = ($state['expires'] > $now && !empty($state['token']));

    $notice .= '<div class="updated notice"><p>Token kadaluarsa. Sistem membuat token baru: <strong>' 
        . esc_html( $state['token'] ) . '</strong></p></div>';
}

    ?>
    
    <div class="wrap">
        <h1 class="wp-heading-inline">Pengaturan CBT Bima</h1>
        <hr class="wp-header-end" />

        <?php
        if ($notice) {
            echo $notice; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        ?>

        <div class="cbt-bima-admin" style="max-width: 900px; margin-top: 20px;">

            <!-- Panel Token Aktif -->
            <div class="postbox" style="margin-bottom: 20px;">
                <h2 class="hndle"><span>Token Aktif</span></h2>
                <div class="inside">
                    <p>
                        <strong>Token Saat Ini:</strong>
                        <?php if (!empty($state['token'])) : ?>
                            <code style="font-size: 16px;"><?php echo esc_html($state['token']); ?></code>
                            <?php if ($is_active) : ?>
                                <span style="color: #46b450; font-weight: 600; margin-left: 8px;">(AKTIF)</span>
                            <?php else : ?>
                                <span style="color: #dc3232; font-weight: 600; margin-left: 8px;">(KADALUARSA)</span>
                            <?php endif; ?>
                        <?php else : ?>
                            <em>Belum ada token.</em>
                        <?php endif; ?>
                    </p>
                    <p><strong>Masa Aktif Token:</strong> <?php echo (int) $state['duration']; ?> menit</p>
                    <p><strong>Kadaluarsa:</strong> <?php echo esc_html($expires_txt); ?></p>

                    <form method="post" style="margin-top: 10px;">
                        <?php wp_nonce_field('cbt_bima_regen_token'); ?>
                        <input type="hidden" name="cbt_bima_regen_token" value="1" />
                        <button type="submit" class="button button-secondary">
                            Generate Token Baru
                        </button>
                    </form>
                </div>
            </div>

            <!-- Form Pengaturan Utama -->
            <form method="post">
                <?php wp_nonce_field('cbt_bima_save_settings'); ?>
                <input type="hidden" name="cbt_bima_save_settings" value="1" />

                <div class="postbox">
                    <h2 class="hndle"><span>Pengaturan Umum CBT</span></h2>
                    <div class="inside">
                        <table class="form-table">
                            <tbody>
                                <tr>
                                    <th scope="row">
                                        <label for="cbt_token_duration">Masa Aktif Token (menit)</label>
                                    </th>
                                    <td>
                                        <input name="cbt_bima[token_duration]" type="number" id="cbt_token_duration"
                                               value="<?php echo esc_attr($opts['token_duration']); ?>"
                                               class="small-text" min="1" />
                                        <p class="description">Contoh: 15</p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="cbt_excel_key">Excel Key</label>
                                    </th>
                                    <td>
                                        <input name="cbt_bima[excel_key]" type="text" id="cbt_excel_key"
                                               value="<?php echo esc_attr($opts['excel_key']); ?>"
                                               class="regular-text" />
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="cbt_timezone">Timezone</label>
                                    </th>
                                    <td>
                                        <select name="cbt_bima[timezone]" id="cbt_timezone">
                                            <option value="Asia/Jakarta"  <?php selected($opts['timezone'], 'Asia/Jakarta'); ?>>Asia/Jakarta (WIB)</option>
                                            <option value="Asia/Makassar" <?php selected($opts['timezone'], 'Asia/Makassar'); ?>>Asia/Makassar (WITA)</option>
                                            <option value="Asia/Jayapura" <?php selected($opts['timezone'], 'Asia/Jayapura'); ?>>Asia/Jayapura (WIT)</option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Mapel & Jadwal</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="cbt_bima[hide_subject_choice]" value="1"
                                                <?php checked($opts['hide_subject_choice'], 1); ?> />
                                            Sembunyikan Pilihan Mapel (otomatis pilih mapel sesuai jadwal)
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="cbt_bima[show_all_subjects]" value="1"
                                                <?php checked($opts['show_all_subjects'], 1); ?> />
                                            Tampilkan Semua Mapel (walaupun di luar jadwal)
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Perilaku Ujian</th>
                                    <td>
                                        <label>
                                            <input type="checkbox" name="cbt_bima[show_score_at_end]" value="1"
                                                <?php checked($opts['show_score_at_end'], 1); ?> />
                                            Tampilkan Nilai Siswa di Akhir Ujian
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="cbt_bima[force_reset_on_exit]" value="1"
                                                <?php checked($opts['force_reset_on_exit'], 1); ?> />
                                            Wajib reset ketika siswa keluar tanpa logout
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="cbt_bima[allow_logout_before_end]" value="1"
                                                <?php checked($opts['allow_logout_before_end'], 1); ?> />
                                            Siswa boleh logout sebelum selesai
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="cbt_bima[allow_self_registration]" value="1"
                                                <?php checked($opts['allow_self_registration'], 1); ?> />
                                            Peserta bisa melakukan pendaftaran sendiri
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="cbt_bima[auto_token]" value="1"
                                                <?php checked($opts['auto_token'], 1); ?> />
                                            Token secara otomatis (digenerate sistem)
                                        </label>
                                        <br />
                                        <label>
                                            <input type="checkbox" name="cbt_bima[require_all_answered]" value="1"
                                                <?php checked($opts['require_all_answered'], 1); ?> />
                                            Wajib menjawab semua soal sebelum boleh mengumpulkan
                                        </label>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="cbt_min_remaining_minutes">Minimal Sisa Waktu (menit)</label>
                                    </th>
                                    <td>
                                        <input name="cbt_bima[min_remaining_minutes]" type="number"
                                               id="cbt_min_remaining_minutes"
                                               value="<?php echo esc_attr($opts['min_remaining_minutes']); ?>"
                                               class="small-text" min="0" />
                                        <p class="description">
                                            Minimal sisa waktu agar siswa baru boleh mengumpulkan (contoh: 60).
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Metode Penghitungan Waktu</th>
                                    <td>
                                        <select name="cbt_bima[time_mode]">
                                            <option value="dynamic" <?php selected($opts['time_mode'], 'dynamic'); ?>>
                                                Dynamic (model UNBK, waktu berhenti jika logout/error)
                                            </option>
                                            <option value="classic" <?php selected($opts['time_mode'], 'classic'); ?>>
                                                Classic (waktu jalan terus sesuai jadwal)
                                            </option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">Metode Penyimpanan Jawaban</th>
                                    <td>
                                        <select name="cbt_bima[save_mode]">
                                            <option value="realtime" <?php selected($opts['save_mode'], 'realtime'); ?>>
                                                Realtime – jawaban langsung disimpan ke server
                                            </option>
                                            <option value="classic" <?php selected($opts['save_mode'], 'classic'); ?>>
                                                Classic – simpan sesuai mekanisme lain
                                            </option>
                                        </select>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="cbt_blocked_message">Pesan Error Siswa yang Diblokir</label>
                                    </th>
                                    <td>
                                        <textarea name="cbt_bima[blocked_message]" id="cbt_blocked_message"
                                                  rows="3" class="large-text"><?php
                                            echo esc_textarea($opts['blocked_message']);
                                        ?></textarea>
                                    </td>
                                </tr>

                                <tr>
                                    <th scope="row">
                                        <label for="cbt_locked_message">Pesan Error Siswa Terblokir / Terkunci</label>
                                    </th>
                                    <td>
                                        <textarea name="cbt_bima[locked_message]" id="cbt_locked_message"
                                                  rows="3" class="large-text"><?php
                                            echo esc_textarea($opts['locked_message']);
                                        ?></textarea>
                                    </td>
                                </tr>

                            </tbody>
                        </table>

                        <p class="submit">
                            <button type="submit" class="button button-primary">
                                Simpan Pengaturan
                            </button>
                        </p>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <?php
}
