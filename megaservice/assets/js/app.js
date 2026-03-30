/**
 * Megaservice Theme - Main JavaScript File
 * ==============================================
 */

import './carousel.js';
import './menu.js';
import './parts-search.js';

(function() {
  'use strict';

  // Initialize when DOM is ready
  document.addEventListener('DOMContentLoaded', function() {
    console.log('Megaservice theme JS initialized');
    
    // Initialize components
    initializeNavigation();
    initializeCartWidget();
    initializeSearchWidget();
  });

  /**
   * Initialize navigation functionality
   */
  function initializeNavigation() {
    // Menu sidebar est initialisé par menu.js
  }

  /**
   * Initialize cart widget functionality
   */
  function initializeCartWidget() {
    // Cart widget logic here
  }

  /**
   * Initialize search widget functionality
   */
  function initializeSearchWidget() {
    const widget = document.querySelector('.ms-search-widget');
    if (!widget) return;

    const toggle = widget.querySelector('.ms-search-widget__toggle');
    const form = widget.querySelector('.ms-search-widget__form');
    if (!toggle || !form) return;

    toggle.addEventListener('click', function() {
      const isHidden = form.hasAttribute('hidden');
      if (isHidden) {
        form.removeAttribute('hidden');
        toggle.setAttribute('aria-expanded', 'true');
        form.querySelector('input[type="text"]').focus();
      } else {
        form.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });

    document.addEventListener('click', function(e) {
      if (!widget.contains(e.target)) {
        form.setAttribute('hidden', '');
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  }
})();
