// Previews the rating while hovering/focusing the star buttons of forms marked with data-rating.
(function () {
    document.querySelectorAll('form[data-rating] [data-rating-value]').forEach((group) => {
        const buttons = group.querySelectorAll('button[value]');
        const current = Number(group.dataset.ratingValue);

        function highlight(stars) {
            buttons.forEach((button) => {
                const on = Number(button.value) <= stars;
                button.classList.toggle('text-amber-400', on);
                button.classList.toggle('text-gray-300', !on);
            });
        }

        buttons.forEach((button) => {
            button.addEventListener('mouseenter', () => highlight(Number(button.value)));
            button.addEventListener('focus', () => highlight(Number(button.value)));
        });
        group.addEventListener('mouseleave', () => highlight(current));
        group.addEventListener('focusout', () => highlight(current));
    });
})();
