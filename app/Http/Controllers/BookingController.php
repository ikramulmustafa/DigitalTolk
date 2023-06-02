<?php

namespace DTApi\Http\Controllers;

use DTApi\Models\Job;
use DTApi\Models\Distance;
use Illuminate\Http\Request;
use DTApi\Repository\BookingRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    /**
     * @var BookingRepository
     */
    protected $repository;

    public function __construct(BookingRepository $bookingRepository)
    {
        $this->repository = $bookingRepository;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function index(Request $request)
    {
        if ($request->has('user_id')) {
            $response = $this->repository->getUsersJobs($request->get('user_id'));
        } elseif ($this->isAdminOrSuperAdmin($request)) {
            $response = $this->repository->getAll($request);
        } else {
            $response = null;
        }

        return response($response);
    }

    /**
     * @param $id
     * @return mixed
     */
    public function show($id)
    {
        $job = $this->repository->with('translatorJobRel.user')->find($id);

        return response($job);
    }

    /**
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $this->validateJobRequest($request);

        $data = $request->all();
        $response = $this->repository->store($request->__authenticatedUser, $data);

        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     * @throws ValidationException
     */
    public function update($id, Request $request)
    {
        $this->validateJobRequest($request);

        $data = $request->except(['_token', 'submit']);
        $user = $request->__authenticatedUser;
        $response = $this->repository->updateJob($id, $data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function immediateJobEmail(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->storeJobEmail($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function getHistory(Request $request)
    {
        if ($request->has('user_id')) {
            $response = $this->repository->getUsersJobsHistory($request->get('user_id'), $request);
            return response($response);
        }

        return null;
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJob(Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;
        $response = $this->repository->acceptJob($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function acceptJobWithId(Request $request)
    {
        $jobId = $request->get('job_id');
        $user = $request->__authenticatedUser;
        $response = $this->repository->acceptJobWithId($jobId, $user);

        return response($response);
    }

    /**
     * @param $id
     * @param Request $request
     * @return mixed
     */
    public function cancelJob($id, Request $request)
    {
        $data = $request->all();
        $user = $request->__authenticatedUser;

        $response = $this->repository->cancelJobAjax($data, $user);

        return response($response);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function distanceFeed(Request $request)
    {
        $data = $request->all();
        $distance = $data['distance'] ?? '';
        $time = $data['time'] ?? '';
        $jobId = $data['jobid'] ?? '';
        $sessionTime = $data['session_time'] ?? '';

        $flagged = $data['flagged'] == 'true' ? 'yes' : 'no';
        $manuallyHandled = $data['manually_handled'] == 'true' ? 'yes' : 'no';
        $byAdmin = $data['by_admin'] == 'true' ? 'yes' : 'no';
        $adminComment = $data['admincomment'] ?? '';

        if ($time || $distance) {
            Distance::where('job_id', $jobId)->update(['distance' => $distance, 'time' => $time]);
        }

        if ($adminComment || $sessionTime || $flagged || $manuallyHandled || $byAdmin) {
            Job::where('id', $jobId)->update([
                'admin_comments' => $adminComment,
                'flagged' => $flagged,
                'session_time' => $sessionTime,
                'manually_handled' => $manuallyHandled,
                'by_admin' => $byAdmin
            ]);
        }

        return response('Record updated!');
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function reopen(Request $request)
    {
        $data = $request->all();
        $response = $this->repository->reopen($data);

        return response($response);
    }

    /**
     * @param Request $request
     * @return mixed
     */
    public function resendNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);
        $this->repository->sendNotificationTranslator($job, $jobData, '*');

        return response(['success' => 'Push sent']);
    }

    /**
     * @param Request $request
     * @return \Illuminate\Contracts\Routing\ResponseFactory|\Symfony\Component\HttpFoundation\Response
     */
    public function resendSMSNotifications(Request $request)
    {
        $data = $request->all();
        $job = $this->repository->find($data['jobid']);
        $jobData = $this->repository->jobToData($job);

        try {
            $this->repository->sendSMSNotificationToTranslator($job);
            return response(['success' => 'SMS sent']);
        } catch (\Exception $e) {
            return response(['success' => $e->getMessage()]);
        }
    }

    /**
     * Checks if the authenticated user is an admin or superadmin.
     *
     * @param Request $request
     * @return bool
     */
    private function isAdminOrSuperAdmin(Request $request): bool
    {
        $user = $request->__authenticatedUser;
        return $user->user_type === 'admin' || $user->user_type === 'superadmin';
    }

    /**
     * Validate the job request.
     *
     * @param Request $request
     * @return void
     * @throws ValidationException
     */
    private function validateJobRequest(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'key' => 'required',
            // Add more validation rules here
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }
}
