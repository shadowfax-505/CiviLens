<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentAiMetadata extends Model
{
    use HasFactory;

    protected $table = 'document_ai_metadata';

    protected $guarded = [];
}
