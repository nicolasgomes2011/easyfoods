<?php

namespace App\Rules;

use App\Models\Category;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

/**
 * A category name must turn into a slug that is not empty and not used yet by
 * another category of the same restaurant (unique(restaurant_id, slug)),
 * inactive ones included. "Bebidas" and "bebidas!" clash: same slug.
 *
 * Shared by every screen that saves a category, so the slug they store is
 * always checked the same way.
 */
final class UniqueCategoryName implements ValidationRule
{
    public function __construct(
        private readonly ?int $restaurantId,
        private readonly ?int $ignoreCategoryId = null,
    ) {}

    public static function slugFor(string $name): string
    {
        return Str::slug($name);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $slug = self::slugFor((string) $value);

        if ($slug === '') {
            $fail('Use pelo menos uma letra ou número no nome da categoria.');

            return;
        }

        $taken = Category::where('restaurant_id', $this->restaurantId)
            ->where('slug', $slug)
            ->when($this->ignoreCategoryId, fn ($query) => $query->whereKeyNot($this->ignoreCategoryId))
            ->exists();

        if ($taken) {
            $fail('Já existe uma categoria com esse nome.');
        }
    }
}
