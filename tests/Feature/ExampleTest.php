<?php

test('landing page can be rendered', function () {
    $response = $this->get(route('home'));

    $response
        ->assertSuccessful()
        ->assertSee('<link rel="icon" href="/favicon.png" type="image/png">', false)
        ->assertSee('Share passwords.', false)
        ->assertSee('Zero-knowledge secret sharing', false)
        ->assertSee('Frequently asked questions', false);
});
