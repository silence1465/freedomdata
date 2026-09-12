<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public $timestamps = false;
    protected $fillable = ['user_id', 'title', 'message', 'type', 'link', 'is_read', 'created_at'];

    protected function casts(): array
    {
        return ['is_read' => 'boolean', 'created_at' => 'datetime'];
    }

    public function user() { return $this->belongsTo(User::class); }

    /** Helper to send a notification */
    public static function send(int $userId, string $title, string $message, string $type = 'info', ?string $link = null): self
    {
        return self::create([
            'user_id' => $userId,
            'title' => $title,
            'message' => $message,
            'type' => $type,
            'link' => $link,
            'created_at' => now(),
        ]);
    }
}
