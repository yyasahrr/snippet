/**
 * Asadzadeh Academy Builder - Frontend JS - vanilla, no lib
 * Reveal via IntersectionObserver, FAQ accordion, focus handling
 */
(function(){
    'use strict';

    function initReveal(root){
        if(!root) return;
        var prefersReduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if(prefersReduced){
            root.querySelectorAll('[data-reveal]').forEach(function(el){ el.classList.add('is-visible'); });
            return;
        }
        var io = new IntersectionObserver(function(entries){
            entries.forEach(function(entry){
                if(entry.isIntersecting){
                    entry.target.classList.add('is-visible');
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });
        root.querySelectorAll('[data-reveal]').forEach(function(el){ io.observe(el); });
    }

    function initFAQ(root){
        if(!root) return;
        var faqs = root.querySelectorAll('.aap-faq-item');
        faqs.forEach(function(f){
            f.addEventListener('toggle', function(){
                if(f.open){
                    faqs.forEach(function(other){ if(other!==f) other.open = false; });
                }
            });
        });
    }

    function initAll(){
        document.querySelectorAll('.aap-home, .aap-courses-page, .aap-about-page, .aap-contact-page').forEach(function(root){
            if(root.dataset.aapReady === '1') return;
            root.dataset.aapReady = '1';
            initReveal(root);
            initFAQ(root);
        });
    }

    if(document.readyState === 'loading'){
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Re-init for Elementor editor
    if(window.elementorFrontend){
        window.elementorFrontend.hooks.addAction('frontend/element_ready/global', function($scope){
            initAll();
        });
    }
})();
