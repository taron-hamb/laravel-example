<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'action_id',
        'from_id',
        'to_id',
        'company_id',
        'industry_id',
        'appointment_id',
        'customer_invitation_token',
        'individual_customer_invitation_token',
        'staff_invitation_token',
        'message_id',
        'to_role_id',
        'seen_at',
        'read_at',
    ];

    protected $hidden = [
        'customer_invitation_token',
        'staff_invitation_token',
    ];

    protected $appends = [
        'message',
        'link',
    ];

    /**
     * @return mixed
     */
    public function getMessageAttribute()
    {
        $notification = &$this;
        return generateNotificationMessage($notification);
    }

    /**
     * @return mixed
     */
    public function getLinkAttribute()
    {
        $notification = &$this;
        return generateNotificationLink($notification);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function action()
    {
        return $this->belongsTo(NotificationAction::class, 'action_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo(IndividualUserIndustry::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'from_id', 'id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function appointment()
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function customerInvitation()
    {
        return $this->belongsTo(CustomerInvitation::class, 'customer_invitation_token', 'token');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function individualCustomerInvitation()
    {
        return $this->belongsTo(IndividualUserCustomerInvitation::class, 'individual_customer_invitation_token', 'token');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function staffInvitation()
    {
        return $this->belongsTo(StaffInvitation::class, 'staff_invitation_token', 'token');
    }
}
