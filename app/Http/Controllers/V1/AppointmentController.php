<?php

namespace App\Http\Controllers\Api\V1\General;

use App\Http\Controllers\Api\V1\BaseController;
use App\Services\General\AppointmentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use App\Transformers\General\AppointmentTransformer;

class AppointmentController extends BaseController
{
    protected $appointmentService;

    /**
     * AppointmentController constructor.
     * @param AppointmentService $appointmentService
     */
    public function __construct(AppointmentService $appointmentService)
    {
        $this->appointmentService = $appointmentService;
    }

    /**
     * @param Request $request
     * @return \Dingo\Api\Http\Response|void
     */
    public function index(Request $request)
    {
        try {
            Log::info('----Start AppointmentController:index----');
            $appointments = $this->appointmentService->getAll($request->all())->sortByDesc('created_at');
            Log::info('----End AppointmentController:index:success----');

            return $this->response->collection($appointments, AppointmentTransformer::class);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            Log::info('Catch for AppointmentController:index');
            Log::error('Error message: '.$message);
            Log::info('----End AppointmentController:index:error----');

            return $this->response->errorBadRequest($message);
        }
    }

    /**
     * @param $id
     * @return \Illuminate\Http\JsonResponse|void
     */
    public function getAppointmentRole($id)
    {
        try {
            Log::info('----Start AppointmentController:getAppointmentRole----');
            $appointment = $this->appointmentService->getById($id);
            $role = $this->appointmentService->getUserRoleInAppointment($appointment);
            Log::info('----End AppointmentController:getAppointmentRole:success----');

            return response()->json(['role' => $role]);
        } catch (\Exception $exception) {
            $message = $exception->getMessage();
            Log::info('Catch for AppointmentController:getAppointmentRole');
            Log::error('Error message: '.$message);
            Log::info('----End AppointmentController:getAppointmentRole:error----');

            return $this->response->errorBadRequest($message);
        }
    }
}
