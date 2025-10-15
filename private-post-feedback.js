/* global jQuery */
(function($) {
  	$(function() {
        $('.private-feedback-toggle').on('click', function(e) {
            e.preventDefault();
            $('.private-feedback-form').toggle();
        });
        const stars = document.querySelectorAll('.private-feedback-star');
        let currentRating = 0;

        stars.forEach(star => {
            star.addEventListener('mouseover', () => {
                resetHover();
                star.classList.add('hovered');
                highlightUpTo(star.dataset.value);
            });

            star.addEventListener('mouseout', resetHover);

            star.addEventListener('click', () => {
                currentRating = star.dataset.value;
                setRating(currentRating);
                sendRating(currentRating);
            });
        });

        function highlightUpTo(value) {
            stars.forEach(s => {
                if (s.dataset.value <= value) s.classList.add('hovered');
            });
        }

        function resetHover() {
            stars.forEach(s => s.classList.remove('hovered'));
        }

        function setRating(value) {
            stars.forEach(s => {
                s.classList.toggle('selected', s.dataset.value <= value);
            });
            document.getElementById('private_feedback_rate').value = value;
        }

        function sendRating() {
            const form = $('form.private-feedback-rating-form')[0];
            const formData = new FormData(form);
            fetch(PrivateFeedbackRating.ajax_url, {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then(data => console.log('Rating saved:', data))
            .catch(err => console.error('Error:', err));
        }
    });
}(jQuery));
        