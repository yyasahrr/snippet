(function () {
	'use strict';

	function initReveal(root) {
		if (!root) return;
		var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
		if (prefersReduced) {
			root.querySelectorAll('[data-reveal]').forEach(function (el) {
				el.classList.add('is-visible');
			});
			return;
		}
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				if (entry.isIntersecting) {
					entry.target.classList.add('is-visible');
					io.unobserve(entry.target);
				}
			});
		}, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
		root.querySelectorAll('[data-reveal]').forEach(function (el) {
			io.observe(el);
		});
	}

	function initFAQ(root) {
		if (!root) return;
		var faqs = root.querySelectorAll('.aap-faq-item');
		faqs.forEach(function (f) {
			f.addEventListener('toggle', function () {
				if (f.open) {
					faqs.forEach(function (other) {
						if (other !== f) other.open = false;
					});
				}
			});
		});
	}

	function initAll(scope) {
		var root = scope && scope.querySelectorAll ? scope : document;
		var nodes = [];
		if (root.matches && root.matches('[data-aap-page]')) {
			nodes = [root];
		} else {
			nodes = root.querySelectorAll('[data-aap-page]');
			// Also support legacy aa-home
			if (!nodes.length) {
				nodes = root.querySelectorAll('.aap-home, .aap-courses-page, .aap-about-page, .aap-contact-page');
			}
		}
		nodes.forEach(function (el) {
			if (el.dataset.aapReady === '1') return;
			el.dataset.aapReady = '1';
			initReveal(el);
			initFAQ(el);
		});
	}

	document.addEventListener('DOMContentLoaded', function () {
		initAll(document);
	});

	window.addEventListener('elementor/frontend/init', function () {
		if (!window.elementorFrontend || !window.elementorFrontend.hooks) return;
		window.elementorFrontend.hooks.addAction('frontend/element_ready/aap-home.default', function (scope) {
			var element = scope && scope[0] ? scope[0] : scope;
			initAll(element);
		});
		window.elementorFrontend.hooks.addAction('frontend/element_ready/aap-courses.default', function (scope) {
			var element = scope && scope[0] ? scope[0] : scope;
			initAll(element);
		});
		window.elementorFrontend.hooks.addAction('frontend/element_ready/aap-about.default', function (scope) {
			var element = scope && scope[0] ? scope[0] : scope;
			initAll(element);
		});
		window.elementorFrontend.hooks.addAction('frontend/element_ready/aap-contact.default', function (scope) {
			var element = scope && scope[0] ? scope[0] : scope;
			initAll(element);
		});
		// Backward compat for global
		window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function (scope) {
			var element = scope && scope[0] ? scope[0] : scope;
			initAll(element);
		});
	});
})();
