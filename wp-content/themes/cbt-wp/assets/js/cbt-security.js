// assets/js/cbt-security.js
(function () {
  // ================== CONFIG DASAR ==================
  const SEC_CONFIG = {
    enableOnExamOnly: true,      // true = fitur berat cuma aktif kalau ada #cbt-exam-root
    maxLeaveCount: 5,            // berapa kali boleh pindah tab sebelum peringatan keras
    warnThreshold: 3,            // mulai diperingatkan sejak n kali
    devtoolsCheckInterval: 2500, // ms cek devtools
  };

  // ================== UTIL: ANTI-SPAM ALERT ==================
  let __lastWarnAt = 0;
  function safeWarn(showFn, minGapMs = 800) {
    const now = Date.now();
    if (now - __lastWarnAt > minGapMs) {
      __lastWarnAt = now;
      try {
        showFn();
      } catch (e) {
        console.error(e);
      }
    }
  }

  const __warnCh = new Map();
  function safeWarnCh(channel, showFn, minGapMs = 800) {
    const now = Date.now();
    const last = __warnCh.get(channel) || 0;
    if (now - last > minGapMs) {
      __warnCh.set(channel, now);
      try {
        showFn();
      } catch (e) {
        console.error(e);
      }
    }
  }

  function showSwal(title, text, icon) {
    if (typeof Swal !== "undefined") {
      Swal.fire({
        title,
        text,
        icon,
        confirmButtonText: "OK",
      });
    } else {
      alert(title + "\n\n" + text);
    }
  }

  // ================== STATE KEAMANAN ==================
  const secState = {
    isExamPage: false,
    leaveCount: 0,
    blurCount: 0,
    wakeLock: null,
    devtoolsOpen: false,
    fullscreenBound: false,
  };

  function detectExamMode() {
    const examRoot = document.getElementById("cbt-exam-root");
    if (SEC_CONFIG.enableOnExamOnly) {
      secState.isExamPage = !!examRoot;
    } else {
      secState.isExamPage = true;
    }
  }

  // ================== WAKE LOCK (CEGAH LAYAR SLEEP) ==================
  async function requestWakeLock() {
    if (!("wakeLock" in navigator)) return;
    try {
      const lock = await navigator.wakeLock.request("screen");
      secState.wakeLock = lock;
      lock.addEventListener("release", () => {
        console.log("[CBT-SEC] WakeLock dilepas");
      });
      console.log("[CBT-SEC] WakeLock aktif");
    } catch (e) {
      console.warn("[CBT-SEC] Gagal minta WakeLock", e);
    }
  }

  function setupWakeLock() {
    if (!secState.isExamPage) return;
    requestWakeLock();
    document.addEventListener("visibilitychange", () => {
      if (document.visibilityState === "visible") {
        requestWakeLock();
      }
    });
  }

  // ================== FULLSCREEN MODE ==================
  function isFullscreen() {
    return (
      document.fullscreenElement ||
      document.webkitFullscreenElement ||
      document.mozFullScreenElement ||
      document.msFullscreenElement
    );
  }

  function requestFullscreen() {
    const el = document.documentElement; // full tab, bukan cuma div
    if (el.requestFullscreen) return el.requestFullscreen();
    if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
    if (el.mozRequestFullScreen) return el.mozRequestFullScreen();
    if (el.msRequestFullscreen) return el.msRequestFullscreen();
    return Promise.reject(new Error("Fullscreen API tidak tersedia"));
  }

  function setupFullscreenGuard() {
    if (!secState.isExamPage || secState.fullscreenBound) return;
    secState.fullscreenBound = true;

    // Coba minta fullscreen di klik pertama (user gesture)
    const firstClickHandler = function () {
      if (!secState.isExamPage) return;
      if (!isFullscreen()) {
        requestFullscreen()
          .then(() => {
            console.log("[CBT-SEC] Fullscreen diaktifkan.");
          })
          .catch((e) => {
            console.warn("[CBT-SEC] Tidak bisa masuk fullscreen:", e);
          });
      }
      document.removeEventListener("click", firstClickHandler, true);
    };

    document.addEventListener("click", firstClickHandler, true);

    // Monitor kalau keluar fullscreen
    function fullscreenChangeHandler() {
      if (!secState.isExamPage) return;
      if (!isFullscreen()) {
        safeWarnCh("fullscreen-exit", () => {
          showSwal(
            "Mode Layar Penuh Dimatikan",
            "Anda keluar dari mode layar penuh. Selama ujian, harap tetap dalam mode fullscreen.",
            "warning"
          );
          if (navigator.vibrate) {
            navigator.vibrate([100, 60, 100]);
          }
        });
      }
    }

    document.addEventListener("fullscreenchange", fullscreenChangeHandler);
    document.addEventListener(
      "webkitfullscreenchange",
      fullscreenChangeHandler
    );
    document.addEventListener("mozfullscreenchange", fullscreenChangeHandler);
    document.addEventListener("MSFullscreenChange", fullscreenChangeHandler);
  }

  // ================== ANTI TAB PINDAH / VISIBILITY ==================
  function setupVisibilityGuard() {
    document.addEventListener("visibilitychange", () => {
      if (!secState.isExamPage) return;

      if (document.hidden) {
        secState.leaveCount++;
        secState.blurCount++;

        const msgShort =
          "Anda berpindah dari tampilan ujian. Aktivitas ini tercatat di sistem.";
        const msgHard =
          "Anda terlalu sering meninggalkan halaman ujian. Segera fokus kembali!";

        if (secState.leaveCount >= SEC_CONFIG.warnThreshold) {
          safeWarnCh("leave-hard", () => {
            showSwal("Peringatan!", msgHard, "warning");
            if (navigator.vibrate) {
              navigator.vibrate([120, 80, 120]);
            }
          });
        } else {
          safeWarnCh("leave-soft", () => {
            showSwal("Perhatian", msgShort, "info");
          });
        }

        console.log(
          "[CBT-SEC] visibilitychange hidden, leaveCount=" +
            secState.leaveCount
        );
      }
    });

    window.addEventListener("blur", () => {
      if (!secState.isExamPage) return;
      secState.blurCount++;
      console.log("[CBT-SEC] window blur, blurCount=" + secState.blurCount);
    });

    window.addEventListener("focus", () => {
      if (!secState.isExamPage) return;
      console.log("[CBT-SEC] window focus");
    });
  }

  // ================== ANTI DEVTOOLS SEDERHANA ==================
  function detectDevtools() {
    const threshold = 160; // px
    const widthDiff = window.outerWidth - window.innerWidth;
    const heightDiff = window.outerHeight - window.innerHeight;
    return widthDiff > threshold || heightDiff > threshold;
  }

  function setupDevtoolsGuard() {
    setInterval(() => {
      const opened = detectDevtools();
      if (opened && !secState.devtoolsOpen) {
        secState.devtoolsOpen = true;
        if (!secState.isExamPage) return;
        safeWarnCh("devtools", () => {
          showSwal(
            "Perhatian",
            "Penggunaan Developer Tools terdeteksi. Aktivitas ini dicatat dan dapat dianggap kecurangan.",
            "warning"
          );
          if (navigator.vibrate) {
            navigator.vibrate([150, 100, 150]);
          }
        });
      } else if (!opened && secState.devtoolsOpen) {
        secState.devtoolsOpen = false;
      }
    }, SEC_CONFIG.devtoolsCheckInterval);
  }

  // ================== ANTI KLIK KANAN & SHORTCUT ==================
  function setupContextMenuGuard() {
    document.addEventListener(
      "contextmenu",
      function (e) {
        if (!secState.isExamPage) return;
        e.preventDefault();
        safeWarnCh("ctx", () => {
          showSwal(
            "Diblokir",
            "Klik kanan dinonaktifkan selama ujian berlangsung.",
            "info"
          );
        });
      },
      { capture: true }
    );
  }

  function setupKeyGuard() {
    document.addEventListener(
      "keydown",
      function (e) {
        if (!secState.isExamPage) return;

        const key = e.key || e.keyCode;
        const ctrl = e.ctrlKey || e.metaKey;

        // F12
        if (key === "F12" || key === 123) {
          e.preventDefault();
          e.stopPropagation();
          safeWarnCh("k-f12", () => {
            showSwal("Diblokir", "F12 dinonaktifkan selama ujian.", "info");
          });
          return;
        }

        // Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U, Ctrl+S
        if (ctrl) {
          const k = (key + "").toLowerCase();
          if (
            (e.shiftKey && (k === "i" || k === "j")) ||
            k === "u" ||
            k === "s"
          ) {
            e.preventDefault();
            e.stopPropagation();
            safeWarnCh("k-dev", () => {
              showSwal(
                "Diblokir",
                "Shortcut tersebut dinonaktifkan selama ujian.",
                "info"
              );
            });
          }
        }
      },
      { capture: true }
    );
  }

  // ================== ANTI COPY / CUT / PASTE (OPSIONAL) ==================
  function setupCopyGuard() {
    document.addEventListener(
      "copy",
      function (e) {
        if (!secState.isExamPage) return;
        e.preventDefault();
        safeWarnCh("copy", () => {
          showSwal(
            "Diblokir",
            "Menyalin teks dari halaman ujian dinonaktifkan.",
            "info"
          );
        });
      },
      true
    );

    document.addEventListener(
      "cut",
      function (e) {
        if (!secState.isExamPage) return;
        e.preventDefault();
      },
      true
    );

    document.addEventListener(
      "paste",
      function (e) {
        if (!secState.isExamPage) return;
        // kalau mau blok paste, aktifkan:
        // e.preventDefault();
      },
      true
    );
  }

  // ================== BEFOREUNLOAD (KELUAR HALAMAN) ==================
  function setupBeforeUnloadGuard() {
    window.addEventListener("beforeunload", function (e) {
      if (!secState.isExamPage) return;
      const msg =
        "Anda sedang mengerjakan ujian. Yakin ingin meninggalkan halaman?";
      e.preventDefault();
      e.returnValue = msg;
      return msg;
    });
  }

  // ================== INIT ==================
  document.addEventListener("DOMContentLoaded", function () {
    // Deteksi apakah ini halaman ujian
    detectExamMode();

    // Guard dasar: jalan di semua halaman
    setupDevtoolsGuard();
    setupContextMenuGuard();
    setupKeyGuard();
    setupCopyGuard();

    // Fitur berat hanya ketika exam page
    if (secState.isExamPage) {
      console.log("[CBT-SEC] Mode keamanan ujian AKTIF");
      setupVisibilityGuard();
      setupBeforeUnloadGuard();
      setupWakeLock();
      setupFullscreenGuard();
    } else {
      console.log("[CBT-SEC] Bukan halaman ujian, mode ringan saja.");
    }
  });
})();
