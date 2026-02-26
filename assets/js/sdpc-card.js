(function() {
  'use strict';

  var priceCache = {};

  function formatPrice(price, currency) {
    var symbol = currency === 'GBP' ? '£' : '$';
    return symbol + price.toFixed(2);
  }

  function copyToClipboard(text) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
      return navigator.clipboard.writeText(text);
    }
    var textArea = document.createElement('textarea');
    textArea.value = text;
    textArea.style.position = 'fixed';
    textArea.style.left = '-999999px';
    document.body.appendChild(textArea);
    textArea.focus();
    textArea.select();
    return new Promise(function(resolve, reject) {
      var ok = document.execCommand('copy');
      document.body.removeChild(textArea);
      ok ? resolve() : reject();
    });
  }

  function fetchLivePrice(url) {
    if (priceCache[url]) {
      return Promise.resolve(priceCache[url]);
    }

    return fetch(url).then(function(res) {
      if (!res.ok) throw new Error('HTTP ' + res.status);
      return res.text();
    }).then(function(html) {
      var doc = new DOMParser().parseFromString(html, 'text/html');
      var finalEl = doc.querySelector('.normal-price .price-wrapper[data-price-amount]');
      var oldEl = doc.querySelector('.old-price .price-wrapper[data-price-amount]');
      var finalPrice = finalEl ? parseFloat(finalEl.getAttribute('data-price-amount')) : null;
      var oldPrice = oldEl ? parseFloat(oldEl.getAttribute('data-price-amount')) : null;

      if (!finalPrice || isNaN(finalPrice) || finalPrice <= 0) return null;

      var result = {
        price: finalPrice,
        compareAtPrice: (oldPrice && !isNaN(oldPrice) && oldPrice > finalPrice) ? oldPrice : null
      };
      priceCache[url] = result;
      return result;
    }).catch(function(e) {
      console.warn('Failed to fetch live price:', e.message);
      return null;
    });
  }

  function initCard(card) {
    var currentIndex = 0;
    var isDragging = false;
    var startX = 0;
    var currentX = 0;
    var dragOffset = 0;

    // Read data attributes.
    var discountCode = card.getAttribute('data-discount-code') || '';
    var fetchLive = card.getAttribute('data-fetch-live-price') === '1';
    var productUrl = card.getAttribute('data-product-url') || '';
    var currency = card.getAttribute('data-currency') || 'USD';

    var gallery = card.querySelector('.sdpc-media__gallery');
    var track = gallery.querySelector('.sdpc-media__track');
    var nav = gallery.querySelector('.sdpc-media__nav');
    var dotsContainer = card.querySelector('.sdpc-media__dots') || card.querySelector('.sdpc-media__thumbs');
    var pricingContainer = card.querySelector('.sdpc-content__pricing');
    var badgesContainer = card.querySelector('.sdpc-content__badges');

    // --- Gallery navigation ---
    var slides = track.querySelectorAll('.sdpc-media__slide');
    if (slides.length > 1) {
      var prevBtn = nav.querySelector('.sdpc-media__button--prev');
      var nextBtn = nav.querySelector('.sdpc-media__button--next');
      var dots = dotsContainer ? Array.from(dotsContainer.children) : [];

      function updateGallery(index, animate) {
        if (animate === undefined) animate = true;
        currentIndex = Math.max(0, Math.min(index, slides.length - 1));

        if (!animate) {
          track.style.transition = 'none';
        }
        track.style.transform = 'translateX(-' + (currentIndex * 100) + '%)';
        if (!animate) {
          track.offsetHeight;
          track.style.transition = '';
        }

        dots.forEach(function(d, i) {
          var isActive = i === currentIndex;
          var cls = d.classList.contains('sdpc-media__dot') ? 'sdpc-media__dot--active' : 'sdpc-media__thumb--active';
          d.classList.toggle(cls, isActive);
          d.setAttribute('aria-selected', isActive);
        });

        if (prevBtn) prevBtn.disabled = currentIndex === 0;
        if (nextBtn) nextBtn.disabled = currentIndex === slides.length - 1;

        var a11y = document.createElement('div');
        a11y.setAttribute('role', 'status');
        a11y.setAttribute('aria-live', 'polite');
        a11y.className = 'sdpc-sr-only';
        a11y.textContent = 'Image ' + (currentIndex + 1) + ' of ' + slides.length;
        gallery.appendChild(a11y);
        setTimeout(function() { a11y.remove(); }, 100);
      }

      if (prevBtn) prevBtn.addEventListener('click', function() { if (currentIndex > 0) updateGallery(currentIndex - 1); });
      if (nextBtn) nextBtn.addEventListener('click', function() { if (currentIndex < slides.length - 1) updateGallery(currentIndex + 1); });

      dots.forEach(function(dot, i) {
        dot.addEventListener('click', function() { updateGallery(i); });
      });

      gallery.addEventListener('keydown', function(e) {
        if (e.key === 'ArrowLeft' && currentIndex > 0) updateGallery(currentIndex - 1);
        else if (e.key === 'ArrowRight' && currentIndex < slides.length - 1) updateGallery(currentIndex + 1);
      });

      // Touch / mouse drag.
      function handleStart(e) {
        isDragging = true;
        startX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
        dragOffset = 0;
        track.classList.add('sdpc-media__track--dragging');
        gallery.style.cursor = 'grabbing';
        e.preventDefault();
      }

      function handleMove(e) {
        if (!isDragging) return;
        e.preventDefault();
        currentX = e.type.includes('mouse') ? e.clientX : e.touches[0].clientX;
        dragOffset = currentX - startX;
        var max = gallery.offsetWidth * 0.33;
        dragOffset = Math.max(-max, Math.min(max, dragOffset));
        var base = -currentIndex * 100;
        var pct = (dragOffset / gallery.offsetWidth) * 100;
        track.style.transform = 'translateX(calc(' + base + '% + ' + pct + '%))';
      }

      function handleEnd() {
        if (!isDragging) return;
        isDragging = false;
        track.classList.remove('sdpc-media__track--dragging');
        gallery.style.cursor = '';
        var threshold = gallery.offsetWidth * 0.15;
        if (dragOffset < -threshold && currentIndex < slides.length - 1) updateGallery(currentIndex + 1);
        else if (dragOffset > threshold && currentIndex > 0) updateGallery(currentIndex - 1);
        else updateGallery(currentIndex);
        dragOffset = 0;
      }

      gallery.addEventListener('mousedown', handleStart);
      window.addEventListener('mousemove', handleMove);
      window.addEventListener('mouseup', handleEnd);
      gallery.addEventListener('touchstart', handleStart, { passive: false });
      gallery.addEventListener('touchmove', handleMove, { passive: false });
      gallery.addEventListener('touchend', handleEnd);
      track.addEventListener('dragstart', function(e) { e.preventDefault(); });

      if (dotsContainer && dotsContainer.classList.contains('sdpc-media__thumbs') && dots[0]) {
        dots[0].scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
      }
    }

    // --- Discount copy-to-clipboard ---
    var discountBox = card.querySelector('.sdpc-content__discount');
    if (discountBox && discountCode) {
      var feedback = discountBox.querySelector('.sdpc-content__discount-feedback');

      function handleCopy() {
        copyToClipboard(discountCode).then(function() {
          discountBox.classList.add('sdpc-content__discount--copied');
          if (feedback) feedback.classList.add('sdpc-content__discount-feedback--visible');

          var a11y = document.createElement('div');
          a11y.className = 'sdpc-sr-only';
          a11y.setAttribute('role', 'status');
          a11y.setAttribute('aria-live', 'polite');
          a11y.textContent = 'Discount code ' + discountCode + ' copied to clipboard';
          discountBox.appendChild(a11y);

          setTimeout(function() {
            discountBox.classList.remove('sdpc-content__discount--copied');
            if (feedback) feedback.classList.remove('sdpc-content__discount-feedback--visible');
            a11y.remove();
          }, 2000);
        }).catch(function() {
          console.error('Failed to copy discount code');
        });
      }

      discountBox.addEventListener('click', handleCopy);
      discountBox.addEventListener('keydown', function(e) {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); handleCopy(); }
      });
    }

    // --- Live price update ---
    if (fetchLive && productUrl && pricingContainer) {
      var priceEl = pricingContainer.querySelector('.sdpc-content__price');
      if (priceEl) priceEl.classList.add('sdpc-content__price--loading');

      var observer = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            fetchLivePrice(productUrl).then(function(live) {
              if (priceEl) priceEl.classList.remove('sdpc-content__price--loading');

              if (live && live.price) {
                if (priceEl) priceEl.textContent = formatPrice(live.price, currency);

                var compareEl = pricingContainer.querySelector('.sdpc-content__compare');
                if (live.compareAtPrice && live.compareAtPrice > live.price) {
                  if (!compareEl) {
                    compareEl = document.createElement('span');
                    compareEl.className = 'sdpc-content__compare';
                    pricingContainer.appendChild(compareEl);
                  }
                  compareEl.textContent = formatPrice(live.compareAtPrice, currency);

                  var savings = Math.round((1 - live.price / live.compareAtPrice) * 100);
                  var badge = badgesContainer ? badgesContainer.querySelector('.sdpc-content__badge--savings') : null;
                  if (badge) {
                    badge.textContent = 'Save ' + savings + '%';
                  } else if (badgesContainer) {
                    badge = document.createElement('span');
                    badge.className = 'sdpc-content__badge sdpc-content__badge--savings';
                    badge.textContent = 'Save ' + savings + '%';
                    badgesContainer.appendChild(badge);
                  }
                }
              }
            });
            observer.unobserve(card);
          }
        });
      }, { rootMargin: '100px' });

      observer.observe(card);
    }
  }

  function initAllCards() {
    document.querySelectorAll('.sdpc-card').forEach(initCard);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllCards);
  } else {
    initAllCards();
  }
})();
