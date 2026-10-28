        </main>
    </div>
</div>
<script src="<?= asset('vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
<script>
    // Ask before any form with data-confirm is submitted (delete, rollback, ...)
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (e) => {
            if (!window.confirm(form.dataset.confirm)) e.preventDefault();
        });
    });
    // Disable the submit button of long-running forms and show a spinner
    document.querySelectorAll('form[data-busy]').forEach((form) => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type=submit], button:not([type])');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + form.dataset.busy;
            }
        });
    });
</script>
</body>
</html>
