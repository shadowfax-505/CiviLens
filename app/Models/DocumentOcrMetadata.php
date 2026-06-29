<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentOcrMetadata extends Model
{
    use HasFactory;

    protected $table = 'document_ocr_metadata';

    protected $guarded = [];
}
