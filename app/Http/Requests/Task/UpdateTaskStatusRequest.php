<?php

namespace App\Http\Requests\Task;

use App\Enums\TaskStatus;
use App\Models\Task;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateTaskStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TaskStatus::class)],
        ];
    }

    /**
     * Configure the validator instance with business transition rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            /** @var Task|null $task */
            $task = $this->route('task');

            if (!$task instanceof Task) {
                // If route model binding resolved ID or didn't resolve directly
                $taskId = $this->route('id') ?? $this->route('task');
                $task = Task::find($taskId);
            }

            if ($task && $this->filled('status')) {
                $targetStatus = TaskStatus::tryFrom($this->input('status'));
                $currentStatus = $task->status instanceof TaskStatus 
                    ? $task->status 
                    : TaskStatus::tryFrom($task->status);

                if ($currentStatus && $targetStatus && !$currentStatus->canTransitionTo($targetStatus)) {
                    $validator->errors()->add(
                        'status',
                        "Invalid status transition from '{$currentStatus->value}' to '{$targetStatus->value}'. Allowed transitions: " . 
                        implode(', ', array_map(fn($s) => $s->value, $currentStatus->allowedTransitions()))
                    );
                }
            }
        });
    }
}
