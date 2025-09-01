<?php
declare(strict_types=1);

class User extends Model
{
    protected $table = 'users';          // tip yok (Model ile uyum için)
    protected $primaryKey = 'id';
    protected $fillable = [
        'username','email','password','full_name','role','department',
        'position','phone','avatar','is_active','two_factor_enabled'
    ];
    protected $hidden = ['password','two_factor_secret'];

    // İstersen yardımcılar:
    public function findByEmail(string $email)
    {
        return $this->findBy('email', $email);
    }
}