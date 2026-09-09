<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HelpArticle extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'audience',
        'content',
        'sort',
        'is_published',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'sort' => 'integer',
        ];
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeForAudience(Builder $query, string $audience): Builder
    {
        return $query->where(function (Builder $query) use ($audience) {
            $query->where('audience', 'todos')
                ->orWhere('audience', $audience);
        });
    }
}
