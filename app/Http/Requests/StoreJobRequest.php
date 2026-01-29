<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreJobRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->canEdit() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'job_number' => 'required|string|unique:jobs,job_number',
            'franchise' => 'required|in:PC,CV',
            'plate_number' => 'required|string',
            'unit_type' => 'nullable|string',
            'chassis_number' => 'nullable|string',
            'service_advisor' => 'nullable|string',
            'technician' => 'nullable|string',
            'foreman' => 'nullable|string',
            'block' => 'nullable|string',
            'job_type' => 'nullable|string',
            'job_date' => 'nullable|date',
            'date_in' => 'nullable|date',
            'check_in_time' => 'nullable|string',
            'promise_date' => 'nullable|date',
            'estimated_amount' => 'nullable|numeric',
            'payment_type' => 'nullable|string',
            'work_status' => 'nullable|string',
            'job_description' => 'nullable|string',
            'description' => 'nullable|string',
            'customer_name' => 'nullable|string',
            'customer_address' => 'nullable|string',
            'initial_remark' => 'nullable|string',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'job_number.required' => 'Job/WIP number is required.',
            'job_number.unique' => 'This job number already exists.',
            'franchise.required' => 'Franchise (PC/CV) is required.',
            'plate_number.required' => 'Plate number is required.',
        ];
    }
}
