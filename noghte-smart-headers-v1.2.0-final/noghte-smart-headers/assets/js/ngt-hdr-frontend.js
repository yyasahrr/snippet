(function () {
	'use strict';

	var lastFocused = null;
	var resizeTimer = null;

	function syncBodyLock() {
		var hasOpenLayer = document.querySelector('.ngt-hdr-drawer.is-open, .ngt-hdr-search-modal.is-open');
		document.body.classList.toggle('ngt-hdr-lock', Boolean(hasOpenLayer));
	}

	function getTriggerForLayer(layer) {
		var header = layer ? layer.closest('.ngt-hdr') : null;
		if (!header) return null;
		return layer.matches('[data-ngt-drawer]')
			? header.querySelector('[data-ngt-menu-open]')
			: header.querySelector('[data-ngt-search-open]');
	}

	function setLayerState(layer, trigger, open) {
		if (!layer) return;
		layer.classList.toggle('is-open', open);
		layer.setAttribute('aria-hidden', open ? 'false' : 'true');
		if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
		syncBodyLock();
	}

	function closeAll(except) {
		document.querySelectorAll('.ngt-hdr-drawer.is-open, .ngt-hdr-search-modal.is-open').forEach(function (layer) {
			if (layer === except) return;
			setLayerState(layer, getTriggerForLayer(layer), false);
		});
	}

	function directChild(parent, selector) {
		if (!parent) return null;
		for (var i = 0; i < parent.children.length; i += 1) {
			if (parent.children[i].matches(selector)) return parent.children[i];
		}
		return null;
	}

	function initMobileSubmenus(drawer) {
		if (!drawer) return;
		drawer.querySelectorAll('.ngt-hdr-mobile-menu li').forEach(function (item) {
			var submenu = directChild(item, '.sub-menu');
			if (!submenu || directChild(item, '.ngt-hdr-submenu-toggle')) return;

			var expanded = item.classList.contains('current-menu-ancestor') || item.classList.contains('current-menu-parent');
			var toggle = document.createElement('button');
			toggle.type = 'button';
			toggle.className = 'ngt-hdr-submenu-toggle';
			toggle.setAttribute('aria-label', 'نمایش زیرمنو');
			toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
			toggle.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m7 9 5 5 5-5"/></svg>';
			item.classList.toggle('is-submenu-open', expanded);

			toggle.addEventListener('click', function () {
				var isOpen = !item.classList.contains('is-submenu-open');
				item.classList.toggle('is-submenu-open', isOpen);
				toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			});

			item.insertBefore(toggle, submenu);
		});
	}

	function getFocusable(layer) {
		if (!layer) return [];
		return Array.prototype.slice.call(layer.querySelectorAll(
			'a[href], button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])'
		)).filter(function (element) {
			return element.offsetWidth > 0 || element.offsetHeight > 0 || element === document.activeElement;
		});
	}

	function focusFirst(layer, preferred) {
		window.setTimeout(function () {
			if (preferred && typeof preferred.focus === 'function') {
				preferred.focus();
				return;
			}
			var focusable = getFocusable(layer);
			var panel = layer ? layer.querySelector('[data-ngt-layer-panel]') : null;
			if (focusable.length) focusable[0].focus();
			else if (panel) panel.focus();
		}, 80);
	}

	function initHeader(header) {
		if (!header || header.dataset.ngtBound === 'yes') return;
		header.dataset.ngtBound = 'yes';

		var drawer = header.querySelector('[data-ngt-drawer]');
		var menuOpen = header.querySelector('[data-ngt-menu-open]');
		var menuClose = header.querySelectorAll('[data-ngt-menu-close]');
		var searchModal = header.querySelector('[data-ngt-search-modal]');
		var searchOpen = header.querySelector('[data-ngt-search-open]');
		var searchClose = header.querySelectorAll('[data-ngt-search-close]');
		var searchInput = header.querySelector('[data-ngt-search-input]');

		initMobileSubmenus(drawer);

		if (menuOpen && drawer) {
			menuOpen.addEventListener('click', function () {
				lastFocused = menuOpen;
				closeAll(drawer);
				setLayerState(drawer, menuOpen, true);
				focusFirst(drawer, drawer.querySelector('[data-ngt-menu-close]'));
			});
		}

		menuClose.forEach(function (button) {
			button.addEventListener('click', function () {
				setLayerState(drawer, menuOpen, false);
				if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
			});
		});

		if (drawer) {
			drawer.querySelectorAll('a').forEach(function (link) {
				link.addEventListener('click', function () {
					setLayerState(drawer, menuOpen, false);
				});
			});
		}

		if (searchOpen && searchModal) {
			searchOpen.addEventListener('click', function () {
				lastFocused = searchOpen;
				closeAll(searchModal);
				setLayerState(searchModal, searchOpen, true);
				focusFirst(searchModal, searchInput);
			});
		}

		searchClose.forEach(function (button) {
			button.addEventListener('click', function () {
				setLayerState(searchModal, searchOpen, false);
				if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
			});
		});
	}

	function initWithin(scope) {
		var root = scope && scope.querySelectorAll ? scope : document;
		if (root.matches && root.matches('[data-ngt-header]')) initHeader(root);
		root.querySelectorAll('[data-ngt-header]').forEach(initHeader);
	}

	function updateStickyState() {
		var scrolled = window.scrollY > 12;
		document.querySelectorAll('.ngt-hdr.is-sticky').forEach(function (header) {
			header.classList.toggle('is-scrolled', scrolled);
		});
	}

	function closeDrawersAboveBreakpoint() {
		document.querySelectorAll('.ngt-hdr').forEach(function (header) {
			var breakpoint = parseInt(header.getAttribute('data-collapse-at') || '1024', 10);
			if (window.innerWidth <= breakpoint) return;
			var drawer = header.querySelector('[data-ngt-drawer].is-open');
			if (drawer) setLayerState(drawer, header.querySelector('[data-ngt-menu-open]'), false);
		});
	}

	document.addEventListener('keydown', function (event) {
		var opened = document.querySelector('.ngt-hdr-search-modal.is-open, .ngt-hdr-drawer.is-open');
		if (!opened) return;

		if (event.key === 'Escape') {
			event.preventDefault();
			closeAll();
			if (lastFocused && typeof lastFocused.focus === 'function') lastFocused.focus();
			return;
		}

		if (event.key !== 'Tab') return;
		var focusable = getFocusable(opened);
		if (!focusable.length) return;
		var first = focusable[0];
		var last = focusable[focusable.length - 1];
		if (event.shiftKey && document.activeElement === first) {
			event.preventDefault();
			last.focus();
		} else if (!event.shiftKey && document.activeElement === last) {
			event.preventDefault();
			first.focus();
		}
	});

	document.addEventListener('DOMContentLoaded', function () {
		initWithin(document);
		updateStickyState();
	});

	window.addEventListener('scroll', updateStickyState, { passive: true });
	window.addEventListener('resize', function () {
		window.clearTimeout(resizeTimer);
		resizeTimer = window.setTimeout(closeDrawersAboveBreakpoint, 120);
	}, { passive: true });

	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
		window.elementorFrontend.hooks.addAction('frontend/element_ready/ngt-smart-header.default', function (scope) {
			var element = scope && scope[0] ? scope[0] : scope;
			initWithin(element);
		});
	});
})();
