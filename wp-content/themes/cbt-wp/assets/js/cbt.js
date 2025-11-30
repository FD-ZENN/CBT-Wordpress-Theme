// assets/js/cbt.js
(function () {
  const THEME_KEY = "CBT_THEME";
  const hasGlobal = typeof CBT_GLOBAL !== "undefined";
  const rest  = hasGlobal ? CBT_GLOBAL.rest_url : null;
  const nonce = hasGlobal ? CBT_GLOBAL.nonce : null;

  // =============== THEME TOGGLE ===============
  function applyThemeBodyClasses(theme) {
    const body = document.body;
    if (!body) return;

    const t = theme === "light" ? "light" : "dark";

    // ini string body_class dari front-page.php:
    // min-h-screen bg-slate-100 text-slate-900 dark:bg-slate-900 dark:text-slate-100
    // kita ganti jadi versi "light:*" kalau mode light
    let cls = body.className;

    if (t === "light") {
      cls = cls.replace(
        "dark:bg-slate-900 dark:text-slate-100",
        "light:bg-slate-900 light:text-slate-100"
      );
    } else {
      cls = cls.replace(
        "light:bg-slate-900 light:text-slate-100",
        "dark:bg-slate-900 dark:text-slate-100"
      );
    }

    body.className = cls;

    try {
      localStorage.setItem(THEME_KEY, t);
    } catch (e) {}

    const btn = document.getElementById("cbt-theme-toggle");
    if (btn) {
      btn.textContent = t === "light" ? "🌞 Light" : "🌙 Dark";
    }
  }

  function initThemeToggle() {
    let saved = "dark";
    try {
      saved = localStorage.getItem(THEME_KEY) || "dark";
    } catch (e) {}

    // buat tombol
    const btn = document.createElement("button");
    btn.id = "cbt-theme-toggle";
    btn.type = "button";
    btn.textContent = saved === "light" ? "🌞 Light" : "🌙 Dark";
    btn.className =
      "fixed z-50 top-3 right-3 px-3 py-1 text-xs rounded-full shadow-lg border border-slate-500/40 bg-slate-800/80 text-slate-100";

    btn.addEventListener("click", () => {
      const current =
        (localStorage.getItem(THEME_KEY) || "dark") === "light"
          ? "light"
          : "dark";
      applyThemeBodyClasses(current === "light" ? "dark" : "light");
    });

    document.body.appendChild(btn);
    applyThemeBodyClasses(saved);
  }

  // =============== BOOTSTRAP ===============
  document.addEventListener("DOMContentLoaded", function () {
    // 1) SELALU hidupkan theme toggle
    initThemeToggle();

    // 2) Kalau CBT_GLOBAL nggak ada, stop di sini (tapi toggle sudah dibuat)
    if (!hasGlobal) return;

  // ---------- LOGIN & TOKEN ----------
  const loginBtn = document.getElementById("cbt-login-btn");
  const refreshTokenBtn = document.getElementById("cbt-refresh-token");

  if (refreshTokenBtn) {
    refreshTokenBtn.addEventListener("click", fetchToken);
    // langsung load token pertama kali
    fetchToken();
  }

  if (loginBtn) {
    loginBtn.addEventListener("click", handleLogin);
  }

  // ---------- MAPEL & REGISTER ----------
  loadMapel();
  initRegisterUI();

  // ---------- HALAMAN UJIAN ----------
  const examRoot = document.getElementById("cbt-exam-root");
  if (examRoot) {
    initExam(examRoot);
  }
});




  // ================= TOKEN =================
  // ================= TOKEN (HALAMAN LOGIN) =================
function fetchToken() {
  const info = document.getElementById("cbt-token-info");
  if (!rest) return;

  axios
    .get(rest + "token")
    .then((res) => {
      const data = res.data || {};
      let text = "";

      if (data.token) {
        text = data.token;
        if (data.expires) {
          // /cbt/v1/token mengirim expires = UNIX (detik)
          const exp = new Date(data.expires * 1000);
          text += " | Exp: " + exp.toLocaleTimeString();
        }
      } else {
        text = "Token belum tersedia";
      }

      if (info) info.textContent = text;
    })
    .catch((err) => {
      console.error("Gagal ambil token:", err);
      if (info) info.textContent = "Gagal mengambil token.";
    });
}

// ================= LIST MAPEL (UNTUK SELECT) =================
function loadMapel() {
  const selLogin = document.getElementById("cbt-test-code");
  const selReg   = document.getElementById("cbt-reg-mapel");

  // Kalau dua-duanya nggak ada (bukan front page), skip
  if (!selLogin && !selReg) return;
  if (!rest) return;

  // Placeholder dulu
  if (selLogin) {
    selLogin.innerHTML = '<option value="">Memuat mapel...</option>';
  }
  if (selReg) {
    selReg.innerHTML = '<option value="">Memuat mapel...</option>';
  }

  axios
    .get(rest + "tests") // endpoint: /cbt/v1/tests
    .then((res) => {
      const data  = res.data || {};
      const tests = data.tests || data; // fleksibel bentuk respons

      if (!Array.isArray(tests) || tests.length === 0) {
        if (selLogin) {
          selLogin.innerHTML =
            '<option value="">Belum ada mapel aktif</option>';
        }
        if (selReg) {
          selReg.innerHTML =
            '<option value="">Belum ada mapel aktif</option>';
        }
        return;
      }

      let optionsHtml = '<option value="">Pilih mapel...</option>';
      tests.forEach((t) => {
        const code = t.code || "";
        const name = t.name || code;
        if (!code) return;

        // sesuai permintaan: pakai name saja, nggak usah panjang
        const label = name;

        optionsHtml +=
          '<option value="' +
          code.replace(/"/g, "&quot;") +
          '">' +
          label.replace(/</g, "&lt;") +
          "</option>";
      });

      if (selLogin) selLogin.innerHTML = optionsHtml;
      if (selReg) selReg.innerHTML = optionsHtml;
    })
    .catch((err) => {
      console.error("Gagal load mapel:", err);
      if (selLogin) {
        selLogin.innerHTML =
          '<option value="">Gagal memuat mapel</option>';
      }
      if (selReg) {
        selReg.innerHTML =
          '<option value="">Gagal memuat mapel</option>';
      }
    });
}

// ================= DAFTAR PESERTA (SELF REGISTER) =================
function initRegisterUI() {
  const toggleBtn  = document.getElementById("cbt-toggle-register");
  const loginPanel = document.getElementById("cbt-login-panel");
  const regPanel   = document.getElementById("cbt-register-panel");
  const regBtn     = document.getElementById("cbt-register-btn");

  // Kalau elemen tidak ada (admin matikan self-registration), skip
  if (!loginPanel || !regPanel) return;

  // default: login tampil, register hidden
  regPanel.style.display = "none";

  if (toggleBtn) {
    let showingRegister = false;
    toggleBtn.addEventListener("click", () => {
      showingRegister = !showingRegister;
      if (showingRegister) {
        loginPanel.style.display = "none";
        regPanel.style.display = "block";
        toggleBtn.textContent = "Sudah punya akun? Kembali ke login";
      } else {
        loginPanel.style.display = "block";
        regPanel.style.display = "none";
        toggleBtn.textContent = "Belum punya akun? Daftar di sini";
      }
    });
  }

  if (regBtn) {
    regBtn.addEventListener("click", handleRegister);
  }
}

function handleRegister() {
  const fullName = document.getElementById("cbt-reg-fullname")?.value.trim();
  const nis      = document.getElementById("cbt-reg-nis")?.value.trim();
  const user     = document.getElementById("cbt-reg-username")?.value.trim();
  const pass     = document.getElementById("cbt-reg-password")?.value;
  const mapel    = document.getElementById("cbt-reg-mapel")?.value;
  const ket1     = document.getElementById("cbt-reg-ket1")?.value.trim();
  const ket2     = document.getElementById("cbt-reg-ket2")?.value.trim();
  const ket3     = document.getElementById("cbt-reg-ket3")?.value.trim();
  const sesi     = document.getElementById("cbt-reg-sesi")?.value.trim();

  if (!fullName || !user || !pass || !mapel) {
    Swal.fire(
      "Perhatian",
      "Nama lengkap, username, password, dan mapel wajib diisi.",
      "warning"
    );
    return;
  }

  Swal.fire({
    title: "Mendaftarkan peserta...",
    didOpen: () => Swal.showLoading(),
    allowOutsideClick: false,
  });

  axios
    .post(
      rest + "register",
      {
        full_name: fullName,
        nis: nis,
        username: user,
        password: pass,
        mapel_code: mapel,
        ket1: ket1,
        ket2: ket2,
        ket3: ket3,
        sesi: sesi,
      },
      {
        headers: {
          "X-WP-Nonce": nonce,
        },
      }
    )
    .then((res) => {
      Swal.close();
      const d = res.data || {};
      Swal.fire(
        "Berhasil",
        d.message ||
          "Pendaftaran berhasil. Silakan login dengan username & password yang baru dibuat.",
        "success"
      ).then(() => {
        // isi otomatis field login biar enak
        const loginUser = document.getElementById("cbt-username");
        const loginPass = document.getElementById("cbt-password");
        const loginMapel = document.getElementById("cbt-test-code");
        if (loginUser) loginUser.value = user;
        if (loginPass) loginPass.value = pass;
        if (loginMapel && mapel) loginMapel.value = mapel;

        // balik ke panel login kalau tombol toggle ada
        const toggleBtn = document.getElementById("cbt-toggle-register");
        const loginPanel = document.getElementById("cbt-login-panel");
        const regPanel = document.getElementById("cbt-register-panel");
        if (toggleBtn && loginPanel && regPanel) {
          loginPanel.style.display = "block";
          regPanel.style.display = "none";
          toggleBtn.textContent = "Belum punya akun? Daftar di sini";
        }
      });
    })
    .catch((err) => {
      Swal.close();
      let msg = "Gagal mendaftarkan peserta.";
      if (err.response && err.response.data && err.response.data.message) {
        msg = err.response.data.message;
      }
      console.error("Register error:", err);
      Swal.fire("Error", msg, "error");
    });
}


    // ================= MAPEL / TEST LIST (LOGIN) =================
  function initMapelDropdown() {
  if (!rest) return;

  const el = document.getElementById("cbt-test-code");
  if (!el) return;

  axios
    .get(rest + "tests")
    .then((res) => {
      const data  = res.data || {};
      const tests = data.tests || [];
      const hideChoice = data.hide_subject_choice === 1;

      const availableForAuto = tests.filter((t) => t.is_today || t.available_now);

      // auto pilih kalau cuma 1 mapel & hide_subject_choice = 1
      if (hideChoice && availableForAuto.length === 1) {
        const t = availableForAuto[0];
        el.value = t.code;

        const wrapper = el.closest(".cbt-mapel-group");
        if (wrapper) wrapper.style.display = "none";

        const info = document.getElementById("cbt-selected-subject");
        if (info) {
          info.textContent = t.name; // ⬅️ cuma name
        }
        return;
      }

      // isi option normal
      el.innerHTML = "";

      const placeholder = document.createElement("option");
      placeholder.value = "";
      placeholder.textContent = "Pilih Mapel / Kode Ujian";
      placeholder.disabled = true;
      placeholder.selected = true;
      el.appendChild(placeholder);

      tests.forEach((t) => {
        const opt = document.createElement("option");
        opt.value = t.code; // ⬅️ tetap pakai kode sebagai value

        // ⬇️ label pendek: cuma name, plus ★ jika sedang jam ujian
        let label = t.name || t.code;
        if (t.available_now) {
          label = "★ " + label;
        }

        opt.textContent = label;
        el.appendChild(opt);
      });
    })
    .catch((err) => {
      console.error("Gagal load mapel:", err);
    });
}


  // ================= LOGIN =================
  function handleLogin() {
    const u = document.getElementById("cbt-username").value.trim();
    const p = document.getElementById("cbt-password").value;
    const t = document.getElementById("cbt-token").value.trim();
    const code = document.getElementById("cbt-test-code").value.trim();

    if (!u || !p || !t || !code) {
      Swal.fire("Perhatian", "Lengkapi semua data login.", "warning");
      return;
    }

    Swal.fire({
      title: "Memproses...",
      didOpen: () => Swal.showLoading(),
      allowOutsideClick: false,
    });

    axios
      .post(
        rest + "login",
        {
          username: u,
          password: p,
          mapel: code,
          token: t,
          test_code: code,
        },
        {
          headers: {
            "X-WP-Nonce": nonce,
          },
        }
      )
      .then((res) => {
        Swal.close();
        const d = res.data;

        // Simpan info sesi di localStorage
        try {
                  localStorage.setItem(
          "CBT_SESSION_INFO_" + d.session_id,
          JSON.stringify({
            session_id: d.session_id,
            session_slug: d.session_slug,
            end_timestamp: d.end_timestamp,
            session_auth: d.session_auth || "",
            student_name: d.student_name || "",          // dari PHP lama
            participant_name: d.participant_name || "",  // kalau nanti kamu ganti nama field
          })
        );

        } catch (e) {}

        const slug = d.session_slug || "S" + d.session_id;
        window.location.href =
          CBT_GLOBAL.site_url + "cbt/?session=" + encodeURIComponent(slug);
      })
      .catch((err) => {
        Swal.close();
        let msg = "Gagal login.";
        if (err.response && err.response.data && err.response.data.message) {
          msg = err.response.data.message;
        }
        Swal.fire("Error", msg, "error");
      });
  }

  // ================= EXAM STATE =================
let examState = {
  sessionId: null,
  questions: [],
  currentIndex: 0,
  endTimestamp: null,
  timerInterval: null,
  sessionAuth: null,
  studentName: "",
  participantName: ""
};

// Hitung statistik jawaban (kosong & ragu)
function getAnswerStats() {
  let unanswered = 0;
  let ragu = 0;

  (examState.questions || []).forEach((q) => {
    if (!q.current_answer) {
      unanswered++;
    }
    if (q.is_ragu) {
      ragu++;
    }
  });

  return { unanswered, ragu };
}


  // ================= NAV BUTTON CLASS =================
  function getNavBtnClass(q, idx) {
    const base =
      "cbt-nav-btn inline-flex items-center justify-center w-8 h-8 text-xs rounded-md border " +
      "transition-colors duration-150";
    const isActive = idx === examState.currentIndex;
    const answered = !!q.current_answer;
    const isRagu = !!q.is_ragu;

    if (isActive) {
      return (
        base +
        " border-blue-400 bg-blue-500 text-white shadow-sm ring-1 ring-blue-300/40"
      );
    }
    if (isRagu) {
      return (
        base +
        " border-amber-300 bg-amber-300 text-slate-900 shadow-sm ring-1 ring-amber-200/60"
      );
    }
    if (answered) {
      return (
        base +
        " border-emerald-400 bg-emerald-500 text-white shadow-sm ring-1 ring-emerald-300/40"
      );
    }
    return (
      base +
      " border-slate-500/60 bg-slate-800/80 text-slate-100 hover:bg-slate-700"
    );
  }

  // ================= INIT EXAM PAGE =================
  function initExam(root) {
    const sessionId = parseInt(root.dataset.sessionId || "0", 10);
    if (!sessionId) {
      root.innerHTML =
        '<p class="text-center text-red-400">Session ID tidak valid.</p>';
      return;
    }

    examState.sessionId = sessionId;

    // 🔑 Ambil cache dari localStorage
    let cache = null;
    try {
      cache = JSON.parse(
        localStorage.getItem("CBT_SESSION_INFO_" + sessionId) || "null"
      );
    } catch (e) {
      cache = null;
    }

    if (!cache || !cache.session_auth) {
      root.innerHTML =
        '<p class="text-center text-red-400">Sesi ujian tidak valid atau sudah berakhir.<br>Silakan login ulang.</p>';
      setTimeout(() => {
        window.location.href = CBT_GLOBAL.site_url;
      }, 3000);
      return;
    }

    examState.sessionAuth = cache.session_auth;
examState.studentName = cache.student_name || "";
if (cache.end_timestamp) {
  examState.endTimestamp = cache.end_timestamp;
}
if (cache.participant_name) {
  examState.participantName = cache.participant_name;
}


    // Baru kemudian panggil API /exam dengan session_auth
    axios
      .get(rest + "exam", {
        params: {
          session_id: sessionId,
          session_auth: examState.sessionAuth, // 🔑 kirim ke server
        },
      })
      .then((res) => {
        const d = res.data;
        examState.questions = d.questions || [];
        examState.currentIndex = (d.last_question || 1) - 1;
        if (examState.currentIndex < 0) examState.currentIndex = 0;

        // fallback endTimestamp kalau belum ada
        if (!examState.endTimestamp) {
          examState.endTimestamp = Date.now() + 30 * 60 * 1000;
        }

        renderExamUI(root, d);
        startTimer();
      })
      .catch((err) => {
        console.error(err);
        root.innerHTML =
          '<p class="text-center text-red-400">Gagal memuat ujian.</p>';
      });
  }

  // ================= RENDER EXAM UI =================
  function renderExamUI(root, examMeta) {
    const q = examState.questions;
    if (!q.length) {
      root.innerHTML =
        '<p class="text-center text-yellow-400 mt-6">Belum ada soal untuk tes ini.</p>';
      return;
    }

    const navButtons = q
      .map(
        (x, idx) =>
          `<button data-idx="${idx}" class="${getNavBtnClass(
            x,
            idx
          )}">${x.display_no}</button>`
      )
      .join("");

const participantName =
  examMeta.participant_name ||
  examMeta.user_name ||
  examMeta.student_name ||
  examMeta.username ||
  examState.participantName || // ⬅️ ambil dari localStorage kalau ada
  "-";



    root.innerHTML = `
  <div class="flex flex-col gap-4 cbt-exam-wrapper px-3 py-4 md:px-4 md:py-6">
    <!-- Header -->
    <div class="rounded-2xl px-4 py-3 md:px-6 md:py-4 border shadow-md
                bg-gradient-to-r from-blue-900/90 via-blue-800/90 to-blue-900/90
                text-slate-50 border-slate-700/80">
      <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3">
        <div>
          <div class="text-xs uppercase tracking-wide text-slate-200 mb-1">
            Ujian Berbasis Komputer
          </div>
          <h2 class="text-lg md:text-xl font-semibold">
            ${examMeta.test_name || "Ujian CBT"}
          </h2>

          <div class="mt-2 flex flex-col gap-1">
            <div class="text-[11px] md:text-xs text-slate-200">
              Kode Ujian:
              <span class="font-mono font-semibold">
                ${examMeta.test_code || "-"}
              </span>
            </div>

            <div class="inline-flex items-center gap-2 mt-1">
              <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold
                           bg-slate-900/70 border border-slate-500/60 uppercase tracking-wide">
                Peserta
              </span>
              <span class="text-sm md:text-base font-semibold text-white truncate
                           max-w-[220px] md:max-w-xs">
                ${examState.participantName || examState.studentName || "-"}
              </span>
            </div>
          </div>
        </div>

        <div class="flex items-center gap-3 justify-between md:justify-end">
          <div class="text-right">
            <div class="text-[11px] uppercase tracking-wide text-slate-200">
              Sisa Waktu
            </div>
            <div id="cbt-timer"
                 class="text-2xl md:text-3xl font-semibold mt-1 font-mono drop-shadow">
              --:--:--
            </div>
          </div>
        </div>
      </div>
    </div>

        <!-- Grid utama -->
        <div class="grid gap-4 md:grid-cols-[minmax(0,3fr)_minmax(220px,1fr)] items-start">
          <!-- Soal -->
          <div class="cbt-panel bg-white rounded-2xl shadow-lg p-4 md:p-6 backdrop-blur-sm text-slate-100">
            <div id="cbt-question-container"></div>
          </div>

          <!-- Navigasi soal -->
          <div class="cbt-panel bg-slate-900/90 border border-slate-700/80 rounded-2xl shadow-lg p-3 md:p-4 backdrop-blur-sm text-slate-100">
            <div class="flex items-center justify-between gap-2 mb-2">
              <div>
                <div class="text-xs font-semibold tracking-wide text-slate-100">Navigasi Soal</div>
                <div class="text-[11px] text-slate-400">
                  Ketuk nomor untuk berpindah.
                </div>
              </div>
            </div>

            <div class="text-[10px] text-slate-400 mb-3 flex flex-wrap gap-2">
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-blue-500/90 text-white">
                <span class="w-2 h-2 rounded-full bg-white"></span> Aktif
              </span>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-emerald-500/90 text-white">
                <span class="w-2 h-2 rounded-full bg-white"></span> Terjawab
              </span>
              <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-amber-300 text-slate-900">
                <span class="w-2 h-2 rounded-full bg-slate-900/70"></span> Ragu
              </span>
            </div>

            <div class="flex flex-wrap gap-2 mb-4 max-h-60 overflow-y-auto pr-1">
              ${navButtons}
            </div>

            <div class="flex flex-col gap-2 pt-2 border-t border-slate-700/80">
              <button id="cbt-btn-prev"
                class="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-sm font-medium transition-colors text-slate-100">
                Sebelumnya
              </button>
              <button id="cbt-btn-next"
                class="w-full py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-sm font-medium transition-colors text-slate-100">
                Berikutnya
              </button>
              <button id="cbt-btn-finish"
                class="w-full py-2 rounded-xl bg-red-600 hover:bg-red-500 text-sm font-semibold shadow-md shadow-red-900/40 text-white mt-1">
                Selesai Ujian
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    root
      .querySelectorAll(".cbt-nav-btn")
      .forEach((btn) =>
        btn.addEventListener("click", () =>
          goToQuestion(parseInt(btn.dataset.idx, 10))
        )
      );
    root
      .querySelector("#cbt-btn-prev")
      .addEventListener("click", () => moveQuestion(-1));
    root
      .querySelector("#cbt-btn-next")
      .addEventListener("click", () => moveQuestion(1));
    root
      .querySelector("#cbt-btn-finish")
      .addEventListener("click", handleFinish);

    renderCurrentQuestion();
  }

  // ================= RENDER CURRENT QUESTION =================
  function renderCurrentQuestion() {
    const root = document.getElementById("cbt-exam-root");
    if (!root) return;
    const container = root.querySelector("#cbt-question-container");
    if (!container) return;

    const idx = examState.currentIndex;
    const q = examState.questions[idx];
    if (!q) return;

    const optionsOrder = ["A", "B", "C", "D", "E"];
    const isRagu = !!q.is_ragu;

    const optsHtml = optionsOrder
  .map((k) => {
    const html = q.options && q.options[k] ? q.options[k] : "";
    const checked = q.current_answer === k ? "checked" : "";
    return `
        <label class="flex items-start gap-3 py-1.5 px-2 rounded-xl hover:bg-blue-900/90 cursor-pointer transition-colors">
          <div class="pt-0.5">
            <input type="radio" name="cbt-answer" value="${k}" ${checked}
              class="w-4 h-4 text-blue-500 border-slate-500 bg-slate-900 rounded-full">
          </div>
          <div class="text-sm leading-relaxed text-slate-900">
            <span class="font-semibold mr-1">${k}.</span>
            <span class="cbt-option-html">${html}</span>
          </div>
        </label>
      `;
  })
  .join("");


    container.innerHTML = `
      <div class="flex flex-col gap-4">
        <div class="flex items-center justify-between gap-2">
          <div class="text-xs uppercase tracking-wide text-slate-900">
            Soal ${q.display_no}
          </div>
          <div class="text-[11px] px-2 py-0.5 rounded-full border border-slate-600/70 bg-slate-900/70 text-slate-300">
            Jumlah soal: ${examState.questions.length}
          </div>
        </div>

        <div class=" max-w-none mb-2 cbt-question-content text-slate-900">
          ${q.content}
        </div>

        <div class="mt-1 space-y-1">
          ${optsHtml}
        </div>

        <label
          class="mt-3 flex items-center justify-between gap-3 cursor-pointer 
                 py-1.5 px-2 border border-slate-600/70 rounded-xl"
        >
          <span class="inline-flex items-center gap-2 text-sm text-amber-500">
            <input
              type="checkbox"
              id="cbt-ragu"
              class="w-4 h-4 cursor-pointer"
              ${isRagu ? "checked" : ""}
            >
            <span>Ragu-ragu pada soal ini</span>
          </span>
        
          <span class="text-[11px] text-slate-400">
            Gunakan <span class="font-semibold">Ragu</span> jika ingin meninjau ulang.
          </span>
        </label>

      </div>
    `;

    container
      .querySelectorAll("input[name='cbt-answer']")
      .forEach((r) =>
        r.addEventListener("change", (ev) =>
          saveAnswer(q, ev.target.value)
        )
      );

    const raguCheckbox = container.querySelector("#cbt-ragu");
    if (raguCheckbox) {
      raguCheckbox.addEventListener("change", (ev) =>
        toggleRagu(q, ev.target.checked)
      );
    }

    root.querySelectorAll(".cbt-nav-btn").forEach((b, i) => {
      const qq = examState.questions[i];
      b.className = getNavBtnClass(qq, i);
    });
  }

  function moveQuestion(step) {
    const next = examState.currentIndex + step;
    if (next < 0 || next >= examState.questions.length) return;
    examState.currentIndex = next;
    renderCurrentQuestion();
  }

  function goToQuestion(idx) {
    if (idx < 0 || idx >= examState.questions.length) return;
    examState.currentIndex = idx;
    renderCurrentQuestion();
  }

  // ================= JAWABAN & RAGU =================
  function saveAnswer(question, ans) {
    axios
      .post(
        rest + "answer",
        {
          session_id: examState.sessionId,
          session_auth: examState.sessionAuth,        // 🔑
          question_post_id: question.post_id,
          question_no: question.number,
          answer: ans,
        },
        {
          headers: {
            "X-WP-Nonce": nonce,
          },
        }
      )

      .then(() => {
        question.current_answer = ans;

        const root = document.getElementById("cbt-exam-root");
        if (root) {
          root.querySelectorAll(".cbt-nav-btn").forEach((b, i) => {
            const qq = examState.questions[i];
            b.className = getNavBtnClass(qq, i);
          });
        }
      })
      .catch((err) => {
        console.error(err);
        Swal.fire(
          "Error",
          "Gagal menyimpan jawaban. Periksa koneksi.",
          "error"
        );
      });
  }

  function toggleRagu(question, isRagu) {
    question.is_ragu = isRagu;

    const root = document.getElementById("cbt-exam-root");
    if (root) {
      root.querySelectorAll(".cbt-nav-btn").forEach((b, i) => {
        const qq = examState.questions[i];
        b.className = getNavBtnClass(qq, i);
      });
    }
  }

// ================= FINISH / SELESAI UJIAN =================
function handleFinish() {
  const stats = getAnswerStats();
  let infoText = "Pastikan semua soal sudah Anda kerjakan.";

  if (stats.unanswered > 0 || stats.ragu > 0) {
    infoText =
      "Ringkasan jawaban Anda:\n" +
      "- Soal belum dijawab: " + stats.unanswered + "\n" +
      "- Soal ditandai ragu: " + stats.ragu + "\n\n" +
      "Anda tetap ingin menyelesaikan ujian?";
  }

  Swal.fire({
    title: "Selesai Ujian?",
    text: infoText,
    icon: "warning",
    showCancelButton: true,
    confirmButtonText: "Ya, Selesai",
    cancelButtonText: "Batal",
  }).then((res) => {
    if (res.isConfirmed) {
      finishExam();
    }
  });
}

function finishExam() {
  axios
    .post(
      rest + "finish",
      {
        session_id: examState.sessionId,
        session_auth: examState.sessionAuth || null,
      },
      {
        headers: {
          "X-WP-Nonce": nonce,
        },
      }
    )
    .then((res) => {
      const d = res.data || {};
      const score =
        typeof d.score !== "undefined" && d.score !== null ? d.score : 0;

      Swal.fire({
        title: "Ujian Selesai",
        html: "Nilai Anda: <b>" + score + "</b>",
        icon: "success",
      }).then(() => {
        window.location.href = CBT_GLOBAL.site_url;
      });
    })
    .catch((err) => {
      console.log("Finish error RAW:", err);
      console.log(
        "Finish error RESPONSE DATA:",
        err && err.response && err.response.data
      );

      let msg = "Gagal menyelesaikan ujian. Coba lagi.";

      if (err.response && err.response.data) {
        const data = err.response.data;

        // 👇 ambil langsung message dari REST API WP
        if (typeof data.message === "string" && data.message.trim() !== "") {
          msg = data.message; // contoh: "Anda belum menjawab semua soal (0 / 30)."
        }
      }

      Swal.fire({
        icon: "error",
        title: "Tidak bisa mengumpulkan",
        text: msg,
      });
    });
}



  // ================= TIMER =================
  function startTimer() {
    const el = document.getElementById("cbt-timer");
    if (!el) return;

    function tick() {
      const now = Date.now();
      const diff = examState.endTimestamp - now;
      if (diff <= 0) {
        el.textContent = "00:00:00";
        clearInterval(examState.timerInterval);
        Swal.fire({
          title: "Waktu Habis",
          text: "Waktu ujian Anda sudah berakhir. Sistem akan mengumpulkan jawaban.",
          icon: "warning",
          allowOutsideClick: false,
        }).then(() => {
          finishExam();
        });
        return;
      }
      const sec = Math.floor(diff / 1000);
      const h = String(Math.floor(sec / 3600)).padStart(2, "0");
      const m = String(Math.floor((sec % 3600) / 60)).padStart(2, "0");
      const s = String(sec % 60).padStart(2, "0");
      el.textContent = `${h}:${m}:${s}`;
    }

    tick();
    examState.timerInterval = setInterval(tick, 1000);
  }
})();
