<?php

namespace App\Models;

// Import các lớp cần thiết
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Sluggable\HasSlug;
use Spatie\Sluggable\SlugOptions;

class Category extends Model
{
    // Thêm HasFactory
    use HasFactory, HasSlug;

    protected $fillable = [
        'name',
        'slug',
        'img_url',
        'parent_id'
    ];

    public function getSlugOptions():SlugOptions
    {
        return SlugOptions::create()
            ->generateSlugsFrom('name')
            ->saveSlugsTo('slug');
    }

    /**
     * Mối quan hệ: Một danh mục có nhiều sản phẩm.
     */
    public function products()
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Mối quan hệ: Một danh mục con thuộc về một danh mục cha.
     */
    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Mối quan hệ: Một danh mục cha có nhiều danh mục con.
     */
    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function allChildren()
    {
        return $this->children()->with('allChildren');
    }
     public function ancestors()
    {
        // with('ancestors') sẽ lặp lại việc tải quan hệ 'parent' một cách đệ quy
        return $this->parent()->with('ancestors');
    }

    /**
     *  Tính độ sâu của danh mục hiện tại.
     */
    public function getDepth(): int
    {
        $depth = 0;
        $ancestor = $this->ancestors; // Lấy ra chuỗi tổ tiên đã được eager-load

        while ($ancestor) {
            $depth++;
            $ancestor = $ancestor->ancestors;
        }

        return $depth;
    }

    /**
     *  Kiểm tra xem danh mục hiện tại có phải là con cháu của một danh mục khác không.
     */
    public function isDescendantOf(Category $parent): bool
    {
        $ancestor = $this->ancestors;

        while ($ancestor) {
            if ($ancestor->id === $parent->id) {
                return true;
            }
            $ancestor = $ancestor->ancestors;
        }

        return false;
    }
}
