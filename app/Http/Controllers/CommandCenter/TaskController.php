<?php

namespace App\Http\Controllers\CommandCenter;

use App\Http\Controllers\Controller;
use App\Models\CommandCenter\CommandTask;
use App\Services\CommandCenter\TaskService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    protected TaskService $service;

    public function __construct(TaskService $service)
    {
        $this->service = $service;
    }

    /**
     * Task board page (kanban + list).
     */
    public function index(Request $request)
    {
        $user     = $request->user();
        $view     = $request->get('view', 'kanban');
        $columns  = $this->service->getTasksByStatus($user);
        $summary  = $this->service->getSummary($user);

        return view('command-center.tasks.index', [
            'user'    => $user,
            'columns' => $columns,
            'summary' => $summary,
            'currentView' => $view,
        ]);
    }

    /**
     * Store a new task.
     */
    public function store(Request $request)
    {
        $request->validate([
            'title'       => 'required|string|max:255',
            'task_type'   => 'nullable|string|max:50',
            'priority'       => 'nullable|in:low,normal,high,critical',
            'due_date'       => 'nullable|date',
            'description'    => 'nullable|string',
            'assigned_to'    => 'nullable|exists:users,id',
            'property_id'    => 'nullable|exists:properties,id',
            'contact_id'     => 'nullable|exists:contacts,id',
            'send_reminder'  => 'nullable|boolean',
        ]);

        $data = $request->all();
        $data['assigned_to']    = $data['assigned_to'] ?? $request->user()->id;
        $data['task_type']      = $data['task_type'] ?? 'custom';
        $data['send_reminder']  = $request->boolean('send_reminder');

        $task = $this->service->create($data, $request->user());

        if ($request->wantsJson()) {
            return response()->json($task, 201);
        }

        return back()->with('success', 'Task created.');
    }

    /**
     * Update a task.
     */
    public function update(Request $request, CommandTask $task)
    {
        $request->validate([
            'title'    => 'sometimes|required|string|max:255',
            'status'   => 'nullable|in:todo,in_progress,awaiting,done,dismissed',
            'priority' => 'nullable|in:low,normal,high,critical',
            'due_date' => 'nullable|date',
        ]);

        if ($request->has('status') && count($request->all()) === 1) {
            $task = $this->service->updateStatus($task, $request->status);
        } else {
            $task = $this->service->update($task, $request->all());
        }

        if ($request->wantsJson()) {
            return response()->json($task);
        }

        return back()->with('success', 'Task updated.');
    }

    /**
     * Soft-delete a task.
     */
    public function destroy(Request $request, CommandTask $task)
    {
        $this->service->delete($task);

        if ($request->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Task removed.');
    }

    /**
     * Quick-complete a task (from dashboard checkbox).
     */
    public function complete(CommandTask $task)
    {
        $task->markDone();

        if (request()->wantsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', 'Task completed.');
    }

    /**
     * Update task status via AJAX (for drag-and-drop).
     */
    public function updateStatus(Request $request, CommandTask $task)
    {
        $request->validate(['status' => 'required|in:todo,in_progress,awaiting,done,dismissed']);

        $task = $this->service->updateStatus($task, $request->status);

        return response()->json($task);
    }
}
