/**
 * NOXARA — Animations JavaScript
 */
'use strict';

// ── Intersection Observer: Fade-in on scroll ──────────────
const animObserver = new IntersectionObserver((entries) => {
  entries.forEach(entry => {
    if (entry.isIntersecting) {
      entry.target.classList.add('fade-in-up');
      animObserver.unobserve(entry.target);
    }
  });
}, { threshold: 0.1 });

document.querySelectorAll('.animate-on-scroll').forEach(el => animObserver.observe(el));

// ── Hero Particle System ──────────────────────────────────
function initParticles(containerId) {
  const container = document.getElementById(containerId);
  if (!container) return;

  const count = 18;
  for (let i = 0; i < count; i++) {
    const p = document.createElement('div');
    p.className = 'particle';
    const size = 4 + Math.random() * 12;
    p.style.cssText = `
      width:${size}px; height:${size}px;
      left:${Math.random()*100}%;
      top:${Math.random()*100}%;
      animation-duration:${4+Math.random()*6}s;
      animation-delay:${Math.random()*4}s;
      background:${Math.random()>.5?'#00D4FF':'#7B2FFF'};
    `;
    container.appendChild(p);
  }
}

// ── Daily Reward Box Animation ────────────────────────────
function animateRewardBox(boxEl, callback) {
  if (!boxEl) return;
  boxEl.classList.add('gift-shake');

  setTimeout(() => {
    boxEl.classList.remove('gift-shake');
    boxEl.innerHTML = '<span style="font-size:3rem;display:block;animation:rewardReveal .6s cubic-bezier(.34,1.56,.64,1)">🎁</span>';

    setTimeout(() => {
      boxEl.style.transition = 'transform .4s cubic-bezier(.34,1.56,.64,1)';
      boxEl.style.transform  = 'scale(1.3)';
      setTimeout(() => {
        boxEl.style.transform = 'scale(1)';
        if (callback) callback();
      }, 400);
    }, 300);
  }, 800);
}

// ── VIP Upgrade Celebration ───────────────────────────────
function celebrateVipUpgrade(level) {
  launchConfetti(80);

  const vipColors = ['#888','#CD7F32','#C0C0C0','#FFD700','#00D4FF','#7B2FFF'];
  const color = vipColors[level] || '#00D4FF';

  const banner = document.createElement('div');
  banner.style.cssText = `
    position:fixed; top:50%; left:50%; transform:translate(-50%,-50%);
    z-index:9999; background:rgba(10,14,26,.95);
    border:2px solid ${color}; border-radius:24px; padding:32px 48px;
    text-align:center; box-shadow:0 0 40px ${color}60;
    animation:vipBlast .6s cubic-bezier(.34,1.56,.64,1) forwards;
  `;
  banner.innerHTML = `
    <div style="font-size:3rem;margin-bottom:8px">🏆</div>
    <div style="font-family:'Orbitron',sans-serif;font-size:1.4rem;font-weight:900;color:${color}">VIP LEVEL ${level}</div>
    <div style="color:#8899BB;margin-top:8px">Selamat! Anda naik level!</div>
  `;
  document.body.appendChild(banner);
  setTimeout(() => {
    banner.style.animation = 'popZoomOut .3s forwards';
    setTimeout(() => banner.remove(), 300);
  }, 3000);
}

// ── Number counter (landing page) ────────────────────────
function animateCounters() {
  document.querySelectorAll('[data-counter]').forEach(el => {
    const target   = parseInt(el.dataset.counter.replace(/\D/g,'')) || 0;
    const suffix   = el.dataset.counterSuffix || '';
    const prefix   = el.dataset.counterPrefix || '';
    const duration = 2000;
    const start    = performance.now();

    const step = (now) => {
      const elapsed  = now - start;
      const progress = Math.min(elapsed / duration, 1);
      const eased    = 1 - Math.pow(1 - progress, 3);
      const value    = Math.floor(target * eased);
      el.textContent = prefix + value.toLocaleString('id-ID') + suffix;
      if (progress < 1) requestAnimationFrame(step);
    };
    requestAnimationFrame(step);
  });
}

// Trigger counters when in view
const counterObserver = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      animateCounters();
      counterObserver.disconnect();
    }
  });
}, { threshold: 0.3 });

const statsSection = document.querySelector('.stats-section, [data-counter]');
if (statsSection) counterObserver.observe(statsSection);

// ── Progress bar animated ─────────────────────────────────
document.querySelectorAll('.progress-bar[data-progress]').forEach(bar => {
  const obs = new IntersectionObserver(entries => {
    if (entries[0].isIntersecting) {
      bar.style.width = bar.dataset.progress + '%';
      obs.unobserve(bar);
    }
  });
  obs.observe(bar);
  bar.style.width = '0%';
});

// ── Smooth page transitions ───────────────────────────────
document.querySelectorAll('a[href]:not([target]):not([data-no-transition])').forEach(link => {
  link.addEventListener('click', e => {
    const href = link.getAttribute('href');
    if (href.startsWith('#') || href.startsWith('javascript') || href.startsWith('mailto') || href.startsWith('tel')) return;
    if (e.ctrlKey || e.metaKey || e.shiftKey) return;
    e.preventDefault();
    document.body.style.opacity = '.7';
    document.body.style.transition = 'opacity .2s';
    setTimeout(() => { window.location.href = href; }, 200);
  });
});

// ── Skeleton screen removal ───────────────────────────────
function removeSkeleton(containerId) {
  const el = document.getElementById(containerId);
  if (el) el.classList.remove('skeleton-loading');
}

// ── Mining profit rain effect ─────────────────────────────
function profitRain(amount) {
  const symbols = ['💰', '💎', '⭐'];
  const count   = Math.min(15, Math.ceil(amount / 1000));

  for (let i = 0; i < count; i++) {
    setTimeout(() => {
      const el = document.createElement('div');
      el.style.cssText = `
        position:fixed; font-size:${1 + Math.random()}rem;
        left:${10 + Math.random() * 80}%; top:-40px; z-index:9990;
        pointer-events:none; animation:coinDrop ${1 + Math.random()}s ease forwards;
      `;
      el.textContent = symbols[Math.floor(Math.random() * symbols.length)];
      document.body.appendChild(el);
      setTimeout(() => el.remove(), 2000);
    }, i * 100);
  }
}

// ── Mobile swipe cards ────────────────────────────────────
function initSwipeCards(wrapperSelector) {
  const wrapper = document.querySelector(wrapperSelector);
  if (!wrapper) return;

  let startX = 0, scrollLeft = 0;
  wrapper.addEventListener('mousedown', e => {
    startX = e.pageX - wrapper.offsetLeft;
    scrollLeft = wrapper.scrollLeft;
    wrapper.style.cursor = 'grabbing';
  });
  wrapper.addEventListener('mouseleave', () => wrapper.style.cursor = 'grab');
  wrapper.addEventListener('mouseup',    () => wrapper.style.cursor = 'grab');
  wrapper.addEventListener('mousemove', e => {
    if (!e.buttons) return;
    const x    = e.pageX - wrapper.offsetLeft;
    const walk = (x - startX) * 1.5;
    wrapper.scrollLeft = scrollLeft - walk;
  });
}

initSwipeCards('.wallet-grid-swipe');

// ── Auto-init banner sliders ──────────────────────────────
if (typeof initBannerSlider !== 'undefined') {
  document.querySelectorAll('.banner-slider').forEach(initBannerSlider);
}
