const form = document.getElementById('settings-form');

if (form) {
    const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

    const request = (url, method, body = null) =>
        fetch(url, {
            method,
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: body ? JSON.stringify(body) : null,
        });

    const save = (input) => {
        const value = input.type === 'checkbox' ? input.checked : input.type === 'range' ? Number(input.value) : input.value;

        return request(form.action, 'PATCH', { [input.name]: value });
    };

    let volumeTimer;

    form.addEventListener('input', (event) => {
        if (event.target.name !== 'volume') {
            return;
        }

        document.getElementById('volume-value').textContent = `${event.target.value}%`;
        clearTimeout(volumeTimer);
        volumeTimer = setTimeout(() => save(event.target), 120);
    });

    form.addEventListener('change', (event) => {
        if (event.target.name && event.target.name !== 'volume' && event.target.id !== 'playground') {
            save(event.target);
        }
    });

    document.getElementById('check-updates').addEventListener('click', async (event) => {
        event.target.textContent = 'Checking…';
        await request(form.dataset.checkUpdatesUrl, 'POST');
        setTimeout(() => (event.target.textContent = 'Check for updates'), 3000);
    });
}
