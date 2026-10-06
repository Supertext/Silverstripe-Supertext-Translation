/* Supertext tab: show the overwrite option only when a locale with its own content is ticked,
   and a busy label while translating. Entwine re-binds when the CMS reloads the form. */
(function ($) {
  $.entwine('ss', function ($) {
    $('.supertext-targets[data-existing]').entwine({
      onmatch: function () { this._super(); this.update(); },
      onchange: function () { this.update(); },
      update: function () {
        var existing = this.data('existing') || [];
        var replacing = this.find('input:checked').toArray().some(function (box) { return existing.indexOf(box.value) !== -1; });
        $('.supertext-overwrite').closest('.field').toggleClass('supertext-hidden', !replacing).addClass('supertext-overwrite');
      }
    });
    $('button.supertext-translate, button.supertext-test').entwine({
      // The CMS only submits for buttons in the bottom toolbar; submit through it like they do.
      onclick: function (e) {
        e.preventDefault();
        if (this.is(':disabled') || (this.hasClass('supertext-translate') && !$('.supertext-targets input:checked').length)) { return false; }
        this.find('.btn__title').text(this.data('busy'));
        this.addClass('btn--loading loading');
        this.parents('form').trigger('submit', [this]);
        return false;
      }
    });
  });
})(jQuery);
