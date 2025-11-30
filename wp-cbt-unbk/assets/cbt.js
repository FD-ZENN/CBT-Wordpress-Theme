jQuery(function($){
  let sessionToken = null;
  let questions = [];
  let currentIndex = 0;
  let timerInterval = null;
  let remainingSeconds = 0;
  let timeMethod = 'classic';

  function showMessage(msg, isError=false){
    $('#cbt-login-message').html(
      '<div style="color:'+(isError?'red':'green')+'">'+msg+'</div>'
    );
  }

  // Step 1: pilih mapel diisi via AJAX sederhana
  function loadMapel() {
    // Versi simpel: hardcode atau pakai wp_localize_script tambahan
    // Bisa diganti REST endpoint daftar test aktif.
    // Untuk demo: skip.
  }
  loadMapel();

  $('#cbt-btn-next-token').on('click', function(){
    const data = {
      username: $('input[name="username"]').val(),
      password: $('input[name="password"]').val(),
      kode_test: $('select[name="kode_test"]').val()
    };
    if (!data.username || !data.password || !data.kode_test) {
      showMessage('Lengkapi data login dan mapel.', true);
      return;
    }
    $('#cbt-login-form').hide();
    $('#cbt-token-form').show();
  });

  $('#cbt-btn-login-final').on('click', function(){
    const payload = {
      username: $('input[name="username"]').val(),
      password: $('input[name="password"]').val(),
      kode_test: $('select[name="kode_test"]').val(),
      nama_siswa: $('input[name="nama_siswa"]').val(),
      token: $('input[name="token"]').val()
    };

    $.ajax({
      url: CBT_API.rest_url + 'login',
      method: 'POST',
      headers: { 'X-WP-Nonce': CBT_API.nonce },
      data: payload,
      success: function(res){
        sessionToken = res.session_token;
        timeMethod = res.time_method;
        remainingSeconds = res.alokasi_menit * 60;
        showMessage('Login berhasil. Memuat soal...');
        loadQuestions();
      },
      error: function(xhr){
        showMessage(xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Gagal login', true);
        $('#cbt-token-form').show();
        $('#cbt-login-form').hide();
      }
    });
  });

  function loadQuestions(){
    $.ajax({
      url: CBT_API.rest_url + 'questions',
      method: 'GET',
      headers: { 'X-CBT-Session': sessionToken },
      success: function(res){
        questions = res.questions || [];
        if (!questions.length) {
          showMessage('Tidak ada soal untuk test ini.', true);
          return;
        }
        renderExamUI();
        startTimer();
      },
      error: function(xhr){
        showMessage('Gagal memuat soal.', true);
      }
    });
  }

  function renderExamUI(){
    const container = $('<div class="cbt-exam-wrapper"></div>');
    const header = $(`
      <div class="cbt-exam-header">
        <div>Nama: <span id="cbt-student-name"></span></div>
        <div>Mapel: <span id="cbt-test-name"></span></div>
        <div>Timer: <span id="cbt-timer"></span></div>
      </div>
    `);
    const questionBox = $(`
      <div class="cbt-question-box">
        <div id="cbt-question-number"></div>
        <div id="cbt-question-content"></div>
        <div id="cbt-options"></div>
      </div>
    `);
    const navBox = $('<div class="cbt-nav-box"></div>');
    const navButtons = $('<div class="cbt-nav-buttons"></div>');
    const finishBox = $('<div class="cbt-finish-box"><button id="cbt-btn-finish">Selesai Ujian</button></div>');

    // nomor soal 1..n
    questions.forEach(function(q, idx){
      const btn = $('<button class="cbt-nav-num">'+(idx+1)+'</button>');
      btn.on('click', function(){
        saveCurrentAnswer(function(){
          currentIndex = idx;
          renderCurrentQuestion();
        });
      });
      navButtons.append(btn);
    });

    navBox.append(navButtons);

    container.append(header, questionBox, navBox, finishBox);
    $('#cbt-login-message').after(container);

    renderCurrentQuestion();

    $('#cbt-btn-finish').on('click', function(){
      if (!confirm('Anda yakin ingin mengakhiri ujian?')) return;
      saveCurrentAnswer(function(){
        $.ajax({
          url: CBT_API.rest_url + 'finish',
          method: 'POST',
          headers: { 'X-CBT-Session': sessionToken },
          success: function(res){
            clearInterval(timerInterval);
            alert('Ujian selesai. Nilai Anda: '+ (res.nilai || 0).toFixed(2));
            window.location.href = '/'; // logout ke beranda
          },
          error: function(){
            alert('Gagal mengakhiri ujian. Coba lagi.');
          }
        });
      });
    });
  }

  function renderCurrentQuestion(){
    const q = questions[currentIndex];
    if (!q) return;
    $('#cbt-question-number').text('Soal '+q.no_soal);
    $('#cbt-question-content').html(q.post_content || '(Belum ada konten soal)');
    const opts = ['A','B','C','D','E'];
    const optBox = $('#cbt-options').empty();
    opts.forEach(function(o){
      const id = 'opt_'+q.question_id+'_'+o;
      const el = $(`
        <label>
          <input type="radio" name="cbt_answer" value="${o}" id="${id}">
          ${o}
        </label><br>
      `);
      optBox.append(el);
    });
  }

  function saveCurrentAnswer(cb){
    const q = questions[currentIndex];
    if (!q) { if (cb) cb(); return; }
    const ans = $('input[name="cbt_answer"]:checked').val();
    if (!ans) { if (cb) cb(); return; }

    $.ajax({
      url: CBT_API.rest_url + 'save-answer',
      method: 'POST',
      headers: { 'X-CBT-Session': sessionToken },
      data: {
        question_id: q.question_id,
        no_soal:     q.no_soal,
        jawaban:     ans
      },
      complete: function(){
        if (cb) cb();
      }
    });
  }

  function startTimer(){
    function updateDisplay(){
      const m = Math.floor(remainingSeconds/60);
      const s = remainingSeconds%60;
      $('#cbt-timer').text(
        (m<10?'0':'')+m+':' + (s<10?'0':'')+s
      );
      if (remainingSeconds <= 0) {
        clearInterval(timerInterval);
        alert('Waktu habis. Ujian akan diakhiri.');
        $('#cbt-btn-finish').trigger('click');
      }
      remainingSeconds--;
    }
    updateDisplay();
    timerInterval = setInterval(updateDisplay, 1000);
  }
});
