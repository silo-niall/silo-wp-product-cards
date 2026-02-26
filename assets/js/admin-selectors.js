/**
 * SILO Product Cards — Admin scrape selector UI.
 * Add/remove/reindex dynamic selector rows.
 */
(function($) {
  'use strict';

  var $body = $('#silo-pc-selectors-body');
  var template = $('#silo-pc-row-template').html();

  /**
   * Reindex all rows so the form array keys are sequential.
   */
  function reindex() {
    $body.find('.silo-pc-selector-row').each(function(i) {
      $(this).find('[name]').each(function() {
        var name = $(this).attr('name');
        // Replace silo_pc_scrape_selectors[N] with silo_pc_scrape_selectors[i]
        $(this).attr('name', name.replace(/\[\d+\]/, '[' + i + ']'));
      });
    });
  }

  /**
   * Toggle attribute input visibility based on extract type.
   */
  function toggleAttrInput($row) {
    var extract = $row.find('.silo-pc-extract-select').val();
    var $attrInput = $row.find('.silo-pc-attr-input');
    if (extract === 'attribute') {
      $attrInput.show();
    } else {
      $attrInput.hide();
    }
  }

  // Add new row.
  $('#silo-pc-add-selector').on('click', function() {
    var count = $body.find('.silo-pc-selector-row').length;
    var html = template.replace(/__INDEX__/g, count);
    $body.append(html);
    var $newRow = $body.find('.silo-pc-selector-row').last();
    toggleAttrInput($newRow);
  });

  // Remove row.
  $body.on('click', '.silo-pc-remove-row', function() {
    $(this).closest('.silo-pc-selector-row').remove();
    reindex();
  });

  // Toggle attribute input on extract change.
  $body.on('change', '.silo-pc-extract-select', function() {
    toggleAttrInput($(this).closest('.silo-pc-selector-row'));
  });

  // Reset to defaults.
  $('#silo-pc-reset-defaults').on('click', function() {
    if (!confirm('Reset all selectors to the default Magento 2 configuration?')) {
      return;
    }
    // Submit a hidden form to reset.
    var $form = $(this).closest('form');
    $body.find('.silo-pc-selector-row').remove();
    // Let the form submit empty — sanitize_selectors() will restore defaults.
    $form.submit();
  });

  // Init: toggle attribute inputs on page load.
  $body.find('.silo-pc-selector-row').each(function() {
    toggleAttrInput($(this));
  });

  // Simple drag-to-reorder using native drag events.
  var dragRow = null;

  $body.on('mousedown', '.silo-pc-col-handle', function(e) {
    dragRow = $(this).closest('tr')[0];
    $(dragRow).addClass('silo-pc-dragging');
  });

  $(document).on('mousemove', function(e) {
    if (!dragRow) return;

    var rows = $body.find('.silo-pc-selector-row').not(dragRow);
    var dragRect = dragRow.getBoundingClientRect();
    var dragMid = dragRect.top + dragRect.height / 2;

    rows.each(function() {
      var rect = this.getBoundingClientRect();
      var mid = rect.top + rect.height / 2;

      if (e.clientY < mid && dragMid > mid) {
        $(this).before(dragRow);
        return false;
      } else if (e.clientY > mid && dragMid < mid) {
        $(this).after(dragRow);
        return false;
      }
    });
  });

  $(document).on('mouseup', function() {
    if (dragRow) {
      $(dragRow).removeClass('silo-pc-dragging');
      dragRow = null;
      reindex();
    }
  });

})(jQuery);
