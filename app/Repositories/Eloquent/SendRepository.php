<?php

namespace App\Repositories\Eloquent;

use App\DTOs\SendData;
use App\Models\Send;
use App\Repositories\Interfaces\SendRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Facades\DB;

class SendRepository implements SendRepositoryInterface
{
    public function __construct(protected Send $model) {}

    public function find(string $id): ?Send
    {
        return $this->model
            ->with('authorizedUsers')
            ->where('id', BinaryCodec::encode($id, 'ulid'))
            ->first();
    }

    public function findAll(string $userId, array $columns): Collection
    {
        return $this->model
            ->with('authorizedUsers')
            ->select($columns)
            ->where('user_id', $userId)
            ->get();
    }

    public function create(SendData $data, array $pivotData = []): Send
    {
        return DB::transaction(function () use ($data, $pivotData) {
            $send = $this->fillSend($data);
            $send->save();

            if (! empty($pivotData)) {
                DB::table($send->authorizedUsers()->getTable())->insert($pivotData);
            }

            return $send->load('authorizedUsers');
        });
    }

    private function fillSend(SendData $data, ?Send $send = null): Send
    {
        $send ??= $this->model->newInstance();
        $sendWithUserId = [
            ...$data->toArray(),
            'user_id' => auth()->id(),
        ];

        return $send->fill($sendWithUserId);
    }

    public function update(string $id, SendData $data, array $pivotData = []): Send
    {
        $record = $this->model->findOrFail($id);

        return DB::transaction(function () use ($record, $data, $pivotData) {
            $record = $this->fillSend($data, $record);
            $record->save();

            if (! empty($pivotData)) {
                $record->authorizedUsers()->detach();
                DB::table($record->authorizedUsers()->getTable())->insert($pivotData);
            }

            return $record->load('authorizedUsers');
        });
    }

    public function delete(string $id): bool
    {
        $record = $this->model
            ->where('id', BinaryCodec::encode($id, 'ulid'))
            ->first();

        if ($record === null) {
            return false;
        }

        return (bool) $record->delete();
    }

    public function findExpired(): Collection
    {
        return $this->model->query()
            ->where('valid_to', '<', now())
            ->get(['id', 'user_id']);
    }

    public function deleteExpired(): int
    {
        return $this->model->query()
            ->where('valid_to', '<', now())
            ->delete();
    }
}
