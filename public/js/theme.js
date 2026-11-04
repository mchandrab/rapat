(() => {
    const root = document.documentElement;
    const storageKey = 'erat-theme';

    const currentTheme = () => root.dataset.bsTheme === 'dark' ? 'dark' : 'light';

    const updateButtons = () => {
        const dark = currentTheme() === 'dark';

        document.querySelectorAll('[data-theme-toggle]').forEach((button) => {
            const label = dark ? 'Gunakan tema terang' : 'Gunakan tema gelap';
            button.setAttribute('aria-label', label);
            button.setAttribute('title', label);
        });
    };

    document.addEventListener('click', (event) => {
        const button = event.target.closest('[data-theme-toggle]');

        if (! button) {
            return;
        }

        const theme = currentTheme() === 'dark' ? 'light' : 'dark';
        root.dataset.bsTheme = theme;
        localStorage.setItem(storageKey, theme);
        updateButtons();
    });

    document.addEventListener('DOMContentLoaded', updateButtons);
})();
