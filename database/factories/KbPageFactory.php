<?php

namespace Tabadev\FastHelp\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Tabadev\FastHelp\Models\KbPage;

class KbPageFactory extends Factory
{
    protected $model = KbPage::class;

    public function definition(): array
    {
        $content = fake()->paragraphs(3, true);

        return [
            'url' => fake()->unique()->url(),
            'title' => fake()->sentence(),
            'description' => fake()->text(),
            'content' => $content,
            'content_hash' => md5($content),
            'embedding' => null,
            'status' => 'ok',
            'indexed_at' => now(),
        ];
    }
}
