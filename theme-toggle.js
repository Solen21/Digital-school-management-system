document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme');

    // Apply the saved theme on page load
    if (currentTheme === 'dark') {
        document.body.classList.add('dark-mode');
    }

    // Add event listener to the toggle button
    if (themeToggle) {
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark-mode');
            let theme = 'light';
            if (document.body.classList.contains('dark-mode')) {
                theme = 'dark';
            }
            localStorage.setItem('theme', theme);
        });
    }
});