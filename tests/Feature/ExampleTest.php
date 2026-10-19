<?php

it('redirects guests from the dashboard to sign in', function () {
    $this->get('/')->assertRedirect('/login');
});
