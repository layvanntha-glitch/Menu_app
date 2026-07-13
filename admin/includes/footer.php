    </main>
    <footer class="admin-footer">
        <p>Tasty Bites Admin Panel</p>
    </footer>
    <script>
        (function () {
            var root = document.documentElement;
            function current() {
                return root.getAttribute('data-theme') ||
                    (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            }
            document.querySelectorAll('[data-theme-toggle]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var next = current() === 'dark' ? 'light' : 'dark';
                    root.setAttribute('data-theme', next);
                    try { localStorage.setItem('tb-theme', next); } catch (e) {}
                });
            });
        })();
    </script>
</body>
</html>
