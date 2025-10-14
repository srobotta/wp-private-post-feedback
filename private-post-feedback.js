/* global jQuery */
(function($) {
  	$(function() {
        $('.private-feedback-toggle').on('click', function(e) {
            e.preventDefault();
            $('.private-feedback-form').toggle();
        });
    });
}(jQuery));
        