<?php

namespace Gleman17\LaravelTools\Models;

use Illuminate\Database\Eloquent\Model;

class AIQueryCache extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'ai_query_cache';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'query_hash',
        'prompt',
        'response',
    ];
}
