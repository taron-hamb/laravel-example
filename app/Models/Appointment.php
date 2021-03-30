<?php

namespace App\Models;

use App\Services\General\AppointmentService;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    protected $fillable = [
        'title',
        'start_time',
        'end_time',
        'date',
        'price',
        'duration',
        'creator_id',
        'role_id',
        'customer_id',
        'company_id',
        'staff_id',
        'individual_user_industry_id',
        'service_id',
        'status_id',
        'messages_allowed',
        'permalink',
        'note_from_customer',
        'note_from_creator',
        'deleted_by_owner_at',
        'deleted_by_staff_at',
        'deleted_by_customer_at',
        'deleted_by_individual_user_at',
    ];

    protected $appends = [
        'is_accept_allowed',
    ];

    public function status()
    {
        return $this->belongsTo(AppointmentStatus::class, 'status_id');
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id', 'id');
    }

    public function service()
    {
        return $this->belongsTo(Service::class);
    }

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'creator_id', 'id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class,'customer_id', 'id');
    }

    public function individualUserIndustry()
    {
        return $this->belongsTo(IndividualUserIndustry::class,'individual_user_industry_id', 'id');
    }

    public function industry()
    {
        return $this->belongsTo(Industry::class, 'individual_user_industry_id', 'id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'appointment_id', 'id');
    }

    public function history()
    {
        return $this->hasMany(AppointmentHistory::class);
    }

    public function getIsAcceptAllowedAttribute()
    {
        return AppointmentService::getIsAcceptAllowed($this);
    }
}
