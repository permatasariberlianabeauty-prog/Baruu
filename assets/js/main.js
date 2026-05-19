/**
 * NOXARA — Main JavaScript
 */
'use strict';

// ── Accordion ────────────────────────────────────────────
document.querySelectorAll('.accordion-header').forEach(header => {
  header.addEventListener('click', () => {
    const item = header.closest('.accordion-item');
    const wasOpen = item.classList.contains('open');
    // Close all in same parent
    item.closest('.accordion-list, .accordion')?.querySelectorAll('.accordion-item.open')
      .forEach(i => i.classList.remove('open'));
    if (!wasOpen) item.classList.add('open');
  });
});

// ── Tabs ─────────────────────────────────────────────────
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const group  = btn.closest('[data-tabs]') || btn.closest('.tabs-wrapper');
    const target = btn.dataset.tab;
    group?.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    group?.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById(target)?.classList.add('active');
  });
});

// ── Copy to clipboard ────────────────────────────────────
function copyText(text, btn) {
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent;
    btn.textContent = 'Tersalin!';
    btn.style.color = 'var(--text-success)';
    setTimeout(() => { btn.textContent = orig; btn.style.color = ''; }, 2000);
  });
}
document.querySelectorAll('[data-copy]').forEach(btn => {
  btn.addEventListener('click', () => copyText(btn.dataset.copy, btn));
});

// ── Password visibility toggle ────────────────────────────
document.querySelectorAll('[data-pw-toggle]').forEach(btn => {
  btn.addEventListener('click', () => {
    const input = document.getElementById(btn.dataset.pwToggle);
    if (!input) return;
    if (input.type === 'password') {
      input.type = 'text';
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M17.94 17.94A10.07 10.07 0 0112 20c-7 0-11-8-11-8a18.45 18.45 0 015.06-5.94M9.9 4.24A9.12 9.12 0 0112 4c7 0 11 8 11 8a18.5 18.5 0 01-2.16 3.19m-6.72-1.07a3 3 0 11-4.24-4.24" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/><line x1="1" y1="1" x2="23" y2="23" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    } else {
      input.type = 'password';
      btn.innerHTML = '<svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z" stroke="currentColor" stroke-width="1.7" fill="none"/><circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.7" fill="none"/></svg>';
    }
  });
});

// ── Banner Slider ─────────────────────────────────────────
function initBannerSlider(wrapper) {
  const track  = wrapper.querySelector('.banner-track');
  const slides = wrapper.querySelectorAll('.banner-slide');
  const dots   = wrapper.querySelectorAll('.banner-dot');
  if (!slides.length) return;

  let current = 0;
  const go = (idx) => {
    current = (idx + slides.length) % slides.length;
    track.style.transform = `translateX(-${current * 100}%)`;
    dots.forEach((d, i) => d.classList.toggle('active', i === current));
  };

  dots.forEach((d, i) => d.addEventListener('click', () => go(i)));
  wrapper.querySelector('.banner-prev')?.addEventListener('click', () => go(current - 1));
  wrapper.querySelector('.banner-next')?.addEventListener('click', () => go(current + 1));

  // Auto-play
  const timer = setInterval(() => go(current + 1), 4000);
  wrapper.addEventListener('mouseenter', () => clearInterval(timer));

  go(0);
}
document.querySelectorAll('.banner-slider').forEach(initBannerSlider);

// ── Mining countdown timer ────────────────────────────────
document.querySelectorAll('[data-countdown]').forEach(el => {
  let seconds = parseInt(el.dataset.countdown) || 0;
  if (seconds <= 0) { el.textContent = 'Siap Mining!'; return; }

  const tick = () => {
    if (seconds <= 0) {
      el.textContent = 'Siap Mining!';
      el.closest('.package-card')?.querySelector('.btn-mine')?.classList.remove('mined');
      return;
    }
    const h = Math.floor(seconds / 3600);
    const m = Math.floor((seconds % 3600) / 60);
    const s = seconds % 60;
    el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    seconds--;
    setTimeout(tick, 1000);
  };
  tick();
});

// ── Coin drop animation ───────────────────────────────────
function dropCoins(count = 8) {
  const colors = ['💰','💎','⭐','✨'];
  for (let i = 0; i < count; i++) {
    setTimeout(() => {
      const coin = document.createElement('div');
      coin.className = 'coin-drop';
      coin.textContent = colors[i % colors.length];
      coin.style.left   = `${20 + Math.random() * 60}%`;
      coin.style.top    = `${10 + Math.random() * 30}%`;
      coin.style.animationDelay = `${Math.random() * .4}s`;
      document.body.appendChild(coin);
      setTimeout(() => coin.remove(), 1500);
    }, i * 80);
  }
}

// ── Confetti ──────────────────────────────────────────────
function launchConfetti(count = 60) {
  const colors = ['#00D4FF','#7B2FFF','#FFD700','#FF4466','#00FF88'];
  const container = document.createElement('div');
  container.className = 'confetti-container';
  document.body.appendChild(container);

  for (let i = 0; i < count; i++) {
    const piece = document.createElement('div');
    piece.className = 'confetti-piece';
    piece.style.left     = `${Math.random() * 100}%`;
    piece.style.top      = '-10px';
    piece.style.background = colors[Math.floor(Math.random() * colors.length)];
    piece.style.transform  = `rotate(${Math.random() * 360}deg)`;
    piece.style.animationDelay = `${Math.random() * 1.5}s`;
    piece.style.animationDuration = `${2 + Math.random() * 1.5}s`;
    const size = 4 + Math.random() * 8;
    piece.style.width  = size + 'px';
    piece.style.height = size + 'px';
    if (Math.random() > .5) piece.style.borderRadius = '50%';
    container.appendChild(piece);
  }
  setTimeout(() => container.remove(), 4000);
}

