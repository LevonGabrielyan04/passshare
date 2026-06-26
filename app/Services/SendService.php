<?php

namespace App\Services;

use App\Actions\Interfaces\PreparesSendPivotData;
use App\DTOs\SendData;
use App\Enums\TimePeriod;
use App\Exceptions\SendLimitExceededException;
use App\Models\Send;
use App\Models\User;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendServiceInterface;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SendService implements SendServiceInterface
{
    public function __construct(
        protected SendRepositoryInterface $sendRepository,
        protected PreparesSendPivotData $prepareSendPivotData
    ) {}

    public function createSend(array $data): Send
    {
        $sendData = $this->buildSendData($data, (string) Str::ulid());

        $viewerIds = $this->resolveViewerIds($data['viewers'] ?? []);
        $pivotData = $this->prepareSendPivotData->execute($sendData->id, $viewerIds->toArray());

        return $this->sendRepository->create($sendData, $pivotData);
    }

    public function updateSend(string $id, array $data): Send|bool
    {
        $sendData = $this->buildSendData($data);

        $viewerIds = $this->resolveViewerIds($data['viewers'] ?? []);
        $pivotData = $this->prepareSendPivotData->execute($id, $viewerIds->toArray());

        return $this->sendRepository->update($id, $sendData, $pivotData);
    }

    private function calculateExpiration(string $expire_after): CarbonInterface
    {
        $period = TimePeriod::tryFrom($expire_after) ?? TimePeriod::ONE_DAY;

        return $period->toCarbon();
    }

    private function resolveViewerIds(array $emails): Collection
    {
        if (empty($emails)) {
            return collect();
        }

        return User::whereIn('email', $emails)->pluck('id');
    }

    /**
     * Build a SendData DTO from the validated request data.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildSendData(array $data, ?string $id = null): SendData
    {
        return new SendData(
            userId: auth()->id(),
            message: $data['message'],
            name: $data['name'] ?? 'Send-'.time().'-'.Str::random(5),
            validTo: $this->calculateExpiration($data['expire_after'] ?? '1_day'),
            id: $id,
        );
    }
}
