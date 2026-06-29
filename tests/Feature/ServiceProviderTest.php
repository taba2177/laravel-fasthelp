<?php

it('merges the fasthelp config', function () {
    expect(config('fasthelp.user_model'))->not->toBeNull();
});
