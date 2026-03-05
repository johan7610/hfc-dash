<?php

namespace App\Http\Controllers\CoreX;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\ContactType;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $query = Contact::with('type')->orderBy('last_name')->orderBy('first_name');

        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(function ($q) use ($s) {
                $q->where('first_name', 'like', "%{$s}%")
                  ->orWhere('last_name',  'like', "%{$s}%")
                  ->orWhere('phone',      'like', "%{$s}%")
                  ->orWhere('email',      'like', "%{$s}%");
            });
        }

        if ($request->filled('type')) {
            $query->where('contact_type_id', $request->type);
        }

        $contacts     = $query->paginate(25)->withQueryString();
        $contactTypes = ContactType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('corex.contacts.index', compact('contacts', 'contactTypes'));
    }

    public function show(Contact $contact)
    {
        $contact->load(['type', 'createdBy', 'contactNotes.user', 'documents.uploadedBy']);
        $contactTypes = ContactType::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get();

        return view('corex.contacts.show', compact('contact', 'contactTypes'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'phone'           => 'required|string|max:30',
            'email'           => 'nullable|email|max:150',
            'contact_type_id' => 'nullable|exists:contact_types,id',
            'notes'           => 'nullable|string|max:1000',
        ]);

        $data['created_by_user_id'] = auth()->id();

        Contact::create($data);

        return redirect()->route('corex.contacts.index')->with('success', 'Contact added successfully.');
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'phone'           => 'required|string|max:30',
            'email'           => 'nullable|email|max:150',
            'contact_type_id' => 'nullable|exists:contact_types,id',
            'notes'           => 'nullable|string|max:1000',
            'birthday'        => 'nullable|date',
            'id_number'       => 'nullable|string|max:20',
            'address'         => 'nullable|string|max:500',
        ]);

        $contact->update($data);

        // Redirect to show page if coming from there, otherwise index
        if ($request->has('_from_show')) {
            return redirect()->route('corex.contacts.show', $contact)->with('success', 'Contact updated.');
        }

        return redirect()->route('corex.contacts.index')->with('success', 'Contact updated.');
    }

    public function destroy(Contact $contact)
    {
        $contact->delete();

        return redirect()->route('corex.contacts.index')->with('success', 'Contact deleted.');
    }
}
