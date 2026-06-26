<?php

namespace App\Http\Requests;

use App\Models\Send;
use Illuminate\Support\Facades\Gate;

class StoreSendRequest extends SendRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
//        public function authorize(): bool
//        {
//            return Gate::allows('create', Send::class);
//        }
}
