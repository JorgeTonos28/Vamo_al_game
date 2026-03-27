<?php

namespace App\Http\Controllers\Api\V1\League;

use App\Actions\Admin\InviteUser;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\League\InviteLeagueMemberRequest;
use App\Http\Requests\Api\V1\League\UpdateLeagueMemberRequest;
use App\Models\LeagueCutExpense;
use App\Models\LeaguePlayer;
use App\Models\LeaguePlayerReferral;
use App\Services\LeagueOperations\LeagueManagementReportService;
use App\Services\LeagueOperations\LeagueManagementService;
use App\Services\LeagueOperations\LeagueOperationsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ManagementController extends Controller
{
    public function __construct(
        private readonly LeagueManagementService $managementService,
        private readonly LeagueManagementReportService $reportService,
        private readonly LeagueOperationsService $operationsService,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::success(
            $request,
            $this->managementService->pageData(
                $request->user(),
                $request->integer('cut_id') ?: null,
            ),
            'Modulo Gestion cargado.',
        );
    }

    public function storePayment(Request $request, LeaguePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'cut_id' => ['nullable', 'integer', 'exists:league_cuts,id'],
            'amount_cents' => ['required', 'integer', 'min:0'],
            'apply_referral_credit' => ['nullable', 'boolean'],
        ]);

        $this->managementService->recordPayment(
            $request->user(),
            $player,
            $validated['amount_cents'],
            (bool) ($validated['apply_referral_credit'] ?? false),
            $validated['cut_id'] ?? null,
        );

        return ApiResponse::success(
            $request,
            $this->managementService->pageData(
                $request->user(),
                $validated['cut_id'] ?? null,
            ),
            'Pago registrado.',
        );
    }

    public function destroyPayment(Request $request, LeaguePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'cut_id' => ['nullable', 'integer', 'exists:league_cuts,id'],
        ]);

        $this->managementService->removePayment(
            $request->user(),
            $player,
            $validated['cut_id'] ?? null,
        );

        return ApiResponse::success(
            $request,
            $this->managementService->pageData(
                $request->user(),
                $validated['cut_id'] ?? null,
            ),
            'Pago removido del corte.',
        );
    }

    public function storeExpense(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'cut_id' => ['nullable', 'integer', 'exists:league_cuts,id'],
            'name' => ['required', 'string', 'max:120'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'is_fixed' => ['nullable', 'boolean'],
        ]);

        $this->managementService->storeExpense(
            $request->user(),
            $validated['name'],
            $validated['amount_cents'],
            (bool) ($validated['is_fixed'] ?? false) ? 'fixed' : 'custom',
            $validated['cut_id'] ?? null,
        );

        return ApiResponse::success(
            $request,
            $this->managementService->pageData(
                $request->user(),
                $validated['cut_id'] ?? null,
            ),
            'Gasto agregado al corte.',
            201,
        );
    }

    public function destroyExpense(Request $request, LeagueCutExpense $expense): JsonResponse
    {
        $this->managementService->deleteExpense($request->user(), $expense);

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            'Gasto eliminado.',
        );
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sessions_limit' => ['required', 'integer', 'min:1', 'max:12'],
            'game_days' => ['required', 'array', 'min:1'],
            'game_days.*' => ['required', 'string', 'max:20'],
            'cut_day' => ['required', 'integer', 'min:1', 'max:30'],
            'member_fee_amount_cents' => ['required', 'integer', 'min:1'],
            'guest_fee_amount_cents' => ['required', 'integer', 'min:1'],
            'referral_credit_amount_cents' => ['required', 'integer', 'min:1'],
        ]);

        $this->managementService->updateSettings($request->user(), $validated);

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            'Configuracion de jornadas y cuotas actualizada.',
        );
    }

    public function storeReferral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'referrer_player_id' => ['required', 'integer', 'exists:league_players,id'],
            'referred_player_id' => ['required', 'integer', 'exists:league_players,id'],
        ]);

        /** @var LeaguePlayer $referrer */
        $referrer = LeaguePlayer::query()->findOrFail($validated['referrer_player_id']);
        /** @var LeaguePlayer $referred */
        $referred = LeaguePlayer::query()->findOrFail($validated['referred_player_id']);

        $this->managementService->storeReferral($request->user(), $referrer, $referred);

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            'Referido registrado.',
            201,
        );
    }

    public function destroyReferral(Request $request, LeaguePlayerReferral $referral): JsonResponse
    {
        $this->managementService->deleteReferral($request->user(), $referral);

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            'Referido eliminado.',
        );
    }

    public function storePlayer(InviteLeagueMemberRequest $request, InviteUser $inviteUser): JsonResponse
    {
        $context = $this->operationsService->requireAdminContext($request->user());
        $validated = $request->validated();
        $inviteUser->handle(
            $request->user(),
            [
                ...$validated,
                'league_id' => $context['league']->id,
            ],
        );

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            filled($validated['email'] ?? null)
                ? 'Invitacion enviada y miembro agregado a la liga.'
                : 'Miembro agregado a la liga sin invitacion por correo.',
            201,
        );
    }

    public function updatePlayer(UpdateLeagueMemberRequest $request, LeaguePlayer $player): JsonResponse
    {
        $this->managementService->updateRosterMember(
            $request->user(),
            $player,
            $request->validated(),
        );

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            'Datos del miembro actualizados.',
        );
    }

    public function updatePlayerStatus(Request $request, LeaguePlayer $player): JsonResponse
    {
        $validated = $request->validate([
            'active' => ['required', 'boolean'],
        ]);

        $this->managementService->setPlayerActive(
            $request->user(),
            $player,
            $validated['active'],
        );

        return ApiResponse::success(
            $request,
            $this->managementService->pageData($request->user()),
            $validated['active']
                ? 'Miembro reactivado en la liga.'
                : 'Miembro dado de baja en la liga.',
        );
    }

    public function report(Request $request): StreamedResponse
    {
        $data = $this->managementService->pageData(
            $request->user(),
            $request->integer('cut_id') ?: null,
        );

        $pdf = $this->reportService->makePdf($data['league']['name'], $data);
        $filename = sprintf(
            'gestion-%s-%s.pdf',
            $data['league']['slug'],
            $data['cut_selector']['selected_cut_id'],
        );

        return response()->streamDownload(
            static function () use ($pdf): void {
                echo $pdf;
            },
            $filename,
            [
                'Content-Type' => 'application/pdf',
            ],
        );
    }
}
