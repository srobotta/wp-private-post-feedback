/* global jQuery */
(function($) {
  	$(function() {
        $('.private-feedback-toggle').on('click', function(e) {
            e.preventDefault();
            $('.private-feedback-form').toggle();
        });
        $('.private-feedback-form').on('submit', function(e) {
            e.preventDefault();
            const form = $(this);
            const formData = new FormData(form[0]);
            fetch(PrivateFeedback.ajax_url + '?f=feedback', {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then((data) => {
                if (data.success) {
                    $('.private-feedback-toggle').remove();
                    $('.private-feedback-form').replaceWith($('<p/>').text(data.data.message));
                } else {
                    console.error('Error sending feedback:', data);
                }
            })
            .catch(err => console.error('Error:', err));
        });

        const stars = document.querySelectorAll('.private-feedback-star');
        const container = document.querySelector('.private-feedback-stars');
        let existingRating = parseFloat(PrivateFeedback.existing_rating) || 0;
        let ratingSend = false;

        setFractionalRating(existingRating);

        stars.forEach(star => {
            // Hover
            star.addEventListener('mouseover', () => {
                highlightUpTo(parseInt(star.dataset.value));
            });

            // Click
            star.addEventListener('click', () => {
                existingRating = parseInt(star.dataset.value);
                setFractionalRating(existingRating);
                sendRating(existingRating);
            });
        });

        // Reset to saved rating when leaving all stars
        container.addEventListener('mouseleave', () => {
            setFractionalRating(existingRating);
        });

        function setFractionalRating(rating) {
            if (ratingSend) return;
            stars.forEach(star => {
                const value = parseInt(star.dataset.value);
                let fill = 0;
                if (rating >= value) fill = 100;
                else if (rating + 1 > value) fill = (rating - (value - 1)) * 100;
                star.style.setProperty('--fill', `${fill}%`);
            });
        }

        function highlightUpTo(value) {
            if (ratingSend) return;
            stars.forEach(star => {
                const fill = star.dataset.value <= value ? 100 : 0;
                star.style.setProperty('--fill', `${fill}%`);
            });
        }

        function sendRating(value) {
            if (ratingSend) return;
            document.getElementById('private_feedback_rate').value = value;
            const form = $('form.private-feedback-rating-form')[0];
            const formData = new FormData(form);
            fetch(PrivateFeedback.ajax_url + '?f=rate', {
                method: 'POST',
                body: formData,
            })
            .then(res => res.json())
            .then((data) => {
                if (data.success) {
                    console.log('Rating saved:', data);
                    $('.private-feedback-rating-text').text(data.data.message);
                    ratingSend = true;
                } else {
                    console.error('Error saving rating:', data);
                }
            })
            .catch(err => console.error('Error:', err));
        }
    });
}(jQuery));
        