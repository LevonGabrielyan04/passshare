<?php

namespace App\Http\Requests;

use App\Enums\TimePeriod;
use App\Models\Send;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique(Send::class)->where('user_id', auth()->id()),
            ],
            'viewers' => [
                'required',
                'array',
                'min:1',
                'max:100',
            ],
            'viewers.*' => [
                'required',
                'email',
                'distinct',
                Rule::exists(User::class, 'email'),
            ],
            'message' => [
                'required',
                'string',
                'max:'.config('send.message.encrypted_max_length'),
            ],
            'expire_after' => [
                'required',
                Rule::in(TimePeriod::values()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'viewers.*.exists' => 'Email address number :position is not found in our registered users table.',
        ];
    }
}
