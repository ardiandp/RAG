<?php

namespace App\Models;

use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory;

    protected $fillable = [
        'knowledge_source_id',
        'title',
        'content',
        'status',
    ];

    /**
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    /**
     * @return HasMany<DocumentChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(DocumentChunk::class)->orderBy('chunk_index');
    }
}
