<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreWalkInBookingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && $this->user()->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'dormitory_id' => [
                'required',
                'integer',
                Rule::exists('dormitories', 'id')
                    ->where(fn ($query) => $query->where('facility_id', $this->input('facility_id'))),
            ],
            'pdl_name' => ['required', 'string', 'max:255'],
            'visitor_full_name' => ['required', 'string', 'max:255'],
            'visitor_email' => ['nullable', 'email', 'max:255'],
            'visitor_contact' => ['required', 'string', 'max:20'],
            'visitor_sex' => ['required', 'string', 'in:Male,Female'],
            'visitor_age' => ['required', 'integer', 'min:1', 'max:120'],
            'visitor_id_type' => ['required', 'string', 'max:50'],
            'visitor_id_number' => ['required', 'string', 'max:50'],
            'relationship_to_pdl' => ['required', 'string', 'max:100'],
            'visit_date' => ['required', 'date', 'after_or_equal:today'],
            'time_slot' => ['required', 'string', 'max:50'],
            'accompanying_visitors' => ['nullable', 'array'],
            'accompanying_visitors.*.full_name' => ['required', 'string', 'max:255'],
            'accompanying_visitors.*.age' => ['required', 'integer', 'min:1', 'max:120'],
            'accompanying_visitors.*.sex' => ['required', 'string', 'in:Male,Female'],
            'accompanying_visitors.*.id_type' => ['required', 'string', 'max:50'],
            'accompanying_visitors.*.id_number' => ['required', 'string', 'max:50'],
            'accompanying_visitors.*.relationship_to_pdl' => ['required', 'string', 'max:100'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $user = $this->user();

            if ($user?->isFacilityAdmin()
                && (int) $this->input('facility_id') !== (int) $user->facility_id) {
                $validator->errors()->add('facility_id', 'This account can only access its assigned facility.');
            }
        }];
    }
}
