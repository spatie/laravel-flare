<?php

namespace Workbench\App\Livewire;

use App\Models\User;
use Livewire\Component;

class RandomUser extends Component
{
    public array $users;

    public string $current;

    public function mount()
    {
        $this->users = $this->fetchAll();
    }

    protected function fetchAll(): array
    {
        return User::query()->pluck('email')->all();
    }

    public function randomize()
    {
        if (! isset($this->users)) {
            $this->users = $this->fetchAll();
        }

        $this->current = $this->users[array_rand($this->users)];
    }

    public function select(int $number, array $test)
    {
        $this->current = $this->users[$number] ?? null;
    }

    public function randomizeDatabseWise()
    {
        $this->current = User::query()->inRandomOrder()->limit(1)->first()->email;
    }

    public function render()
    {
        return <<<'HTML'
        <div>
            <p>The current user is {{ $current ?? 'none' }}</p>
            <button wire:click="randomize" class="mt-4 bg-blue-500 text-white px-4 py-2 rounded">Randomize user</button>
            <button wire:click="randomizeDatabseWise" class="mt-4 bg-green-500 text-white px-4 py-2 rounded">Randomize user (database)</button>
            <button wire:click="select(2, [1,1,3,5])" class="mt-4 bg-gray-500 text-white px-4 py-2 rounded">Select (2) users</button>
        </div>
        HTML;
    }
}
