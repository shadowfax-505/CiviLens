<?php

it('does not expose the temporary form-submit debug endpoint', function (): void {
    $this->post('/_debug/form-submit')->assertNotFound();
});
