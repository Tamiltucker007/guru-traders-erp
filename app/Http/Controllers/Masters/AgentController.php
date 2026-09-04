<?php

namespace App\Http\Controllers\Masters;

use App\Http\Controllers\Controller;
use App\Http\Requests\Masters\StoreAgentRequest;
use App\Http\Requests\Masters\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\AgentCommission;
use App\Models\CalculationBasis;
use App\Models\Category;
use App\Models\City;
use App\Models\Currency;
use App\Services\Masters\AgentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\View\View;

class AgentController extends Controller implements HasMiddleware
{
    public function __construct(
        private readonly AgentService $agents
    ) {
    }

    /**
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('permission:agent.view', only: ['index', 'show']),
            new Middleware('permission:agent.create', only: ['create', 'store']),
            new Middleware('permission:agent.edit', only: ['edit', 'update', 'toggleStatus']),
            new Middleware('permission:agent.delete', only: ['destroy']),
        ];
    }

    public function index(Request $request): View
    {
        $agents = Agent::query()
            ->with(['commissionBasis:id,name', 'commissions.currency:id,iso_code'])
            ->search($request->string('search')->toString())
            ->status($request->string('status')->toString())
            ->when($request->filled('agent_type'), fn ($q) => $q->where('agent_type', $request->string('agent_type')->toString()))
            ->sort($request->string('sort')->toString(), $request->string('direction')->toString())
            ->paginate(10)
            ->withQueryString();

        return view('masters.agents.index', [
            'agents'   => $agents,
            'filters'  => $request->only('search', 'status', 'agent_type', 'sort', 'direction'),
        ]);
    }

    public function create(): View
    {
        return view('masters.agents.create', $this->formData());
    }

    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $agent = $this->agents->create($request->validated());

        return redirect()
            ->route('masters.agents.index')
            ->with('success', "Agent {$agent->display_code} created successfully.");
    }

    public function show(Agent $agent): View
    {
        return view('masters.agents.show', [
            'agent' => $agent->load(['categories', 'commissionBasis', 'commissions.currency', 'creator', 'updater']),
        ]);
    }

    public function edit(Agent $agent): View
    {
        // Loaded before formData(), which reads the commissions to decide which
        // currencies the picker has to keep.
        $agent->load(['categories', 'commissions']);

        return view('masters.agents.edit', $this->formData($agent) + [
            'agent' => $agent,
        ]);
    }

    public function update(UpdateAgentRequest $request, Agent $agent): RedirectResponse
    {
        $this->agents->update($agent, $request->validated());

        return redirect()
            ->route('masters.agents.index')
            ->with('success', "Agent {$agent->display_code} updated successfully.");
    }

    public function destroy(Agent $agent): RedirectResponse
    {
        $check = $this->agents->canDelete($agent);

        if (! $check['allowed']) {
            return back()->with('error', $check['reason']);
        }

        $agent->delete();

        return redirect()
            ->route('masters.agents.index')
            ->with('success', "Agent {$agent->display_code} deleted successfully.");
    }

    public function toggleStatus(Agent $agent): RedirectResponse
    {
        $agent->update([
            'status' => $agent->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', "Agent {$agent->display_code} marked {$agent->status}.");
    }

    public function checkCode(Request $request): JsonResponse
    {
        $field = $request->string('field')->toString();

        if (! in_array($field, ['display_code', 'name'], true)) {
            return response()->json(['message' => 'Unknown field.'], 422);
        }

        $taken = Agent::withTrashed()
            ->where($field, $request->string('value')->toString())
            ->when($request->filled('ignore'), fn ($q) => $q->whereKeyNot($request->integer('ignore')))
            ->exists();

        return response()->json(['available' => ! $taken]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(?Agent $agent = null): array
    {
        return [
            'agentTypes'       => Agent::TYPES,
            'categories'       => $this->categoriesForAgent($agent),
            'calculationBases' => $this->calculationBasesForAgent($agent),
            'currencies'       => $this->currenciesForAgent($agent),
            'commissionPayers' => Agent::COMMISSION_PAYERS,
            'paymentTerms'     => Agent::PAYMENT_TERMS,
            'commissionTypes'  => AgentCommission::TYPES,
            'banks'            => $this->banksForAgent($agent),
            'cities'           => $this->citiesForAgent($agent),
        ];
    }

    /**
     * Flat city list for the agent form (searchable). Agent stores the city
     * name as text, not a city_id, so options are name => name. If this agent
     * already has a city outside the seeded list (e.g. overseas), keep it.
     *
     * @return array<string, string>
     */
    private function citiesForAgent(?Agent $agent = null): array
    {
        $cities = City::active()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();

        $current = old('city', $agent?->city);
        if (filled($current) && ! array_key_exists($current, $cities)) {
            $cities = [$current => $current] + $cities;
        }

        return $cities;
    }

    /**
     * Bank-name dropdown options. Always includes the common list; if this
     * agent already has a bank outside the list, keep it so edit does not blank.
     *
     * @return array<string, string>
     */
    private function banksForAgent(?Agent $agent = null): array
    {
        $banks = collect(config('indian_banks', []))
            ->mapWithKeys(fn (string $name) => [$name => $name]);

        $current = $agent?->bank_name;
        if (filled($current) && ! $banks->has($current)) {
            $banks->put($current, $current);
        }

        return $banks->sortKeys()->all();
    }

    /**
     * Buyer-side commission entries carry their own currency; supplier-side
     * ones are INR and the form hides the picker. Inactive currencies are
     * excluded unless this agent already uses one — dropping it from the list
     * would silently blank a saved entry on the next save.
     */
    private function currenciesForAgent(?Agent $agent = null): \Illuminate\Support\Collection
    {
        $query = Currency::query();

        if ($agent) {
            $inUse = $agent->commissions->pluck('currency_id')->filter();

            $query->where(fn ($q) => $q->active()->orWhereIn('id', $inUse));
        } else {
            $query->active();
        }

        return $query->orderBy('iso_code')->get()->pluck('label', 'id');
    }

    private function categoriesForAgent(?Agent $agent = null): \Illuminate\Support\Collection
    {
        $query = Category::query();

        if ($agent) {
            $query->where(function ($q) use ($agent) {
                $q->active()->orWhereIn('id', $agent->categories()->pluck('categories.id'));
            });
        } else {
            $query->active();
        }

        return $query->orderBy('name')->pluck('name', 'id');
    }

    private function calculationBasesForAgent(?Agent $agent = null): \Illuminate\Support\Collection
    {
        $query = CalculationBasis::query();

        if ($agent) {
            $query->where(function ($q) use ($agent) {
                $q->active()->orWhere('id', $agent->calculation_basis_id);
            });
        } else {
            $query->active();
        }

        return $query->orderBy('name')->pluck('name', 'id');
    }
}
