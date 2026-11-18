<?php

namespace App\Http\Requests\Task;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AssignTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'developer_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $user = $this->user();
            $developerId = $this->input('developer_id');

            if ($developerId && $user && !$user->isSuperAdmin()) {
                $dev = User::find($developerId);
                if ($dev && $dev->organization_id !== $user->organization_id) {
                    $validator->errors()->add('developer_id', 'Developer must belong to your organization.');
                }
            }
        });
    }
}
