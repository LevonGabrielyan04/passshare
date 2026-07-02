<?php

namespace App\Http\Controllers;

use App\Http\Requests\SendRequest;
use App\Models\Send;
use App\Services\Interfaces\SendReadServiceInterface;
use App\Services\Interfaces\SendWriteServiceInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class SendController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly SendWriteServiceInterface $sendService,
        private readonly SendReadServiceInterface $sendReadService
    ) {}

    public static function middleware(): array
    {
        return [
            new Middleware(['throttle:sends-index'], only: ['index']),
            new Middleware(['can:create,App\Models\Send'], only: ['create']),
            new Middleware(['throttle:sends-write', 'can:create,App\Models\Send'], only: ['store']),
            new Middleware('can:view,send', only: ['show']),
            new Middleware('can:forceDelete,send', only: ['destroy']),
        ];
    }

    public function index(): View
    {
        $sends = $this->sendReadService->findAll();

        return view('dashboard', compact('sends'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('sends.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SendRequest $request): RedirectResponse
    {
        $this->sendService->createSend($request->validated());

        return redirect()->route('dashboard')
            ->with('success', 'Send created successfully.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Send $send): View
    {
        $send = $this->sendReadService->findOne($send);

        return view('sends.show', compact('send'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Send $send): RedirectResponse
    {
        $this->sendService->deleteSend($send->getKey());

        return redirect()->route('dashboard')
            ->with('success', 'Send deleted successfully.');
    }
}
