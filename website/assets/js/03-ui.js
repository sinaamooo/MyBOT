/* ═══════════════ Interface components ═══════════════ */

NX.ui = (() => {
  /* ── Toasts ── */
  function toast({ tone = 'info', title, text = '', duration = 4200 }) {
    const region = NX.$('[data-toasts]');
    const template = NX.$('#toast-template');
    if (!region || !template) return;
    const node = template.content.firstElementChild.cloneNode(true);
    node.classList.add(`toast--${tone}`);
    node.style.setProperty('--toast-ms', `${duration}ms`);
    NX.$('[data-toast-title]', node).textContent = title;
    NX.$('[data-toast-text]', node).textContent = text;

    const dismiss = () => {
      if (node.classList.contains('is-leaving')) return;
      node.classList.add('is-leaving');
      setTimeout(() => node.remove(), 260);
    };
    NX.$('[data-toast-close]', node).addEventListener('click', dismiss);
    NX.afterAnimation(NX.$('.toast__progress', node), dismiss);

    while (region.children.length >= 3) region.firstElementChild.remove();
    region.append(node);
  }

  /* ── Intro loader: resolves once the page is ready (min 650 ms, max 2.5 s) ── */
  function loader() {
    if (!NX.root.classList.contains('is-loading')) return Promise.resolve();
    const started = performance.now();
    const loaded = new Promise((resolve) => {
      if (document.readyState === 'complete') resolve();
      else addEventListener('load', resolve, { once: true });
    });
    const ready = Promise.all([loaded, document.fonts ? document.fonts.ready : null]);
    const timeout = new Promise((resolve) => setTimeout(resolve, 2500));
    return Promise.race([ready, timeout])
      .then(() => new Promise((resolve) => setTimeout(resolve, Math.max(0, 650 - (performance.now() - started)))))
      .then(() => {
        NX.root.classList.remove('is-loading');
        try { sessionStorage.setItem('nx-seen', '1'); } catch { /* storage blocked */ }
      });
  }

  /* ── Dropdowns ── */
  const dropdowns = () => NX.$$('[data-dropdown]');

  function setDropdown(dropdown, open) {
    dropdown.classList.toggle('is-open', open);
    NX.$('[data-dropdown-trigger]', dropdown).setAttribute('aria-expanded', String(open));
  }

  function closeDropdowns(except) {
    dropdowns().forEach((d) => d !== except && d.classList.contains('is-open') && setDropdown(d, false));
  }

  function initDropdowns() {
    dropdowns().forEach((dropdown) => {
      NX.$('[data-dropdown-trigger]', dropdown).addEventListener('click', () => {
        closeDropdowns(dropdown);
        setDropdown(dropdown, !dropdown.classList.contains('is-open'));
      });
    });
    document.addEventListener('click', (event) => {
      dropdowns().forEach((d) => !d.contains(event.target) && d.classList.contains('is-open') && setDropdown(d, false));
    });
    document.addEventListener('keydown', (event) => {
      if (event.key !== 'Escape') return;
      const open = NX.$('[data-dropdown].is-open');
      if (!open) return;
      setDropdown(open, false);
      NX.$('[data-dropdown-trigger]', open).focus();
    });
  }

  /* ── Modals & sheets (native <dialog>) ── */
  function closeDialog(dialog) {
    if (!dialog.open || dialog.classList.contains('is-closing')) return;
    if (!NX.animated) {
      dialog.close();
      return;
    }
    dialog.classList.add('is-closing');
    NX.afterAnimation(dialog, () => {
      dialog.classList.remove('is-closing');
      dialog.close();
    });
  }

  function initDialogs() {
    document.addEventListener('click', (event) => {
      const opener = event.target.closest('[data-modal-open]');
      if (opener) {
        const dialog = document.getElementById(opener.dataset.modalOpen);
        if (!dialog) return;
        closeDropdowns();
        NX.$$('dialog[open]').forEach((d) => d !== dialog && d.close());
        if (!dialog.open) dialog.showModal();
        return;
      }
      const closer = event.target.closest('[data-modal-close]');
      if (closer) closeDialog(closer.closest('dialog'));
      else if (event.target instanceof HTMLDialogElement) closeDialog(event.target); // backdrop click
    });
    NX.$$('dialog').forEach((dialog) => dialog.addEventListener('cancel', (event) => {
      event.preventDefault();
      closeDialog(dialog);
    }));
  }

  /* ── Copy to clipboard ── */
  async function copyText(text) {
    if (navigator.clipboard && window.isSecureContext) return navigator.clipboard.writeText(text);
    const area = Object.assign(document.createElement('textarea'), { value: text });
    area.setAttribute('readonly', '');
    area.style.cssText = 'position:fixed;opacity:0;pointer-events:none';
    document.body.append(area);
    area.select();
    const copied = document.execCommand('copy');
    area.remove();
    if (!copied) throw new Error('copy failed');
  }

  function initCopy() {
    document.addEventListener('click', async (event) => {
      const button = event.target.closest('[data-copy]');
      if (!button) return;
      try {
        await copyText(button.dataset.copy);
        button.classList.add('is-done');
        setTimeout(() => button.classList.remove('is-done'), 1600);
        toast({ tone: 'success', title: 'کپی شد', text: `${button.dataset.copyLabel} در حافظه کپی شد.` });
      } catch {
        toast({ tone: 'error', title: 'کپی انجام نشد', text: 'لطفاً متن را دستی انتخاب و کپی کنید.' });
      }
    });
  }

  /* ── Tabs with a sliding indicator ── */
  function initTabs() {
    NX.$$('[data-tabs]').forEach((group) => {
      const list = group.matches('[role="tablist"]') ? group : NX.$('[role="tablist"]', group);
      const tabs = NX.$$('[role="tab"]', list);
      const ink = NX.$('.tabs__ink', list);
      let current = tabs.find((t) => t.getAttribute('aria-selected') === 'true') || tabs[0];

      const place = () => {
        ink.style.width = `${current.offsetWidth}px`;
        ink.style.transform = `translateX(${current.offsetLeft}px)`;
      };
      const select = (tab, focus = false) => {
        current = tab;
        tabs.forEach((t) => {
          const on = t === tab;
          t.setAttribute('aria-selected', String(on));
          t.tabIndex = on ? 0 : -1;
          const panel = t.hasAttribute('aria-controls') && document.getElementById(t.getAttribute('aria-controls'));
          if (!panel) return;
          panel.hidden = !on;
          if (on) {
            panel.classList.remove('is-entering');
            void panel.offsetWidth; // restart the entrance animation
            panel.classList.add('is-entering');
          }
        });
        place();
        if (focus) tab.focus();
        group.dispatchEvent(new CustomEvent('nx:tab', { bubbles: true, detail: tab.dataset.value }));
      };

      tabs.forEach((tab) => tab.addEventListener('click', () => select(tab)));
      list.addEventListener('keydown', (event) => {
        const step = { ArrowLeft: 1, ArrowRight: -1 }[event.key]; // RTL: left moves forward
        const index = tabs.indexOf(current);
        if (step) select(tabs[(index + step + tabs.length) % tabs.length], true);
        else if (event.key === 'Home') select(tabs[0], true);
        else if (event.key === 'End') select(tabs[tabs.length - 1], true);
        else return;
        event.preventDefault();
      });
      new ResizeObserver(place).observe(list);
      group.selectTab = (value) => select(tabs.find((t) => t.dataset.value === value) || tabs[0]);
    });
  }

  /* ── Accordion with a measured height animation ── */
  function initAccordions() {
    NX.$$('details[data-accordion]').forEach((details) => {
      const summary = NX.$('summary', details);
      const body = NX.$('.faq__body', details);
      let animation = null;
      summary.addEventListener('click', (event) => {
        if (!NX.animated) return;
        event.preventDefault();
        animation?.cancel();
        const opening = !details.open;
        const from = opening ? 0 : body.offsetHeight;
        if (opening) details.open = true;
        const to = opening ? body.offsetHeight : 0;
        animation = body.animate(
          [{ height: `${from}px`, opacity: opening ? 0 : 1 }, { height: `${to}px`, opacity: opening ? 1 : 0 }],
          { duration: 360, easing: 'cubic-bezier(.22,1,.36,1)' },
        );
        animation.onfinish = () => {
          if (!opening) details.open = false;
          animation = null;
        };
      });
    });
  }

  /* ── Scroll reveal ── */
  function initReveal() {
    const items = NX.$$('[data-reveal]');
    if (!NX.animated || !('IntersectionObserver' in window)) {
      items.forEach((el) => el.classList.add('is-in'));
      return;
    }
    NX.$$('[data-stagger]').forEach((group) => {
      Array.from(group.children).forEach((child, i) => child.style.setProperty('--rd', `${Math.min(i, 6) * 70}ms`));
    });
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        entry.target.classList.add('is-in');
        observer.unobserve(entry.target);
      });
    }, { rootMargin: '0px 0px -6% 0px', threshold: 0.08 });
    items.forEach((el) => observer.observe(el));
  }

  /* ── Pause decorative animations while they are off screen ── */
  function initOffscreenPause() {
    const live = NX.$$('[data-live]');
    if (!live.length) return;
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => entry.target.classList.toggle('is-paused', !entry.isIntersecting));
    });
    live.forEach((el) => observer.observe(el));
  }

  /* ── Pointer light on cards & 3D tilt on the hero preview ── */
  function initPointerEffects() {
    if (!NX.finePointer) return;
    let card = null, tilt = NX.$('[data-tilt]'), x = 0, y = 0, queued = false, tiltVisible = true;

    const paint = () => {
      queued = false;
      if (!NX.animated) return;
      if (card) {
        const box = card.getBoundingClientRect();
        card.style.setProperty('--mx', `${x - box.left}px`);
        card.style.setProperty('--my', `${y - box.top}px`);
      }
      if (tilt && tiltVisible) {
        tilt.style.setProperty('--rx', `${((y / innerHeight) - 0.5) * -7}deg`);
        tilt.style.setProperty('--ry', `${((x / innerWidth) - 0.5) * 12 - 6}deg`);
      }
    };

    document.addEventListener('pointermove', (event) => {
      card = event.target.closest ? event.target.closest('.card') : null;
      x = event.clientX;
      y = event.clientY;
      if (!queued) {
        queued = true;
        requestAnimationFrame(paint);
      }
    }, { passive: true });

    if (tilt) {
      new IntersectionObserver(([entry]) => { tiltVisible = entry.isIntersecting; }).observe(tilt);
    }
  }

  /* ── Services: search + category filter ── */
  function initFilter() {
    const root = NX.$('[data-filter]');
    if (!root) return;
    const input = NX.$('[data-filter-input]', root);
    const field = input.closest('.field');
    const items = NX.$$('[data-filter-item]', root);
    const empty = NX.$('[data-filter-empty]', root);
    const count = NX.$('[data-filter-count]', root);
    const tabs = NX.$('[data-tabs]', root);
    let category = 'all';

    const normalize = (text) => text.toLowerCase().replace(/[يى]/g, 'ی').replace(/ك/g, 'ک').replace(/‌/g, ' ').trim();
    const apply = () => {
      const query = normalize(input.value);
      let shown = 0;
      items.forEach((item) => {
        const match = (category === 'all' || item.dataset.category === category)
          && (!query || normalize(item.dataset.filterText).includes(query));
        item.hidden = !match;
        if (match) shown += 1;
      });
      empty.hidden = shown > 0;
      count.textContent = NX.fa(shown);
      field.classList.toggle('has-value', input.value !== '');
    };

    input.addEventListener('input', apply);
    NX.$('[data-filter-clear]', root).addEventListener('click', () => {
      input.value = '';
      apply();
      input.focus();
    });
    root.addEventListener('nx:tab', (event) => {
      category = event.detail;
      apply();
    });
    NX.$('[data-filter-reset]', root).addEventListener('click', () => {
      input.value = '';
      category = 'all';
      if (tabs) tabs.selectTab('all');
      apply();
    });
  }

  /* ── Legal pages: highlight the section being read ── */
  function initScrollSpy() {
    const links = NX.$$('[data-toc] a');
    if (!links.length) return;
    const byId = new Map(links.map((a) => [decodeURIComponent(a.hash.slice(1)), a]));
    let active = null;
    const observer = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (!entry.isIntersecting) return;
        active?.removeAttribute('aria-current');
        active = byId.get(entry.target.id);
        active?.setAttribute('aria-current', 'true');
      });
    }, { rootMargin: '-25% 0px -65% 0px' });
    byId.forEach((_, id) => {
      const section = document.getElementById(id);
      if (section) observer.observe(section);
    });
  }

  /* ── Performance mode (manual switch, or automatic on a struggling device) ── */
  function initPerfToggle() {
    const toggles = NX.$$('[data-perf-toggle]');
    const sync = () => toggles.forEach((t) => { t.checked = NX.perf; });
    const setPerf = (on) => {
      if (on) NX.root.dataset.perf = 'on';
      else delete NX.root.dataset.perf;
      NX.store.set('nx-perf', on ? '1' : '0');
      sync();
      NX.setQuality(window.nxQuality());
    };
    sync();
    toggles.forEach((toggle) => toggle.addEventListener('change', () => {
      setPerf(toggle.checked);
      toast(toggle.checked
        ? { tone: 'info', title: 'حالت کم‌مصرف روشن شد', text: 'افکت‌های سنگین خاموش شدند تا سایت روی این دستگاه روان‌تر باشد.' }
        : { tone: 'success', title: 'حالت کم‌مصرف خاموش شد', text: 'افکت‌های کامل کهکشانی دوباره فعال شدند.' });
    }));
    document.addEventListener('nx:slow', () => {
      if (NX.perf || NX.store.get('nx-perf') === '0') return; // respect an explicit "off"
      setPerf(true);
      toast({ tone: 'info', title: 'حالت کم‌مصرف خودکار روشن شد', text: 'این دستگاه افکت‌ها را کند اجرا می‌کرد؛ از منوی حساب می‌توانید آن را خاموش کنید.' });
    });
  }

  /* ── Notifications: remember what has been read ── */
  function initNotices() {
    const badge = NX.$('[data-notify-badge]');
    if (!badge) return;
    const signature = badge.dataset.sig;
    if (NX.store.get('nx-notices') === signature) badge.hidden = true;
    NX.$$('[data-notify-clear]').forEach((button) => button.addEventListener('click', () => {
      NX.store.set('nx-notices', signature);
      badge.hidden = true;
      toast({ tone: 'success', title: 'همه‌ی اعلان‌ها خوانده شد' });
    }));
  }

  /* ── Small interactions ── */
  function initTopbar() {
    const bar = NX.$('[data-topbar]');
    if (!bar) return;
    const sentinel = document.createElement('div');
    sentinel.style.cssText = 'position:absolute;top:0;width:1px;height:8px;pointer-events:none';
    document.body.prepend(sentinel);
    new IntersectionObserver(([entry]) => bar.classList.toggle('is-scrolled', !entry.isIntersecting)).observe(sentinel);
  }


  function initScrollTop() {
    NX.$$('[data-scroll-top]').forEach((button) => button.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: NX.animated ? 'smooth' : 'auto' });
    }));
  }

  function init() {
    initDropdowns();
    initDialogs();
    initCopy();
    initTabs();
    initAccordions();
    initOffscreenPause();
    initPointerEffects();
    initFilter();
    initScrollSpy();
    initPerfToggle();
    initNotices();
    initTopbar();
    initScrollTop();
    loader().then(initReveal);
  }

  return { init };
})();
