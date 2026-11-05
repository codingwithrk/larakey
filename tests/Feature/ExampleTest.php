<?php

test('the application redirects to the settings window', function () {
    $response = $this->get('/');

    $response->assertRedirect(route('settings.edit'));
});
