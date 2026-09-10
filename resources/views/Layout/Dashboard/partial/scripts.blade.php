<script src="{{ asset('modernize/src/assets/libs/jquery/dist/jquery.min.js') }}"></script>
<script src="{{ asset('modernize/src/assets/libs/bootstrap/dist/js/bootstrap.bundle.min.js') }}"></script>
<script src="{{ asset('modernize/src/assets/js/sidebarmenu.js') }}"></script>
<script src="{{ asset('modernize/src/assets/js/app.min.js') }}"></script>
<script src="{{ asset('modernize/src/assets/libs/simplebar/dist/simplebar.js') }}"></script>
@livewireScripts
<script>
    (() => {
        const normalize = (value) => value
            .toLocaleLowerCase('id-ID')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .trim();

        const filterOptions = (input) => {
            const select = document.getElementById(input.dataset.selectSearch);

            if (!select) {
                return;
            }

            const keyword = normalize(input.value);

            Array.from(select.options).forEach((option, index) => {
                const visible = index === 0
                    || option.selected
                    || normalize(option.textContent).includes(keyword);

                option.hidden = !visible;
                option.style.display = visible ? '' : 'none';
            });
        };

        const filterCheckboxes = (input) => {
            const container = document.getElementById(input.dataset.checkboxSearch);

            if (!container) {
                return;
            }

            const keyword = normalize(input.value);

            container.querySelectorAll('[data-checkbox-option]').forEach((option) => {
                const checkbox = option.querySelector('input[type="checkbox"]');
                const visible = checkbox?.checked
                    || normalize(option.textContent).includes(keyword);

                option.hidden = !visible;
            });
        };

        const refreshSearches = (container = document) => {
            container.querySelectorAll('[data-select-search]').forEach(filterOptions);
            container.querySelectorAll('[data-checkbox-search]').forEach(filterCheckboxes);
        };

        document.addEventListener('input', (event) => {
            if (event.target.matches('[data-select-search]')) {
                filterOptions(event.target);
            }

            if (event.target.matches('[data-checkbox-search]')) {
                filterCheckboxes(event.target);
            }
        });

        document.addEventListener('hidden.bs.modal', (event) => {
            event.target.querySelectorAll('[data-select-search]').forEach((input) => {
                input.value = '';
                filterOptions(input);
            });
        });

        document.addEventListener('livewire:init', () => {
            Livewire.hook('morph.updated', () => refreshSearches());
        });

        refreshSearches();
    })();
</script>
@stack('scripts')
