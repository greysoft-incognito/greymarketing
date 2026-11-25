<?php

namespace App\Http\Controllers\Workspace;

use App\Enums\HttpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactListCollection;
use App\Http\Resources\ContactListResource;
use App\Models\ContactList;
use App\Models\Workspace;
use Illuminate\Http\Request;

class ContactListController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        $this->authorize('view', $workspace);

        $lists = $workspace->lists()
            ->withCount('contacts')
            ->latest('id')
            ->paginate($request->input('limit', 30));

        return (new ContactListCollection($lists))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize('contribute', $workspace);

        $valid = $this->validate($request, [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $list = $workspace->lists()->create($valid);

        return (new ContactListResource($list->loadCount('contacts')))->additional([
            'message' => 'List created.',
            'status' => 'success',
            'status_code' => HttpStatus::CREATED,
        ])->response()->setStatusCode(HttpStatus::CREATED->value);
    }

    public function show(Workspace $workspace, ContactList $list)
    {
        $this->authorize('view', $workspace);

        return (new ContactListResource($list->loadCount('contacts')))->additional([
            'message' => HttpStatus::message(HttpStatus::OK),
            'status' => 'success',
            'status_code' => HttpStatus::OK,
        ]);
    }

    public function update(Request $request, Workspace $workspace, ContactList $list)
    {
        $this->authorize('contribute', $workspace);

        $valid = $this->validate($request, [
            'name' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        $list->update($valid);

        return (new ContactListResource($list->loadCount('contacts')))->additional([
            'message' => 'List updated.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    public function destroy(Workspace $workspace, ContactList $list)
    {
        $this->authorize('contribute', $workspace);

        $list->delete();

        return (new ContactListResource($list))->additional([
            'message' => 'List deleted.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Add existing contacts to the list.
     */
    public function attach(Request $request, Workspace $workspace, ContactList $list)
    {
        $this->authorize('contribute', $workspace);

        $valid = $this->validate($request, [
            'contacts' => ['required', 'array', 'max:1000'],
            'contacts.*' => ['string'],
        ]);

        $list->addContacts(
            $workspace->contacts()->whereIn('ulid', $valid['contacts'])->pluck('id')->all()
        );

        return (new ContactListResource($list->loadCount('contacts')))->additional([
            'message' => 'Contacts added to list.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }

    /**
     * Remove contacts from the list without deleting them.
     */
    public function detach(Request $request, Workspace $workspace, ContactList $list)
    {
        $this->authorize('contribute', $workspace);

        $valid = $this->validate($request, [
            'contacts' => ['required', 'array', 'max:1000'],
            'contacts.*' => ['string'],
        ]);

        $list->contacts()->detach(
            $workspace->contacts()->whereIn('ulid', $valid['contacts'])->pluck('id')
        );

        return (new ContactListResource($list->loadCount('contacts')))->additional([
            'message' => 'Contacts removed from list.',
            'status' => 'success',
            'status_code' => HttpStatus::ACCEPTED,
        ])->response()->setStatusCode(HttpStatus::ACCEPTED->value);
    }
}
