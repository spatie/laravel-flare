<?php

namespace Workbench\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/**
 * @template TModel of \Workbench\App\Models\Post
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<TModel>
 */
class PostFactory extends Factory
{

    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TModel>
     */
    protected $model = Post::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
        ];
    }
}