// ── Form submit loading ───────────────────────────────────
document.querySelectorAll('form[data-loading]').forEach(form => {
  form.addEventListener('submit', () => {
    const btn = form.querySelector('[type="submit"]');
    if (btn && !btn.disabled) {
      btn.disabled = true;
      btn.dataset.origText = btn.textContent;
      btn.innerHTML = '<span class="spin" style="display:inline-block;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;vertical-align:middle"></span> Memproses...';
    }
  });
});

// ── Number input: format rupiah preview ───────────────────
document.querySelectorAll('[data-rupiah-preview]').forEach(input => {
  const preview = document.getElementById(input.dataset.rupiahPreview);
  if (!preview) return;
  input.addEventListener('input', () => {
    const val = parseInt(input.value.replace(/\D/g,'')) || 0;
    preview.textContent = 'Rp' + val.toLocaleString('id-ID');
  });
});

// ── PIN input: auto-advance ───────────────────────────────
document.querySelectorAll('.pin-input-group').forEach(group => {
  const inputs = [...group.querySelectorAll('input')];
  inputs.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      if (inp.value.length >= 1 && i < inputs.length - 1) inputs[i + 1].focus();
    });
    inp.addEventListener('keydown', e => {
      if (e.key === 'Backspace' && !inp.value && i > 0) inputs[i - 1].focus();
    });
  });
});

// ── Voucher check ─────────────────────────────────────────
const voucherInput = document.getElementById('voucherCode');
const voucherBtn   = document.getElementById('checkVoucherBtn');
if (voucherInput && voucherBtn) {
  voucherBtn.addEventListener('click', async () => {
    const code   = voucherInput.value.trim();
    const amount = document.getElementById('depositAmount')?.value || 0;
    if (!code) return;
    voucherBtn.disabled = true; voucherBtn.textContent = 'Memeriksa...';

    try {
      const res  = await fetch(`${BASE_URL}/api/wallet.php?action=check_voucher&code=${encodeURIComponent(code)}&amount=${amount}`, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const data = await res.json();
      const info = document.getElementById('voucherInfo');
      if (info) {
        info.textContent = data.message;
        info.className   = data.success ? 'form-hint text-success' : 'form-error';
      }
      if (data.success && data.data?.voucher_id) {
        document.getElementById('voucherIdInput').value = data.data.voucher_id;
      }
    } catch(e) {
      console.error(e);
    } finally {
      voucherBtn.disabled = false; voucherBtn.textContent = 'Cek Voucher';
    }
  });
}

// ── Theme toggle ──────────────────────────────────────────
function toggleTheme() {
  const html    = document.documentElement;
  const current = html.dataset.theme === 'dark' ? 'light' : 'dark';
  html.dataset.theme = current;
  fetch(`${BASE_URL}/api/wallet.php?action=set_theme&theme=${current}`, {
    headers: {'X-Requested-With': 'XMLHttpRequest'}
  });
}

// ── Infinite scroll helper ────────────────────────────────
function initInfiniteScroll(container, loadMore) {
  const observer = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) loadMore();
  }, { threshold: .1 });
  const sentinel = document.createElement('div');
  container.appendChild(sentinel);
  observer.observe(sentinel);
}

// ── Debounce ──────────────────────────────────────────────
function debounce(fn, delay = 300) {
  let t;
  return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), delay); };
}

// ── Search live filter ────────────────────────────────────
document.querySelectorAll('[data-search-target]').forEach(input => {
  const targetSelector = input.dataset.searchTarget;
  const items = document.querySelectorAll(targetSelector);

  input.addEventListener('input', debounce(() => {
    const q = input.value.toLowerCase();
    items.forEach(item => {
      const text = item.textContent.toLowerCase();
      item.style.display = text.includes(q) ? '' : 'none';
    });
  }));
});

// ── Mining button handler ─────────────────────────────────
document.querySelectorAll('.btn-mine[data-package-id]').forEach(btn => {
  btn.addEventListener('click', async function() {
    if (this.classList.contains('mined') || this.disabled) return;
    const pkgId  = this.dataset.packageId;
    const origTxt = this.textContent;
    this.disabled = true; this.textContent = 'Mining...';

    try {
      const res  = await fetch(`${BASE_URL}/api/mining.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': CSRF_TOKEN, 'X-Requested-With': 'XMLHttpRequest' },
        body: `action=mine&package_id=${pkgId}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
      });
      const data = await res.json();
      if (data.success) {
        this.classList.add('mined');
        this.textContent = 'Sudah Mining';
        showToast('⛏️ ' + data.message, 'success');
        dropCoins(10);
        // Update countdown
        const card = this.closest('.package-card');
        const cdEl = card?.querySelector('[data-countdown]');
        if (cdEl) { cdEl.dataset.countdown = 3 * 3600; }
      } else {
        showToast(data.message, 'error');
        this.disabled = false; this.textContent = origTxt;
      }
    } catch(e) {
      showToast('Terjadi kesalahan. Coba lagi.', 'error');
      this.disabled = false; this.textContent = origTxt;
    }
  });
});
