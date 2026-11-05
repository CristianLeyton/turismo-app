{{-- Botón "mes siguiente" en el encabezado del panel del date-picker de Filament. --}}
<script>
    (function () {
        function enhance(header) {
            if (header.querySelector('.fi-fo-date-time-picker-next-month')) return;

            const monthSelect = header.querySelector('.fi-fo-date-time-picker-month-select');
            if (!monthSelect) return;

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'fi-fo-date-time-picker-next-month';
            button.textContent = '›';
            button.title = 'Mes siguiente';
            button.setAttribute('aria-label', 'Mes siguiente');
            button.style.marginLeft = '4px';
            button.style.border = '1px solid rgb(209 213 219 / 50)';
            button.style.borderRadius = '6px';
            button.style.padding = '3px 8px';
            button.style.cursor = 'pointer';
            button.style.background = 'transparent';
            button.style.color = 'inherit';

            button.addEventListener('click', () => {
                const scope = button.closest('[x-data]');
                const data = scope && window.Alpine ? window.Alpine.$data(scope) : null;

                if (!data || data.focusedMonth === undefined) return;

                // El watcher de focusedMonth recalcula focusedDate (y de ahí
                // año y mes) via dayjs, que envuelve diciembre → enero.
                data.focusedMonth = Number(data.focusedMonth) + 1;
            });

            monthSelect.after(button);
        }

        function scan(root) {
            root.querySelectorAll?.('.fi-fo-date-time-picker-panel-header').forEach(enhance);
        }

        scan(document);

        new MutationObserver((records) => {
            for (const record of records) {
                scan(record.target);
            }
        }).observe(document.body, { childList: true, subtree: true });

        document.addEventListener('livewire:navigated', () => scan(document));
    })();
</script>
