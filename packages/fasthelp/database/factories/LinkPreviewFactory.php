<?php

namespace Tabadev\FastHelp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tabadev\FastHelp\Models\LinkPreview;

class LinkPreviewFactory extends Factory
{
    protected $model = LinkPreview::class;

    public function definition(): array
    {
        return [
            'url' => fake()->unique()->url(),
            'title' => fake()->sentence(),
            'description' => fake()->text(),
            'image' => fake()->imageUrl(),
            'fetched_at' => now(),
        ];
    }
}
