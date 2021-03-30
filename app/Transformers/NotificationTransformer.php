<?php

namespace App\Transformers\General;

use App\Models\Notification;
use League\Fractal\TransformerAbstract;

class NotificationTransformer extends TransformerAbstract
{
    public function transform(Notification $notification)
    {
        return [
            'id' => $notification->id,
            'action_id' => $notification->action_id,
            'from_id' => $notification->from_id,
            'to_id' => $notification->to_id,
            'company_id' => $notification->company_id,
            'industry_id' => $notification->industry_id,
            'appointment_id' => $notification->appointment_id,
            'message_id' => $notification->message_id,
            'to_role_id' => $notification->to_role_id,
            'seen_at' => $notification->seen_at,
            'read_at' => $notification->read_at,
            'created_at' => $notification->created_at,
            'updated_at' => $notification->updated_at,
            'message' => $notification->message,
            'link' => $notification->link,
            'action' => $notification->action,
            'individual_customer_invitation_token' => $notification->individual_customer_invitation_token,
        ];
    }
}
