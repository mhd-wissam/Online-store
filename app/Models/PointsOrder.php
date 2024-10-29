<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PointsOrder extends Model
{
    use HasFactory;
    protected $fillable =[
        'user_id',
        
        'totalPrice',
        'state',
    ];

    protected $hidden=[
        'updated_at',
    ];
    public function users(){
        return $this->belongsTo(User::class,'user_id');
    }

    public function pointCarts(){
        return $this->hasMany(PointsCart::class,'pointsOrders_id');
    }

}
