<?php

namespace App\Http\Controllers\Api\V1\General;

use App\Events\General\IndustryCustomerAccepted;
use App\Events\General\PhoneVerify;
use App\Http\Controllers\Api\V1\BaseController;
use App\Services\Customer\IndustryService;
use App\Services\Customer\InvitationService;
use App\Services\General\NotificationService;
use App\Transformers\General\NotificationTransformer;
use Illuminate\Support\Facades\Log;

class NotificationController extends BaseController
{
    protected $notificationService;

    /**
     * NotificationController constructor.
     * @param NotificationService $notificationService
     */
    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * @param $id
     * @return \Dingo\Api\Http\Response|void
     */
    public function getById($id)
    {
        try {
            Log::info('----Start NotificationController:getById:error----');
            $notification = $this->notificationService->getById($id);
            Log::info('----End NotificationController:getById:success');

            return $this->response->item($notification, new NotificationTransformer());
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            Log::info('Catch for NotificationController:getById');
            Log::error('Error message: '.$message);
            Log::info('----End NotificationController:getById:error----');

            return $this->response->errorBadRequest($message);
        }
    }


    /**
     * @param $id
     * @return \Dingo\Api\Http\Response|void
     */
    public function acceptIndustryCustomerInvitation($id)
    {
        try {
            Log::info('----Start NotificationController:acceptIndustryCustomerInvitation----');
            $user = \Auth::user();
            $notification = $this->notificationService->getById($id);
            $invitation = $notification->individualCustomerInvitation;

            $industryService = new IndustryService();
            $industryService->checkCustomerIndustryExists($user, $invitation->industry_id);

            $industryService->attachCustomerToIndustry($user, $invitation->industry_id);
            createAndUpdateUserPermissions($user, 'Customer', false);

            $invitationService = new InvitationService();
            $invitationService->destroy($invitation);

            $generalNotificationService = new NotificationService();
            $notification = $generalNotificationService->getIndustryCustomerByInvitationToken($invitation->token);
            if ($notification) {
                $generalNotificationService->destroy($notification);
            }
            // Create customer invitation accepted notification for individual user
            $generalNotificationService->create('individual.customer.invitation.accepted', 'Individual', $invitation->industry->user->id, null, $invitation->industry->id, null, null, null, null, $user->id);

            event(new IndustryCustomerAccepted($invitation->industry, $user));
            event(new PhoneVerify($user));
            Log::info('----End NotificationController:acceptIndustryCustomerInvitation:success----');

            return $this->response->accepted();
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            Log::info('Catch for NotificationController:acceptIndustryCustomerInvitation');
            Log::error('Error message: '.$message);
            Log::info('----End NotificationController:acceptIndustryCustomerInvitation:error----');

            return $this->response->errorBadRequest($message);
        }
    }

}
