<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\CommandCenter\CalendarEvent;
use App\Services\CommandCenter\CalendarEventService;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    protected CalendarEventService $service;

    public function __construct(CalendarEventService $service)
    {
        $this->service = $service;
    }

    /**
     * Full calendar page.
     */
    public function index(Request $request)
    {
        $user  = $request->user();
        $year  = (int) $request->get('year', now()->year);
        $month = (int) $request->get('month', now()->month);
        $view  = $request->get('view', 'month');

        $grid = $this->service->getMonthGrid($user, $year, $month);

        return view('command-center.calendar.index', [
            'user'        => $user,
            'year'        => $year,
            'month'       => $month,
            'currentView' => $view,
            'grid'        => $grid,
            'events'      => $grid['events'],
            'byDate'      => $grid['byDate'],
        ]);
    }

    /**
     * Get events for a date range (AJAX).
     */
    public function events(Request $request)
    {
        $user    = $request->user();
        $start   = $request->get('start', now()->startOfMonth()->toDateString());
        $end     = $request->get('end', now()->endOfMonth()->toDateString());
        $filters = $request->only(['event_type', 'status', 'property_id']);

        $events = $this->service->getEventsForRange($user, $start, $end, $filters);

        return response()->json($events->map(fn (CalendarEvent $e) => [
            'id'         => $e->id,
            'title'      => $e->title,
            'start'      => $e->event_date->toIso8601String(),
            'end'        => $e->end_date?->toIso8601String(),
            'allDay'     => $e->all_day,
            'color'      => $e->colour,
            'type'       => $e->event_type,
            'category'   => $e->category,
            'priority'   => $e->priority,
            'status'     => $e->status,
            'propertyId' => $e->property_id,
            'contactId'  => $e->contact_id,
        ]));
    }

    /**
     * Store a new manual event.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'event_date' => 'required|date',
            'end_date'   => 'nullable|date|after_or_equal:event_date',
            'event_type' => 'nullable|string|max:50',
            'priority'   => 'nullable|in:low,normal,high,critical',
            'all_day'        => 'nullable|boolean',
            'description'    => 'nullable|string',
            'property_id'    => 'nullable|exists:properties,id',
            'contact_id'     => 'nullable|exists:contacts,id',
            'send_reminder'  => 'nullable|boolean',
        ]);

        $data = $request->all();
        $data['send_reminder'] = $request->boolean('send_reminder');

        $event = $this->service->createManual($data, $request->user());

        if ($request->wantsJson()) {
            return response()->json($event, 201);
        }

        return back()->with('success', 'Event created.');
    }

    /**
     * Update an event.
     */
    public function update(Request $request, CalendarEvent $calendarEvent)
    {
        $request->validate([
            'title'      => 'sometimes|required|string|max:255',
            'event_date' => 'sometimes|required|date',
            'end_date'   => 'nullable|date|after_or_equal:event_date',
            'status'     => 'nullable|in:pending,completed,overdue,dismissed',
            'priority'   => 'nullable|in:low,normal,high,critical',
        ]);

        $event = $this->service->update($calendarEvent, $request->all());

        if ($request->wantsJson()) {
            return response()->json($event);
        }

        return back()->with('success', 'Event updated.');
    }

    /**
     * Soft-delete an event.
     */
    public function destroy(Request $request, CalendarEvent $calendarEvent)
    {
        $this->service->delete($calendarEvent);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Event removed.');
    }

    /**
     * Mark event completed.
     */
    public function complete(CalendarEvent $calendarEvent)
    {
        $calendarEvent->markCompleted();
        return back()->with('success', 'Event completed.');
    }

    /**
     * Dismiss event.
     */
    public function dismiss(CalendarEvent $calendarEvent)
    {
        $calendarEvent->markDismissed();
        return back()->with('success', 'Event dismissed.');
    }
}
