(function () {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.querySelector('[data-sidebar-backdrop]');
    const toggles = document.querySelectorAll('[data-sidebar-toggle]');

    if (!sidebar || !backdrop) {
        return;
    }

    function setOpen(open) {
        sidebar.classList.toggle('-translate-x-full', !open);
        backdrop.classList.toggle('hidden', !open);
        toggles.forEach((t) => t.setAttribute('aria-expanded', String(open)));
    }

    toggles.forEach((t) => t.addEventListener('click', () => {
        setOpen(sidebar.classList.contains('-translate-x-full'));
    }));
    backdrop.addEventListener('click', () => setOpen(false));
})();
