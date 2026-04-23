<?php

new class extends \Livewire\Component {
    public int $count = 0;

    public function increment(): void
    {
        $this->count++;
    }

    public function decrement(): void
    {
        $this->count--;
    }
};

?>

<div>
    <h1>Single File Counter: {{ $count }}</h1>

    <button wire:click="increment">+</button>
    <button wire:click="decrement">-</button>
</div>
