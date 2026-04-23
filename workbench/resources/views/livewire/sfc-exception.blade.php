<?php

new class extends \Livewire\Component {
    public function mount(): void
    {
        throw new \Exception('SFC exception in mount');
    }
};

?>

<div>
    <h1>This should not render</h1>
</div>
