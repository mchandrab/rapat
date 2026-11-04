<script>
    (() => {
        const saved = localStorage.getItem('erat-theme');
        const preferred = window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
        document.documentElement.dataset.bsTheme = saved === 'dark' || saved === 'light' ? saved : preferred;
    })();
</script>
