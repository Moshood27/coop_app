<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class ShariaBoardMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'title',
        'bio',
        'photo_url',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Ensure the stored image path is exposed as a public URL for the frontend.
     */
    public function getPhotoUrlAttribute($value)
    {
        if (empty($value)) {
            return null;
        }
        $val = (string) $value;
        if (Str::startsWith($val, ['http://', 'https://', '/storage/'])) {
            return $val;
        }
        try {
            return Storage::disk('public')->url($val);
        } catch (\Throwable $e) {
            return $val;
        }
    }

    /**
     * Sanitize bio to prevent XSS while allowing basic formatting.
     */
    public function setBioAttribute($value)
    {
        $this->attributes['bio'] = HtmlSanitizer::clean($value);
    }
}
