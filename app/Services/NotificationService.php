<?php


namespace App\Services\General;

use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Redis;

class NotificationService
{
    /**
     * @param $id
     * @return mixed
     * @throws \Exception
     */
    public function getById($id)
    {
        $user = \Auth::user();
        $notification = $user->notifications()->find($id)->load('action.type');
        if (!isset($notification)) {
            throw new \Exception('Notification not found');
        }

        return $notification;
    }

    /**
     * @param $notification
     * @return mixed
     * @throws \Exception
     */
    public function destroy($notification)
    {
        return $notification->delete();
    }

    /**
     * @param $action
     * @param $fromId
     * @param $toId
     * @param $companyId
     * @param $industryId
     * @param $appointmentId
     * @param $companyCustomerInvitationToken
     * @param $industryCustomerInvitationToken
     * @param $staffInvitationToken
     * @param $toRole
     * @return mixed
     */
    public function store($action, $fromId, $toId, $toRole, $companyId, $industryId, $appointmentId, $companyCustomerInvitationToken, $industryCustomerInvitationToken, $staffInvitationToken)
    {
        $data = [
            'action_id' => $action->id,
            'from_id' => $fromId,
            'to_id' => $toId,
            'company_id' => $companyId,
            'industry_id' => $industryId,
            'appointment_id' => $appointmentId,
            'customer_invitation_token' => $companyCustomerInvitationToken,
            'individual_customer_invitation_token' => $industryCustomerInvitationToken,
            'staff_invitation_token' => $staffInvitationToken,
            'to_role_id' => $toRole->id,
        ];

        return Notification::create($data);
    }

    /**
     * @param $appointment
     * @param $actions
     * @return mixed
     */
    public function getAppointmentSelectedNotificationsIds($appointment, $actions)
    {
        $notificationIds = $appointment->notifications()
                                       ->whereHas('action', function (Builder $query) use ($actions) {
                                           $query->whereIn('name', $actions);
                                       })
                                       ->pluck('id');

        return $notificationIds;
    }

    /**
     * @param $token
     * @return mixed
     */
    public function getByCustomerInvitationToken($token)
    {
        return Notification::where('customer_invitation_token', $token)->first();
    }

    /**
     * @param $token
     * @return mixed
     */
    public function getIndustryCustomerByInvitationToken($token)
    {
        return Notification::where('individual_customer_invitation_token', $token)->first();
    }

    /**
     * @param $ids
     * @return mixed
     */
    public function deleteMultipleNotifications($ids)
    {
        $user = \Auth::user();
        return $user->notifications()->whereIn('id', $ids)->delete();
    }

    /**
     * Make all owner notifications seen
     * @return array
     */
    public function makeSeen()
    {
        $user = \Auth::user();
        $now = Carbon::now();

        $personalRoleIds = UserRoleService::getPersonalRoleIds();
        if (in_array($user->active_role_id, $personalRoleIds)) {
            $roles = $personalRoleIds;
        } else {
            $ownerRole = getRoleByName('Owner');
            $roles = [$ownerRole->id];
        }

        return $user->notifications()->where('seen_at', null)
                                      ->whereIn('to_role_id', $roles)
                                      ->update(['seen_at' => $now]);
    }

    /**
     * Make owner notification read
     * @param $notification
     * @return array
     */
    public function makeRead($notification)
    {
        $now = Carbon::now();
        $notification->read_at = $now;
        return $notification->save();
    }

    /**
     * @param $actionName
     * @param $toRoleName
     * @param $toId
     * @param $companyId
     * @param $industryId
     * @param $appointmentId
     * @param $companyCustomerInvitationToken
     * @param $industryCustomerInvitationToken
     * @param $staffInvitationToken
     * @param $fromId
     * @return mixed
     */
    public function create($actionName, $toRoleName, $toId, $companyId, $industryId, $appointmentId, $companyCustomerInvitationToken, $industryCustomerInvitationToken, $staffInvitationToken, $fromId = null)
    {
        if (!$fromId) {
            $fromId = \Auth::id();
        }
        $action = getNotificationAction($actionName);
        $toRole = getRoleByName($toRoleName);

        $notification = $this->store($action, $fromId, $toId, $toRole, $companyId, $industryId, $appointmentId, $companyCustomerInvitationToken, $industryCustomerInvitationToken, $staffInvitationToken);
        $this->publishToRedis($notification);

        return $notification;
    }

    /**
     * Publish the new created notification to redis, to get it in node-socket
     *
     * @param $notification
     * @return mixed
     */
    public function publishToRedis($notification)
    {
        $toUser = $notification->toUser;
        $permalink = "account$toUser->permalink";

        return Redis::publish('notification', json_encode([
            'permalink' => $permalink,
            'notificationId' => $notification->id,
        ]));
    }
}
