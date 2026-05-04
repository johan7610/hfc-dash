<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\CommandCenter\CalendarEvent;
use App\Models\CommandCenter\CalendarEventClassSetting;
use App\Services\CommandCenter\Calendar\CalendarThresholdResolver;
use App\Services\CommandCenter\Calendar\CalendarVisibilityResolver;
use App\Services\CommandCenter\CalendarEventService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CalendarController extends Controller
{
    public function __construct(
        private CalendarEventService $service,
        private CalendarThresholdResolver $thresholdResolver,
        private CalendarVisibilityResolver $visibilityResolver,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $view = $request->get('view', 'month');

        // Filter params (shared across all views)
        $typeFilter     = $request->input('types', []);
        $categoryFilter = $request->input('categories', []);
        $scope          = $request->input('scope', 'all');

        $shared = $this->sharedViewData($user, $view, $typeFilter, $categoryFilter, $scope);

        // ── Week view ──
        if ($view === 'week') {
            return $this->renderWeek($request, $user, $shared, $typeFilter, $categoryFilter, $scope);
        }

        // ── Day view ──
        if ($view === 'day') {
            return $this->renderDay($request, $user, $shared, $typeFilter, $categoryFilter, $scope);
        }

        // ── Month + Agenda (existing) ──
        return $this->renderMonthAgenda($request, $user, $shared, $view, $typeFilter, $categoryFilter, $scope);
    }

    // ── View renderers ──

    private function renderWeek(Request $request, $user, array $shared, array $typeFilter, array $categoryFilter, string $scope)
    {
        $anchor = $this->anchorDate($request);
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->addDays(6)->endOfDay();

        $raw = $this->service->getEventsForRange($user, $weekStart->toDateString(), $weekEnd->toDateString());
        $filtered = $this->applyFilters($raw, $user, $typeFilter, $categoryFilter, $scope);

        $weekDays = collect();
        for ($i = 0; $i < 7; $i++) {
            $day = $weekStart->copy()->addDays($i);
            $weekDays->push([
                'date'     => $day,
                'is_today' => $day->isSameDay(Carbon::today()),
                'events'   => $filtered->filter(fn ($e) => $e->event_date->isSameDay($day))->values(),
            ]);
        }

        return view('command-center.calendar.index', $shared + [
            'weekStart'   => $weekStart,
            'weekEnd'     => $weekEnd,
            'weekDays'    => $weekDays,
            'anchorDate'  => $anchor,
            'prevAnchor'  => $weekStart->copy()->subWeek()->toDateString(),
            'nextAnchor'  => $weekStart->copy()->addWeek()->toDateString(),
        ]);
    }

    private function renderDay(Request $request, $user, array $shared, array $typeFilter, array $categoryFilter, string $scope)
    {
        $anchor = $this->anchorDate($request);
        $dayStart = $anchor->copy()->startOfDay();
        $dayEnd   = $anchor->copy()->endOfDay();

        $raw = $this->service->getEventsForRange($user, $dayStart->toDateString(), $dayEnd->toDateString());
        $dayEvents = $this->applyFilters($raw, $user, $typeFilter, $categoryFilter, $scope)
            ->sortBy('event_date')
            ->values();

        return view('command-center.calendar.index', $shared + [
            'dayEvents'   => $dayEvents,
            'anchorDate'  => $anchor,
            'prevAnchor'  => $anchor->copy()->subDay()->toDateString(),
            'nextAnchor'  => $anchor->copy()->addDay()->toDateString(),
        ]);
    }

    private function renderMonthAgenda(Request $request, $user, array $shared, string $view, array $typeFilter, array $categoryFilter, string $scope)
    {
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $range = $request->get('range', 'month');

        $grid = $this->service->getMonthGrid($user, $year, $month);

        $filteredByDate = [];
        foreach ($grid['byDate'] as $dateKey => $dayEvents) {
            $resolved = $this->applyFilters(collect($dayEvents), $user, $typeFilter, $categoryFilter, $scope);
            if ($resolved->isNotEmpty()) {
                $filteredByDate[$dateKey] = $resolved->all();
            }
        }
        $filteredEvents = collect($filteredByDate)->flatten(1);

        // Agenda range logic
        $rangeGroups = [
            'Current'  => ['month' => 'This month', 'year' => 'This year'],
            'Past'     => ['last30' => 'Last 30 days', 'last3months' => 'Last 3 months', 'last6months' => 'Last 6 months', 'lastyear' => 'Last year', 'allpast' => 'All past events'],
            'Upcoming' => ['next30' => 'Next 30 days', '3months' => 'Next 3 months', '6months' => 'Next 6 months', 'allupcoming' => 'All upcoming'],
            'Custom'   => ['custom' => 'Custom range'],
        ];
        $rangeFlat = [];
        foreach ($rangeGroups as $group) { $rangeFlat += $group; }
        if (!array_key_exists($range, $rangeFlat)) { $range = 'month'; }

        $base  = Carbon::create($year, $month, 1)->startOfMonth();
        $today = now()->startOfDay();
        $parseDate = function ($v, Carbon $fb): Carbon { if (!$v) return $fb; try { return Carbon::parse($v); } catch (\Throwable $e) { return $fb; } };

        switch ($range) {
            case 'last30':       $rangeStart = $today->copy()->subDays(30); $rangeEnd = $today->copy()->endOfDay(); break;
            case 'last3months':  $rangeStart = $base->copy()->subMonthsNoOverflow(3)->startOfMonth(); $rangeEnd = $base->copy()->endOfMonth(); break;
            case 'last6months':  $rangeStart = $base->copy()->subMonthsNoOverflow(6)->startOfMonth(); $rangeEnd = $base->copy()->endOfMonth(); break;
            case 'lastyear':     $rangeStart = $today->copy()->subYearNoOverflow(); $rangeEnd = $today->copy()->endOfDay(); break;
            case 'allpast':      $rangeStart = Carbon::create(2000, 1, 1)->startOfDay(); $rangeEnd = $today->copy()->endOfDay(); break;
            case 'next30':       $rangeStart = $today->copy(); $rangeEnd = $today->copy()->addDays(30)->endOfDay(); break;
            case '3months':      $rangeStart = $base->copy()->startOfMonth(); $rangeEnd = $base->copy()->addMonthsNoOverflow(3)->endOfMonth(); break;
            case '6months':      $rangeStart = $base->copy()->startOfMonth(); $rangeEnd = $base->copy()->addMonthsNoOverflow(6)->endOfMonth(); break;
            case 'year':         $rangeStart = $base->copy()->startOfYear(); $rangeEnd = $base->copy()->endOfYear(); break;
            case 'allupcoming':  $rangeStart = $today->copy(); $rangeEnd = $today->copy()->addYearsNoOverflow(5)->endOfDay(); break;
            case 'custom':
                $rangeStart = $parseDate($request->get('from'), $base->copy()->startOfMonth())->startOfDay();
                $rangeEnd   = $parseDate($request->get('to'),   $base->copy()->endOfMonth())->endOfDay();
                if ($rangeEnd->lt($rangeStart)) { [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()]; }
                break;
            default: $range = 'month'; $rangeStart = $base->copy()->startOfMonth(); $rangeEnd = $base->copy()->endOfMonth(); break;
        }

        $agendaEvents = $this->applyFilters(
            $this->service->getEventsForRange($user, $rangeStart->toDateString(), $rangeEnd->toDateString()),
            $user, $typeFilter, $categoryFilter, $scope
        );

        $prevMonth = $base->copy()->subMonth();
        $nextMonth = $base->copy()->addMonth();

        return view('command-center.calendar.index', $shared + [
            'year'             => $year,
            'month'            => $month,
            'grid'             => $grid,
            'events'           => $filteredEvents,
            'byDate'           => $filteredByDate,
            'agendaEvents'     => $agendaEvents,
            'agendaRange'      => $range,
            'agendaRangeLabel' => $rangeFlat[$range],
            'agendaFrom'       => $rangeStart->toDateString(),
            'agendaTo'         => $rangeEnd->toDateString(),
            'rangeGroups'      => $rangeGroups,
            'prevMonth'        => $prevMonth,
            'nextMonth'        => $nextMonth,
        ]);
    }

    // ── Shared data ──

    private function sharedViewData($user, string $view, array $typeFilter, array $categoryFilter, string $scope): array
    {
        return [
            'user'                => $user,
            'currentView'         => $view,
            'typeFilter'          => $typeFilter,
            'categoryFilter'      => $categoryFilter,
            'scope'               => $scope,
            'availableTypes'      => ['compliance', 'deal', 'document', 'lease', 'leave', 'payroll', 'people', 'property', 'recurring'],
            'availableCategories' => CalendarEventClassSetting::withoutGlobalScopes()
                ->where('is_active', true)->orderBy('label')
                ->get(['event_class', 'label'])->unique('event_class')->values(),
        ];
    }

    private function anchorDate(Request $request): Carbon
    {
        return $request->filled('date')
            ? Carbon::parse($request->input('date'))->startOfDay()
            : Carbon::today();
    }

    // ── AJAX + CRUD (unchanged) ──

    public function events(Request $request)
    {
        $user = $request->user();
        $start = $request->get('start', now()->startOfMonth()->toDateString());
        $end   = $request->get('end', now()->endOfMonth()->toDateString());
        $filters = $request->only(['event_type', 'status', 'property_id']);

        $resolved = $this->applyFilters(
            $this->service->getEventsForRange($user, $start, $end, $filters),
            $user,
            $request->input('types', []),
            $request->input('categories', []),
            $request->input('scope', 'all'),
        );

        return response()->json($resolved->map(fn (CalendarEvent $e) => [
            'id' => $e->id, 'title' => $e->title,
            'start' => $e->event_date->toIso8601String(),
            'end' => $e->end_date?->toIso8601String(),
            'allDay' => $e->all_day, 'colour' => $e->resolved_colour,
            'type' => $e->event_type, 'category' => $e->category,
            'priority' => $e->priority, 'status' => $e->status,
            'propertyId' => $e->property_id, 'contactId' => $e->contact_id,
        ])->values());
    }

    public function show(Request $request, CalendarEvent $calendarEvent)
    {
        $user = $request->user();
        if (!$this->visibilityResolver->canSee($calendarEvent, $user)) { abort(403); }

        $colour = $this->thresholdResolver->resolveForEvent($calendarEvent);
        $cfg = CalendarEventClassSetting::forAgencyAndClass($calendarEvent->agency_id, $calendarEvent->category);

        return response()->json([
            'id' => $calendarEvent->id, 'title' => $calendarEvent->title,
            'description' => $calendarEvent->description,
            'event_date' => $calendarEvent->event_date->toIso8601String(),
            'event_date_h' => $calendarEvent->event_date->format('D, d M Y'),
            'days_diff' => (int) now()->startOfDay()->diffInDays($calendarEvent->event_date->copy()->startOfDay(), false),
            'colour' => $colour, 'category' => $calendarEvent->category,
            'class_label' => $cfg?->label ?? $calendarEvent->category,
            'event_type' => $calendarEvent->event_type, 'status' => $calendarEvent->status,
            'source_link' => $this->resolveSourceLink($calendarEvent),
            'metadata' => $calendarEvent->metadata,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255', 'event_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'event_type' => 'nullable|string|max:50', 'priority' => 'nullable|in:low,normal,high,critical',
            'all_day' => 'nullable|boolean', 'description' => 'nullable|string',
            'property_id' => 'nullable|exists:properties,id', 'contact_id' => 'nullable|exists:contacts,id',
            'send_reminder' => 'nullable|boolean',
        ]);
        $data = $request->all();
        $data['send_reminder'] = $request->boolean('send_reminder');
        $event = $this->service->createManual($data, $request->user());
        return $request->wantsJson() ? response()->json($event, 201) : back()->with('success', 'Event created.');
    }

    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $request->validate([
            'title' => 'sometimes|required|string|max:255', 'event_date' => 'sometimes|required|date',
            'end_date' => 'nullable|date|after_or_equal:event_date',
            'status' => 'nullable|in:pending,completed,overdue,dismissed',
            'priority' => 'nullable|in:low,normal,high,critical',
        ]);
        $event = $this->service->update($calendarEvent, $request->all());
        return $request->wantsJson() ? response()->json($event) : back()->with('success', 'Event updated.');
    }

    public function destroy(Request $request, CalendarEvent $calendarEvent)
    {
        $this->service->delete($calendarEvent);
        return $request->wantsJson() ? response()->json(['ok' => true]) : back()->with('success', 'Event removed.');
    }

    public function complete(CalendarEvent $calendarEvent)
    {
        $calendarEvent->markCompleted();
        return back()->with('success', 'Event completed.');
    }

    public function dismiss(CalendarEvent $calendarEvent)
    {
        $calendarEvent->markDismissed();
        return back()->with('success', 'Event dismissed.');
    }

    // ── Private helpers ──

    private function applyFilters(Collection $events, $user, array $typeFilter, array $categoryFilter, string $scope): Collection
    {
        $filtered = $events
            ->when(!empty($typeFilter), fn ($c) => $c->whereIn('event_type', $typeFilter))
            ->when(!empty($categoryFilter), fn ($c) => $c->whereIn('category', $categoryFilter))
            ->when($scope === 'own', fn ($c) => $c->where('user_id', $user->id))
            ->when($scope === 'branch' && $user->branch_id, fn ($c) => $c->where('branch_id', $user->branch_id));

        $visible = $this->visibilityResolver->filterVisible($filtered, $user);
        return collect($visible)->map(function ($event) {
            $event->resolved_colour = $this->thresholdResolver->resolveForEvent($event);
            return $event;
        })->filter(fn ($e) => $e->resolved_colour !== null)->values();
    }

    private function resolveSourceLink(CalendarEvent $event): ?array
    {
        if (!$event->source_type || !$event->source_id) return null;
        if (str_starts_with($event->source_type, 'synthetic:')) return null;
        $routeMap = [
            \App\Models\Property::class => ['route' => 'corex.properties.show', 'label' => 'View property'],
            \App\Models\FicaSubmission::class => ['route' => 'compliance.fica.show', 'label' => 'View FICA submission'],
            \App\Models\Compliance\RmcpVersion::class => ['route' => 'compliance.rmcp.show', 'label' => 'View RMCP version'],
            \App\Models\Compliance\EmployeeScreening::class => ['route' => 'compliance.screenings.show', 'label' => 'View screening'],
            \App\Models\Payroll\PayrollRun::class => ['route' => 'payroll.runs.show', 'label' => 'View payroll run'],
            \App\Models\Payroll\PayrollEmployee::class => ['route' => 'payroll.employees.show', 'label' => 'View employee'],
        ];
        $entry = $routeMap[$event->source_type] ?? null;
        if (!$entry) return null;
        try { return ['url' => route($entry['route'], $event->source_id), 'label' => $entry['label']]; }
        catch (\Throwable $e) { return null; }
    }
}
