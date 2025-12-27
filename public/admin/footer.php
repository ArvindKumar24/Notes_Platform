    </main>
    
    <footer class="bg-light border-top py-4 mt-5" style="background-color: var(--bg-secondary) !important; border-color: var(--border-color) !important;">
        <div class="container text-center">
            <p class="mb-1" style="color: var(--text-primary);">&copy; <?php echo date("Y"); ?> Notes Share Admin Panel. All rights reserved.</p>
            <p class="text-muted mb-0" style="color: var(--text-secondary) !important;">Manage knowledge, maintain quality 🔒</p>
        </div>
    </footer>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Dark Mode Toggle
        const darkModeToggle = document.getElementById('darkModeToggle');
        const htmlElement = document.documentElement;
        
        // Load user preference from localStorage
        function loadTheme() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const theme = savedTheme === 'auto' ? (prefersDark ? 'dark' : 'light') : savedTheme;
            
            htmlElement.setAttribute('data-theme', theme);
            updateToggleIcon(theme);
        }
        
        // Update toggle icon based on theme
        function updateToggleIcon(theme) {
            if (darkModeToggle) {
                const icon = darkModeToggle.querySelector('i');
                if (theme === 'dark') {
                    icon.classList.remove('bi-moon');
                    icon.classList.add('bi-sun');
                } else {
                    icon.classList.remove('bi-sun');
                    icon.classList.add('bi-moon');
                }
            }
        }
        
        // Toggle dark mode
        if (darkModeToggle) {
            darkModeToggle.addEventListener('click', () => {
                const currentTheme = htmlElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                
                htmlElement.setAttribute('data-theme', newTheme);
                localStorage.setItem('theme', newTheme);
                updateToggleIcon(newTheme);
            });
        }
        
        // Load theme on page load
        loadTheme();
        
        // Listen for system theme changes
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', (e) => {
            const theme = e.matches ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', theme);
            localStorage.setItem('theme', theme);
            updateToggleIcon(theme);
        });
    </script>
</body>
</html>
