{{-- Login de los dos paneles: Livewire responde con Cache-Control no-store, así
     que al volver con Atrás/Adelante el navegador recarga la página y repone lo
     escrito en los inputs sin disparar "input". El componente queda con esos
     campos en null y el ingreso los rechaza como obligatorios aunque se vean
     completos. Al mostrarse la página por el historial, cada campo de texto con
     wire:model y valor avisa a Livewire con un "input". La contraseña queda
     afuera: el navegador no la restaura y el gestor de contraseñas ya dispara
     sus eventos. --}}
<script data-login-back-navigation-sync>
    (() => {
        const skippedTypes = ['hidden', 'password', 'checkbox', 'radio', 'file'];

        const syncRestoredValues = () => {
            document.querySelectorAll('input').forEach((input) => {
                if (skippedTypes.includes(input.type) || input.value === '') {
                    return;
                }

                const hasWireModel = [...input.attributes].some((attribute) => attribute.name.startsWith('wire:model'));

                if (hasWireModel) {
                    input.dispatchEvent(new Event('input', { bubbles: true }));
                }
            });
        };

        window.addEventListener('pageshow', (event) => {
            if (event.persisted) {
                return;
            }

            if (performance.getEntriesByType('navigation')[0]?.type !== 'back_forward') {
                return;
            }

            if (window.Livewire?.all?.().length) {
                syncRestoredValues();
            } else {
                document.addEventListener('livewire:initialized', syncRestoredValues, { once: true });
            }
        });
    })();
</script>
