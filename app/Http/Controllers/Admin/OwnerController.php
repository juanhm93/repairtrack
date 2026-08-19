<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ApplicationMaintenanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class OwnerController extends Controller
{
    public function __construct(private ApplicationMaintenanceService $maintenance) {}

    public function index(): Response
    {
        return Inertia::render('admin/Index', [
            'users' => $this->clients(),
        ]);
    }

    public function migrate(Request $request): RedirectResponse
    {
        try {
            $output = $this->maintenance->migrate();
            $this->maintenance->promoteToAdmin($this->authenticatedUser($request));
        } catch (Throwable $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $output !== '' ? $output : __('Migraciones al día.'),
        ]);

        return back();
    }

    public function clearCache(): RedirectResponse
    {
        try {
            $output = $this->maintenance->clearCache();
        } catch (Throwable $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => $exception->getMessage(),
            ]);

            return back();
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $output !== '' ? $output : __('Caché borrada.'),
        ]);

        return back();
    }

    /**
     * @return list<array{id: int, name: string, email: string, created_at: string|null, is_admin?: bool}>
     */
    private function clients(): array
    {
        $hasAdminColumn = Schema::hasColumn('users', 'is_admin');
        $columns = ['id', 'name', 'email', 'created_at'];

        if ($hasAdminColumn) {
            $columns[] = 'is_admin';
        }

        $clients = [];

        foreach (User::query()->orderByDesc('created_at')->get($columns) as $user) {
            $client = [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->created_at?->toIso8601String(),
            ];

            if ($hasAdminColumn) {
                $client['is_admin'] = $user->is_admin;
            }

            $clients[] = $client;
        }

        return $clients;
    }

    private function authenticatedUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
