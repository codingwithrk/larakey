const form = document.getElementById('settings-form');

if (form) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const save = (input) => {
        const value = input.type === 'checkbox' ? input.checked : input.type === 'range' ? Number(input.value) : input.value;

        return fetch(form.action, {
            method: 'PATCH',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify({ [input.name]: value }),
        });
    };

    let volumeTimer;

    form.addEventListener('input', (event) => {
        if (event.target.name !== 'volume') {
            return;
        }

        event.target.style.setProperty('--fill', `${event.target.value}%`);
        clearTimeout(volumeTimer);
        volumeTimer = setTimeout(() => save(event.target), 120);
    });

    form.addEventListener('change', (event) => {
        if (event.target.name && event.target.name !== 'volume') {
            save(event.target);
        }
    });
}
