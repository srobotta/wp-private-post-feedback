/* global jQuery */
(function($) {
  	$(function() {
        $('.private-feedback-toggle').on('click', function(e) {
            e.preventDefault();
            $('.private-feedback-form').toggle();
        });

        const stars = document.querySelectorAll('.private-feedback-star');
        const container = document.querySelector('.private-feedback-stars');
        let existingRating = parseFloat(PrivateFeedbackRating.existing_rating) || 0;
        let currentRating = existingRating;

        setFractionalRating(existingRating);

        stars.forEach(star => {
            // Hover
            star.addEventListener('mouseover', () => {
                highlightUpTo(parseInt(star.dataset.value));
            });

            // Click
            star.addEventListener('click', () => {
                existingRating = parseInt(star.dataset.value);
                currentRating = existingRating;
                setFractionalRating(existingRating);
                sendRating(existingRating);
            });
        });

        // Reset to saved rating when leaving all stars
        container.addEventListener('mouseleave', () => {
            setFractionalRating(existingRating);
        });

        function setFractionalRating(rating) {
            stars.forEach(star => {
                const value = parseInt(star.dataset.value);
                let fill = 0;
                if (rating >= value) fill = 100;
                else if (rating + 1 > value) fill = (rating - (value - 1)) * 100;
                star.style.setProperty('--fill', `${fill}%`);
            });
        }

        function highlightUpTo(value) {
            stars.forEach(star => {
                const fill = star.dataset.value <= value ? 100 : 0;
                star.style.setProperty('--fill', `${fill}%`);
            });
        }

        function sendRating(value) {
            document.getElementById('private_feedback_rate').value = value;
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
        