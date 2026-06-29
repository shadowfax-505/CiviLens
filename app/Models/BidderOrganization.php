<?php

namespace App\Models;

use Database\Factories\BidderOrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'registration_number', 'contact_person', 'email', 'phone', 'website', 'address', 'status'])]
class BidderOrganization extends Model
{
    /** @use HasFactory<BidderOrganizationFactory> */
    use HasFactory, SoftDeletes;

    public function bidSubmissions(): HasMany
    {
        return $this->hasMany(BidSubmission::class);
    }
}
