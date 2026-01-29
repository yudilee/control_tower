<?php

namespace App\Actions\Jobs;

use App\Models\Job;

class CreateJob
{
    /**
     * Create a new job from validated data.
     *
     * @param array $data Validated job data
     * @param string|null $initialRemark Optional initial remark
     * @param string|null $createdBy Name of user creating the job
     * @param int|null $userId ID of user creating the job
     * @return Job
     */
    public function execute(array $data, ?string $initialRemark = null, ?string $createdBy = null, ?int $userId = null): Job
    {
        // Ensure status is set
        $data['status'] = $data['status'] ?? 'uninvoiced';

        // Copy job_date to date_in if date_in is not provided
        if (!empty($data['job_date']) && empty($data['date_in'])) {
            $data['date_in'] = $data['job_date'];
        }

        // Copy unit_type to type_unit if type_unit is not provided
        if (!empty($data['unit_type']) && empty($data['type_unit'])) {
            $data['type_unit'] = $data['unit_type'];
        }

        // Create the job
        $job = Job::create($data);

        // Add initial remark if provided
        if ($initialRemark) {
            $job->addRemark($initialRemark, $createdBy, $userId);
        }

        return $job;
    }
}
