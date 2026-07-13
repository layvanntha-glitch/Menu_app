    </main>
    <footer class="site-footer">
        <div class="container">
            <p><span class="brand-mark"><?= function_exists('brand_mark_html') ? brand_mark_html() : '🍽️' ?></span> &copy; <?= date('Y') ?> <?= function_exists('restaurant_name') ? e(restaurant_name()) : 'Tasty Bites' ?> — freshly made, just for you.</p>
            <p class="footer-links">
                <a href="/menu/index.php">Menu</a>
                <?php if (function_exists('is_user') && is_user()): ?>
                    <a href="/menu/account.php">My Account</a>
                <?php else: ?>
                    <a href="/menu/login.php">Sign in</a>
                    <a href="/menu/register.php">Create account</a>
                <?php endif; ?>
                <a href="/menu/admin/login.php">Staff / Admin</a>
            </p>
        </div>
    </footer>

    <div class="toast-wrap" id="toastWrap" aria-live="polite" aria-atomic="true"></div>

    <script>
        // ---- Telegram Mini App integration (only runs inside Telegram) ----
        (function () {
            var wa = window.Telegram && window.Telegram.WebApp;
            if (!wa || !wa.initData) return;   // not opened as a Mini App
            try {
                wa.ready();
                wa.expand();
                // Follow Telegram's light/dark theme inside the app.
                document.documentElement.setAttribute('data-theme',
                    wa.colorScheme === 'dark' ? 'dark' : 'light');
                document.body.classList.add('in-telegram');

                // Hand the signed initData to the server once so it can notify
                // this user about their orders (kept in the PHP session).
                if (!sessionStorage.getItem('tg-linked')) {
                    fetch('/menu/tg_link.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: 'init=' + encodeURIComponent(wa.initData)
                    }).then(function () { sessionStorage.setItem('tg-linked', '1'); })
                      .catch(function () {});
                }
            } catch (e) {}
        })();

        // ---- Theme toggle (persisted in localStorage) ----
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

        // ---- Toast helper ----
        function tbToast(message) {
            var wrap = document.getElementById('toastWrap');
            if (!wrap) return;
            var el = document.createElement('div');
            el.className = 'toast';
            el.innerHTML = '<span class="toast-dot">✓</span>' +
                '<span></span>';
            el.lastElementChild.textContent = message;
            wrap.appendChild(el);
            setTimeout(function () { el.remove(); }, 3200);
        }

        // Show a toast after adding to the cart, then clean the URL.
        (function () {
            var params = new URLSearchParams(window.location.search);
            if (params.has('added')) {
                tbToast('Added to your cart');
                params.delete('added');
                var qs = params.toString();
                history.replaceState({}, '', window.location.pathname + (qs ? '?' + qs : ''));
            }
        })();
    </script>
</body>
</html>
