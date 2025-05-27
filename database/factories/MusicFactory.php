<?php

namespace Database\Factories;

use App\Models\Music;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class MusicFactory extends Factory
{
    protected $model = Music::class;

    public function definition(): array
    {
        return [
            'filepath' => $this->faker->word(),
            'artist' => $this->faker->word(),
            'title' => $this->faker->word(),
            'release_year' => $this->faker->word(),
            'album' => $this->faker->word(),
            'metadata' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}
