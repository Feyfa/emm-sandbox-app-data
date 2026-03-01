<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Scout\Searchable;
use App\Models\PaymentMethod;
use App\Models\Invoice;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, Searchable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'clerk_user_id',
        'name',
        'email',
        'avatar_url',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * function ini digunakan untuk memilih, field mana saja yang boleh di index oleh scout, jadi saat menjalankan php artisan scout:import "App\Models\User", hanya field ini saja lah yang akan di index, dan field ini juga yang akan muncul saat melakukan search, jadi kalau kita ingin menambahkan field baru ke Meilisearch di server, kita harus menambahkannya di function ini, dan jangan lupa untuk menjalankan php artisan scout:import "App\Models
     * @return array{email: string, id: mixed, name: string}
     */
    public function userIdentities()
    {
        return $this->hasMany(UserIdentity::class);
    }

    public function paymentMethods()
    {
        return $this->hasMany(PaymentMethod::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function toSearchableArray()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
        ];
    }
}
