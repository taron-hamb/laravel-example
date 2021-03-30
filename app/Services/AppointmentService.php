<?php

namespace App\Services\General;

use App\Exceptions\JsonEncodedException;
use App\Models\Appointment;
use App\Models\AppointmentHistory;
use App\Models\AppointmentStatus;
use App\Services\AbstractService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class AppointmentService extends AbstractService
{
    // The fields, which updates does't change appointment status to pending
    const nonSenseFields = [
        'title',
        'messages_allowed',
        'note_from_customer',
        'end_time',
    ];

    /**
     * @param $data
     * @return Collection
     */
    public function getAll($data)
    {
        $user = \Auth::user();
        $ownerRole = getRoleByName('Owner');

        if ($user->activeRole->id !== $ownerRole->id) {
            $appointments = $this->getPersonalAppointments($user, $data, $ownerRole);
        } else {
            $ownerAppointments = $user->ownerAppointments()->newQuery();
            $appointments = $this->filterAppointments($ownerAppointments, $data)->get();
        }

        return $appointments;

    }

    /**
     * @param $id
     * @return mixed
     */
    public function getById($id)
    {
        return Appointment::find($id);
    }

    /**
     * Get all appointments of user on all personal roles
     *
     * @param $user
     * @param $data
     * @param $ownerRole
     * @return Collection
     */
    public function getPersonalAppointments($user, $data, $ownerRole)
    {
        $rolesQuery = $user->roles();
        if (!empty($data) && array_key_exists('roles', $data)) {
            $rolesQuery->whereIn('role_id', $data['roles']);
        }

        $roles = $rolesQuery->get();

        $personalAppointments = new Collection();
        foreach ($roles as $role) {
            $relationName = $role->name.'Appointments';
            $relationAppointments = $user->$relationName()->newQuery();
            // get personal appointments related filter data
            $relationAppointments = $this->filterAppointments($relationAppointments, $data, true, $role);
            $personalAppointments = $personalAppointments->merge($relationAppointments->get());
        }

        return $personalAppointments;
    }

    /**
     * Filter the appointments by query relation and filterData
     *
     * @param $relation
     * @param $filterData
     * @param $filterByRole
     * @param $role
     * @return mixed
     */
    public function filterAppointments($relation, $filterData, $filterByRole = false, $role = false)
    {
        if (!empty($filterData) && array_key_exists('status', $filterData)) {
            $relation->whereIn('status_id', $filterData['status']);
        }
        if (!empty($filterData['date']['from']) && $filterData['date']['from'] !== 'Invalid date') {
            $relation->whereDate('date','>=', $filterData['date']['from']);
        }
        if (!empty($filterData['date']['to']) && $filterData['date']['to'] !== 'Invalid date') {
            $relation->whereDate('date','<=', $filterData['date']['to']);
        }
        if (!empty($filterData['checkedIndustries']) && $role->name === 'Individual') {
            $relation->whereHas('individualUserIndustry', function ($query) use ($filterData) {
                $query->whereIn('industry_id', $filterData['checkedIndustries']);
            });
        }

        return $relation;
    }

    /**
     * Accept appointment
     * @param $appointment
     * @return boolean
     * @throws \Exception
     */
    public function accept($appointment)
    {
        $statusId = getAppointmentStatusIdByName('accepted');
        $appointment->status_id = $statusId;

        return $appointment->save();
    }

    /**
     * Cancel appointment
     * @param $appointment
     * @return boolean
     * @throws \Exception
     */
    public function cancel($appointment)
    {
        $statusId = getAppointmentStatusIdByName('cancelled');
        $appointment->status_id = $statusId;

        return $appointment->save();
    }

    /**
     * Finish appointment
     * @param $appointment
     * @return boolean
     * @throws \Exception
     */
    public function finish($appointment)
    {
        $statusId = getAppointmentStatusIdByName('finished');
        $appointment->status_id = $statusId;
        if (is_null($appointment->save())) {
            throw new \Exception('Appointment cant be finished');
        }

        return $appointment->save();
    }

    /**
     * Get all appointment statuses
     * @return mixed
     */
    public function getStatuses()
    {
        return AppointmentStatus::get();
    }

    /**
     * @param $appointment
     * @return bool
     */
    public function isFinished($appointment)
    {
        $statusId = getAppointmentStatusIdByName('finished');
        return $appointment->status_id == $statusId;
    }

    /**
     * @param $appointment
     * @return bool
     */
    public function isCancelled($appointment)
    {
        $statusId = getAppointmentStatusIdByName('cancelled');
        return $appointment->status_id == $statusId;
    }

    /**
     * Compare old and new appointment data, and create history
     *
     * @param $appointment
     * @param $data
     * @return array
     */
    public function getUpdateHistory($appointment, $data)
    {
        $user = \Auth::user();
        $userId = $user->id;
        $roleId = $user->active_role_id;

        $fillable = $this->getModelFillable();

        $history = [];
        foreach ($data as $key => $value) {
            if (in_array($key, $fillable) && $key != 'end_time' && $appointment->$key != $value) {
                $prevValue = $appointment->$key;
                $data = [
                    'user_id' => $userId,
                    'role_id' => $roleId,
                    'field' => $key,
                    'prev' => $prevValue,
                    'current' => $value,
                ];
                $history[] = new AppointmentHistory($data);
            }
        }

        return $history;
    }

    /**
     * @return array
     */
    public function getModelFillable()
    {
        $appointmentModel = new Appointment();
        return $appointmentModel->getFillable();
    }

    /**
     * Store multiple appointment history
     *
     * @param $appointment
     * @param $history
     * @return mixed
     */
    public function storeMultipleHistory($appointment, $history)
    {
        return $appointment->history()->saveMany($history);
    }

    /**
     * @param $appointment
     * @param $history
     * @return mixed
     */
    public function storeHistory($appointment, $history)
    {
        return $appointment->history()->save($history);
    }

    /**
     * @param $data
     * @param $appointment
     * @return array
     */
    public function getChangedFields($appointment, $data)
    {
        $fillable = $this->getModelFillable();
        $changedFields = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $fillable) && !in_array($key, self::nonSenseFields) && $appointment->$key != $value) {
                $changedFields[] = $key;
            }
        }

        return $changedFields;
    }

    public function makeAcceptedToPending($appointment)
    {
        $acceptedStatusId = getAppointmentStatusIdByName('accepted');
        if ($appointment->status_id == $acceptedStatusId) {
            $pendingStatusId = getAppointmentStatusIdByName('pending');
            $appointment->status_id = $pendingStatusId;
            $appointment->save();
        }

        return $appointment;
    }

    /**
     * @param $owner
     * @param $activeRoleId
     * @return bool
     */
    static function isLoggedInUserCompanyOwner($owner, $activeRoleId)
    {
        $userId = \Auth::id();
        $ownerRole = getRoleByName('Owner');

        return $owner->id == $userId && $activeRoleId == $ownerRole->id;
    }

    /**
     * @param $appointment
     * @param $activeRoleId
     * @return bool
     */
    static function isLoggedInUserStaff($appointment, $activeRoleId)
    {
        $userId = \Auth::id();
        $staffRole = getRoleByName('Staff');

        return $appointment->staff_id == $userId && $activeRoleId == $staffRole->id;
    }

    static function isLastUpdatedByLoggedInUser($lastUpdatedRow, $activeRoleId)
    {
        $userId = \Auth::id();

        return ($lastUpdatedRow && $lastUpdatedRow->user_id == $userId && $lastUpdatedRow->role_id == $activeRoleId);
    }

    static function isLastUpdatedByOwner($owner, $lastUpdatedRow)
    {
        $ownerRole = getRoleByName('Owner');

        return ($lastUpdatedRow && $lastUpdatedRow->user_id == $owner->id && $lastUpdatedRow->role_id == $ownerRole->id);
    }

    static function isLastUpdatedByStaff($staffId, $lastUpdatedRow)
    {
        $staffRole = getRoleByName('Staff');

        return ($lastUpdatedRow && $lastUpdatedRow->user_id == $staffId && $lastUpdatedRow->role_id == $staffRole->id);
    }

    public function isStaffActive($staff)
    {
        return ($staff->email && $staff->password);
    }

    /**
     * @param $startTime
     * @param $duration
     * @return string
     */
    public function getEndTimeByDuration($startTime, $duration)
    {
        return Carbon::createFromFormat('H:i:s', $startTime)
                     ->addMinutes($duration)
                     ->format('H:i:s');
    }

    /**
     * @param $appointment
     * @param $eventName
     * @return mixed
     */
    public function createEventHistory($appointment, $eventName)
    {
        $user = \Auth::user();
        $eventId = getHistoryEventIdByName($eventName);

        $data = [
            'user_id' => $user->id,
            'role_id' => $user->activeRole->id,
            'event_id' => $eventId,
        ];
        $history = new AppointmentHistory($data);

        return $this->storeHistory($appointment, $history);
    }

    /**
     * @param $timeData
     * @param $workingDay
     * @param $breakingTime
     * @throws \Exception
     */
    public function isAppointmentAvailableByTime($timeData, $workingDay, $breakingTime)
    {
        // Collect necessary data in acceptable format
        $dayTime = Carbon::create($timeData['dayTime']);
        $date = Carbon::create($timeData['dayTime'])->format('Y-m-d');
        $dateNow = Carbon::now()->format('Y-m-d');
        $fullDateNow = Carbon::now();
        if ($dayTime->isBefore($dateNow)) {
            throw new JsonEncodedException(['date' => 'Appointment cannot start in the past date.'], 422);
        } elseif ($date === $dateNow && $dayTime->isBefore($fullDateNow)) {
            throw new JsonEncodedException(['start_time' => 'Appointment cannot start in the past hours.'], 422);
        } else {
            // Collect necessary data in acceptable format
            $start = $dayTime->toTimeString();
            $end = $this->getEndTimeByDuration($start, $timeData['duration']);
            $startTime =  Carbon::parse($start)->setDateFrom($dayTime);
            $endTime =  Carbon::parse($end)->setDateFrom($dayTime);
            $formattedTimeData = [
                'startTime' => $startTime,
                'endTime' => $endTime,
                'dayTime' => $dayTime
            ];
            // Check availability by working days and working hours
            $this->isAppointmentOnNotWorkingTime($formattedTimeData, $workingDay);
            $this->isAppointmentOnBreakingHours($formattedTimeData, $breakingTime);
        }

    }

    /**
     * @param $timeData
     * @param $workingDay
     * @throws \Exception
     */
    public function isAppointmentOnNotWorkingTime($timeData, $workingDay)
    {
        if (is_null($workingDay)) {
            throw new JsonEncodedException(['date' => 'Unable to get working time.'], 422);
        } else if (!$workingDay->is_working){
            throw new JsonEncodedException(['date' => 'Appointment must be on working day.'], 422);
        } else {
            // Collect necessary data in acceptable format
            $workStart = Carbon::parse($workingDay->start_time)->setDateFrom($timeData['dayTime']);
            $workEnd = Carbon::parse($workingDay->end_time)->setDateFrom($timeData['dayTime']);
            $isAppointmentOnNotWorkingHours = $timeData['startTime']->isBefore($workStart)
                || $timeData['startTime']->isAfter($workEnd)
                || $timeData['startTime']->equalTo($workEnd)
                || $workEnd->isBetween($timeData['startTime'], $timeData['endTime']);
            if ($isAppointmentOnNotWorkingHours){
                throw new JsonEncodedException(['start_time' => 'Appointment must be on working hours.'], 422);
            }
        }
    }

    /**
     * @param $timeData
     * @param $breakingTime
     * @throws \Exception
     */
    public function isAppointmentOnBreakingHours($timeData, $breakingTime)
    {
        if ($breakingTime['isBreaking']){
            // Collect necessary data in acceptable format
            $breakStart = Carbon::parse($breakingTime['start'])->setDateFrom($timeData['dayTime']);
            $breakEnd = Carbon::parse($breakingTime['end'])->setDateFrom($timeData['dayTime']);
            $isAppointmentOnBreakTime = $breakStart->between($timeData['startTime'], $timeData['endTime'], true)
                || $breakEnd->betweenExcluded($timeData['startTime'], $timeData['endTime'])
                || $breakEnd->equalTo($timeData['endTime'])
                || $timeData['startTime']->betweenExcluded($breakStart, $breakEnd);
            if ($isAppointmentOnBreakTime) {
                throw new JsonEncodedException(['start_time' => 'Appointment is set on breaking hours. Please select valid timing for appointment.'], 422);
            }
        }
    }

    /**
     * @param $appointment
     * @return string
     */
    public function getUserRoleInAppointment($appointment)
    {
        $user = \Auth::user();
        if ($appointment->staff_id == $user->id) {
            $role = 'staff';
        } else if ($appointment->customer_id == $user->id) {
            $role = 'customer';
        } else if ($appointment->individualUserIndustry && $appointment->individualUserIndustry->user_id === $user->id) {
            $role = 'individual';
        } else {
            $role = 'owner';
        }

        return $role;
    }

    /**
     * Make the appointment deleted for owner
     *
     * @param $appointment
     * @return mixed
     */

    public function makeDeleted($appointment)
    {
        $now = Carbon::now();
        $appointment->deleted_by_owner_at = $now;
        return $appointment->save();
    }
}
