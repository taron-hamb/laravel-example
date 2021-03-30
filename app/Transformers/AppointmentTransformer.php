<?php

namespace App\Transformers\General;

use App\Models\Appointment;
use League\Fractal\TransformerAbstract;

class AppointmentTransformer extends TransformerAbstract
{
    public function transform(Appointment $appointment)
    {
        return [
            'id' => $appointment->id,
            'title' => $appointment->title,
            'price' => $appointment->price,
            'duration' => $appointment->duration,
            'start_time' => $appointment->start_time,
            'role_id' => $appointment->role_id,
            'creator_id' => $appointment->creator_id,
            'end_time' => $appointment->end_time,
            'company_id' => $appointment->company_id,
            'company' => $appointment->company,
            'service_id' => $appointment->service_id,
            'service' => $appointment->service,
            'staff_id' => $appointment->staff_id,
            'staff' => $appointment->staff,
            'customer_id' => $appointment->customer_id,
            'customer' => $appointment->customer,
            'permalink' => $appointment->permalink,
            'status_id' => $appointment->status_id,
            'status' => $appointment->status,
            'date' => $appointment->date,
            'history' => $appointment->history,
            'individualUser' => $appointment->individualUserIndustry,
            'industry' => $appointment->industry,
        ];
    }
}
