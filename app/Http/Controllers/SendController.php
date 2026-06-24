<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSendRequest;
use App\Http\Requests\UpdateSendRequest;
use App\Models\Send;
use App\Repositories\Interfaces\SendRepositoryInterface;
use App\Services\Interfaces\SendReadServiceInterface;
use App\Services\Interfaces\SendServiceInterface;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class SendController extends Controller implements HasMiddleware
{
    public function __construct(private readonly SendRepositoryInterface $sendRepository,
        private readonly SendServiceInterface $sendService,
        private readonly SendReadServiceInterface $sendReadService) {}

    public static function middleware(): array
    {
        return [
            new Middleware(['throttle:sends-index'], only: ['index']),
            new Middleware(['can:create,App\Models\Send'], only: ['create']),
            new Middleware(['throttle:sends-write', 'can:create,App\Models\Send'], only: ['store']),
            new Middleware('can:view,send', only: ['show']),
            new Middleware(['throttle:sends-write', 'can:update,send'], only: ['edit', 'update']),
            new Middleware('can:forceDelete,send', only: ['destroy']),
        ];
    }

    public function index()
    {
        $sends = $this->sendReadService->findAll();

        return view('dashboard', compact('sends'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return view('sends.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreSendRequest $request)
    {
        $this->sendService->createSend($request->validated());

        return redirect()->route('dashboard')
            ->with('success', 'Send created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Send $send)
    {
        $send = $this->sendReadService->findOne($send);

        return view('sends.show', compact('send'));
    }

    /**
     * Show the form for editing the specified resource.
     */
//    public function edit(Send $send)
//    {
//        return view('sends.edit', compact('send'));
//    }

    /**
     * Update the specified resource in storage.
     */
//    public function update(UpdateSendRequest $request, Send $send)
//    {
//        $this->sendService->updateSend($send->getKey(), $request->validated());
//
//        return redirect()->route('dashboard')
//            ->with('success', 'Send updated successfully.');
//    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Send $send)
    {
        $this->sendRepository->delete($send->getKey());

        return redirect()->route('dashboard')
            ->with('success', 'Send deleted successfully.');
    }
}
